<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantMonthMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentLedgerService
{
    public function __construct(
        private readonly BillingPolicy $policy,
        private readonly TenantBalanceService $balances,
    ) {}

    public function create(array $validated): Payment
    {
        return DB::transaction(function () use ($validated) {
            $tenant = Tenant::whereKey($validated['tenant_id'])->lockForUpdate()->firstOrFail();
            $dueDate = Carbon::parse($validated['due_date'])->startOfDay();
            $start = $this->policy->tenantStart($tenant);

            if ($start === null) {
                throw ValidationException::withMessages(['tenant_id' => 'Billing starts on the tenant’s move-in date.']);
            }
            if ($dueDate->lt($start)) {
                throw ValidationException::withMessages(['due_date' => 'The due date must be on or after the tenant’s billing start date.']);
            }

            $month = $dueDate->copy()->startOfMonth();
            $mode = $tenant->monthModes()->whereDate('month', $month)->where('mode', TenantMonthMode::STORAGE)->first();
            $charge = $mode ? $this->balances->monthlyCharge($tenant, $month, $mode) : null;
            if ($charge) {
                $existing = $tenant->payments()->whereNull('utility_bill_id')
                    ->where(fn ($query) => $query->whereNull('charge_type')->orWhere('charge_type', '!=', Payment::OPENING_BALANCE))
                    ->whereDate('due_date', '>=', $month)->whereDate('due_date', '<=', $month->copy()->endOfMonth())->exists();
                if ($existing || (float) $charge->amount !== round((float) $validated['amount'], 2)) {
                    throw ValidationException::withMessages([
                        'amount' => 'Use the Payment Ledger to collect the storage fee, or record the exact storage amount if it has not yet accrued.',
                    ]);
                }
                $dueDate = $charge->due_date;
            }

            $paidDate = $validated['status'] === 'paid' ? $validated['paid_date'] : null;
            $endDate = $paidDate ? Carbon::parse($paidDate)->startOfDay() : Carbon::today();
            $daysOverdue = (int) max(0, $dueDate->diffInDays($endDate, false));

            $receiptNumber = null;
            if ($validated['status'] === 'paid') {
                $receiptNumber = 'RCPT-' . strtoupper(uniqid());
            }

            return $tenant->payments()->create([
                'receipt_number' => $receiptNumber,
                'charge_type' => $charge?->charge_type,
                'amount' => $validated['amount'],
                'due_date' => $dueDate->toDateString(),
                'paid_date' => $paidDate,
                'days_overdue' => $daysOverdue,
                'status' => $validated['status'] === 'paid' ? 'paid' : ($daysOverdue > 0 ? 'overdue' : 'pending'),
            ]);
        }, 3);
    }

    /**
     * Record money received from a tenant as a Full, Partial, or Advance payment.
     * The month being paid is picked automatically (see targetMonth()); rent is due on the move-in day.
     *  - Full:    pays the remaining balance of the oldest unpaid month exactly.
     *  - Partial: pays less than that balance; the rest stays outstanding for that month.
     *  - Advance: once nothing is owed up to this month, pays upcoming months in order.
     *             One ledger row per month, sharing one receipt.
     *
     * @return Collection<int, Payment>
     */
    public function recordPayment(array $validated): Collection
    {
        return DB::transaction(function () use ($validated) {
            $tenant = Tenant::whereKey($validated['tenant_id'])->lockForUpdate()->firstOrFail();
            $type = $validated['payment_type'];
            $amount = round((float) $validated['amount'], 2);
            $paidDate = Carbon::parse($validated['paid_date'])->startOfDay();
            $target = $this->targetMonth($tenant, $type);

            if ($target['month'] === null) {
                throw ValidationException::withMessages([$target['field'] => $target['reason']]);
            }

            $allocations = $type === Payment::TYPE_ADVANCE
                ? $this->allocateAdvance($tenant, $target['month'], $amount)
                : [$this->allocateSingleMonth($tenant, $target['month'], $amount, $type)];

            // One receipt per transaction; when it spans several ledger rows they get -1, -2, ... suffixes.
            $base = filled($validated['receipt_number'] ?? null)
                ? trim($validated['receipt_number'])
                : 'RCPT-'.strtoupper(uniqid());
            $count = 0;
            $nextReceipt = function () use ($base, &$count) {
                return $base.'-'.(++$count);
            };

            $rows = collect();
            foreach ($allocations as [$charge, $portion, $balance]) {
                $rows = $rows->merge($this->applyToMonth($tenant, $charge, $balance, $portion, $type, $paidDate, $nextReceipt));
            }
            if ($rows->count() === 1 && $rows->first()->receipt_number === $base.'-1') {
                $rows->first()->update(['receipt_number' => $base]);
            }
            $used = $rows->pluck('receipt_number');
            if (Payment::whereIn('receipt_number', $used)->whereNotIn('id', $rows->pluck('id'))->exists()) {
                throw ValidationException::withMessages(['receipt_number' => "Receipt number {$base} is already used on another payment."]);
            }

            return $rows;
        }, 3);
    }

    /**
     * Which month a payment of this type goes to, counting from the tenant's billing start:
     * Full/Partial → the oldest month (up to this month) that still has a balance;
     * Advance      → the first upcoming month with rent, but only when nothing is owed up to this month.
     *
     * @return array{month: ?Carbon, field: string, reason: ?string}
     */
    public function targetMonth(Tenant $tenant, string $type): array
    {
        $start = $this->policy->tenantStart($tenant);
        if ($start === null) {
            return ['month' => null, 'field' => 'tenant_id', 'reason' => 'This tenant has not moved in yet, so no rent is due.'];
        }

        $current = Carbon::parse(Tenant::localDate())->startOfMonth();
        $oldestUnpaid = null;
        for ($month = $start->copy()->startOfMonth(); $month->lte($current); $month->addMonth()) {
            if ($this->balances->monthBalance($tenant, $month)['remaining'] > 0) {
                $oldestUnpaid = $month->copy();
                break;
            }
        }

        if ($type !== Payment::TYPE_ADVANCE) {
            return $oldestUnpaid
                ? ['month' => $oldestUnpaid, 'field' => 'payment_type', 'reason' => null]
                : ['month' => null, 'field' => 'payment_type', 'reason' => 'Nothing is due right now; rent is paid up to this month. Choose Advance to pay ahead.'];
        }

        if ($oldestUnpaid) {
            return ['month' => null, 'field' => 'payment_type', 'reason' => 'This tenant still owes rent for '.$oldestUnpaid->format('F Y').'. Record a Full or Partial payment for it before paying in advance.'];
        }

        for ($i = 1; $i <= Payment::MAX_ADVANCE_MONTHS; $i++) {
            $month = $current->copy()->addMonthsNoOverflow($i);
            if ($this->balances->monthBalance($tenant, $month)['remaining'] > 0) {
                return ['month' => $month, 'field' => 'payment_type', 'reason' => null];
            }
        }

        return ['month' => null, 'field' => 'payment_type', 'reason' => 'Rent is already paid for the next '.Payment::MAX_ADVANCE_MONTHS.' months.'];
    }

    /**
     * Put money against one month: settle recorded unpaid charges first (oldest first, splitting
     * one if the money runs out), then cover the not-yet-recorded part of the month's charge.
     *
     * @return Collection<int, Payment> the paid rows created or updated
     */
    private function applyToMonth(Tenant $tenant, ?Payment $charge, array $balance, float $amount, string $type, Carbon $paidDate, callable $nextReceipt): Collection
    {
        $rows = collect();
        $left = $amount;
        $paidFields = fn (Carbon $due) => [
            'paid_date' => $paidDate->toDateString(),
            'days_overdue' => (int) max(0, $due->copy()->startOfDay()->diffInDays($paidDate, false)),
            'status' => 'paid',
            'payment_type' => $type,
        ];

        foreach ($balance['unpaid_rows'] as $row) {
            if ($left <= 0.004) {
                break;
            }
            $row = Payment::whereKey($row->id)->lockForUpdate()->firstOrFail();
            if ($left + 0.004 >= (float) $row->amount) {
                $row->update([...$paidFields($row->due_date), 'receipt_number' => $row->receipt_number ?? $nextReceipt()]);
                $left = round($left - (float) $row->amount, 2);
            } else {
                // Split: the unpaid charge shrinks, and the collected portion becomes its own paid row.
                $row->update(['amount' => round((float) $row->amount - $left, 2)]);
                $rows->push($tenant->payments()->create([
                    ...$paidFields($row->due_date),
                    'receipt_number' => $nextReceipt(),
                    'charge_type' => $row->charge_type,
                    'amount' => $left,
                    'due_date' => $row->due_date->toDateString(),
                ]));
                $left = 0;
                continue;
            }
            $rows->push($row->fresh());
        }

        if ($left > 0.004 && $charge !== null) {
            $rows->push($tenant->payments()->create([
                ...$paidFields($charge->due_date),
                'receipt_number' => $nextReceipt(),
                'charge_type' => $charge->charge_type === Payment::STORAGE ? Payment::STORAGE : null,
                'amount' => round($left, 2),
                'due_date' => $charge->due_date->toDateString(),
            ]));
        }

        $tenant->unsetRelation('payments');

        return $rows;
    }

    /** @return array{0: ?Payment, 1: float, 2: array} */
    private function allocateSingleMonth(Tenant $tenant, Carbon $month, float $amount, string $type): array
    {
        $balance = $this->balances->monthBalance($tenant, $month);
        $label = $month->format('F Y');

        if ($balance['charge'] === null && ! $balance['has_unpaid_record']) {
            throw ValidationException::withMessages(['payment_type' => "No rent is billed to this tenant for {$label}. Check the tenant’s room assignment."]);
        }
        if ($balance['remaining'] <= 0) {
            throw ValidationException::withMessages(['payment_type' => "{$label} is already fully paid."]);
        }

        $remaining = number_format($balance['remaining'], 2);
        if ($type === Payment::TYPE_FULL && abs($amount - $balance['remaining']) > 0.004) {
            throw ValidationException::withMessages(['amount' => "A full payment for {$label} must be exactly PHP {$remaining}. Choose Partial for a smaller amount."]);
        }
        if ($type === Payment::TYPE_PARTIAL) {
            if ($balance['storage']) {
                throw ValidationException::withMessages(['payment_type' => "The {$label} storage fee must be paid in full."]);
            }
            if ($amount >= $balance['remaining']) {
                throw ValidationException::withMessages(['amount' => "A partial payment must be less than the PHP {$remaining} balance for {$label}. Choose Full to pay it all."]);
            }
        }

        return [$balance['charge'], $amount, $balance];
    }

    /** @return list<array{0: ?Payment, 1: float, 2: array}> */
    private function allocateAdvance(Tenant $tenant, Carbon $month, float $amount): array
    {
        $left = $amount;
        $allocations = [];

        for ($i = 0; $i < Payment::MAX_ADVANCE_MONTHS && $left > 0.004; $i++) {
            $current = $month->copy()->addMonthsNoOverflow($i);
            $balance = $this->balances->monthBalance($tenant, $current);
            if (($balance['charge'] === null && ! $balance['has_unpaid_record']) || $balance['remaining'] <= 0) {
                continue;
            }

            $portion = round(min($left, $balance['remaining']), 2);
            if ($balance['storage'] && $portion < $balance['remaining']) {
                break; // Storage fees cannot be part-paid; leave the rest unapplied.
            }

            $allocations[] = [$balance['charge'], $portion, $balance];
            $left = round($left - $portion, 2);
        }

        if ($allocations === []) {
            throw ValidationException::withMessages(['payment_type' => 'There is no upcoming rent to pay in advance from '.$month->format('F Y').'.']);
        }
        if ($left > 0.004) {
            throw ValidationException::withMessages([
                'amount' => sprintf('PHP %s is more than the rent for the next %d months from %s. Reduce the amount.', number_format($amount, 2), Payment::MAX_ADVANCE_MONTHS, $month->format('F Y')),
            ]);
        }

        return $allocations;
    }

    public function settleAccrued(Tenant $tenant, string $type, string $dueDate, string $paidDate, ?string $receipt = null): void
    {
        DB::transaction(function () use ($tenant, $type, $dueDate, $paidDate, $receipt) {
            $tenant = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $charge = $this->balances->billingEntries($tenant, $dueDate, $dueDate, specialOnly: true)
                ->firstWhere('charge_type', $type);

            if ($charge === null) {
                return;
            }

            $charge->save();
            $this->markPaid($charge, $paidDate, $receipt);
        }, 3);
    }

    public function settle(Payment $payment, string $paidDate, ?string $receipt = null): Payment
    {
        return DB::transaction(function () use ($payment, $paidDate, $receipt) {
            Tenant::whereKey($payment->tenant_id)->lockForUpdate()->firstOrFail();
            $entry = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            return $this->markPaid($entry, $paidDate, $receipt);
        }, 3);
    }

    private function markPaid(Payment $entry, string $paidDate, ?string $receipt = null): Payment
    {
        if ($entry->hasRecordedPayment()) {
            return $entry;
        }

        $receiptNumber = filled($receipt) ? trim($receipt) : ($entry->receipt_number ?? 'RCPT-' . strtoupper(uniqid()));

        $entry->update([
            'status' => 'paid',
            'paid_date' => $paidDate,
            'receipt_number' => $receiptNumber,
            'payment_type' => Payment::TYPE_FULL, // Mark paid collects the whole charge.
            'days_overdue' => (int) max(0, $entry->due_date->diffInDays(Carbon::parse($paidDate)->startOfDay(), false)),
        ]);

        app(NotificationService::class)->notify(
            $entry->tenant->user,
            'payment_confirmation',
            sprintf('Your payment of PHP %s due on %s has been recorded as paid. Receipt No: %s', number_format((float) $entry->amount, 2), $entry->due_date->format('M d, Y'), $receiptNumber)
        );

        return $entry;
    }
}
