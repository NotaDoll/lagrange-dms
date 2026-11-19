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
            if (! $charge) {
                $dueDate = $this->policy->dueDate($tenant, $month);
                if ($dueDate->lt($start) || $dueDate->lt($this->policy->firstRentDueDate($tenant))) {
                    throw ValidationException::withMessages(['due_date' => 'Rent is first due one month after move-in and follows the move-in day each month.']);
                }
            }
            if ($charge) {
                $existing = $tenant->payments()->whereNull('utility_bill_id')
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
                $receiptNumber = filled($validated['receipt_number'] ?? null)
                    ? trim((string) $validated['receipt_number'])
                    : 'RCPT-'.strtoupper(uniqid());
                if (Payment::where('receipt_number', $receiptNumber)->exists()) {
                    throw ValidationException::withMessages(['receipt_number' => "Receipt number {$receiptNumber} is already used on another payment."]);
                }
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

    /** Collect rent or a selected utility charge. Explicit payment types support the original workflow. */
    public function recordPayment(array $validated): Collection
    {
        return DB::transaction(function () use ($validated) {
            $tenant = Tenant::whereKey($validated['tenant_id'])->lockForUpdate()->firstOrFail();
            if (isset($validated['applies_to'])) {
                return $this->recordAllocatedPayment($tenant, $validated);
            }
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
            if (isset($validated['note'])) {
                $rows->each(fn (Payment $row) => $row->update(['note' => $validated['note']]));
            }

            return $rows;
        }, 3);
    }

    public function previewPayment(Tenant $tenant, float $amount): array
    {
        return collect($this->rentAllocations($tenant, $amount))->map(fn (array $allocation) => [
            'due_date' => $allocation['due']->format('M d, Y'),
            'amount' => $allocation['amount'],
            'remaining' => round($allocation['balance']['remaining'] - $allocation['amount'], 2),
            'upcoming' => $allocation['upcoming'],
        ])->all();
    }

    private function recordAllocatedPayment(Tenant $tenant, array $validated): Collection
    {
        $amount = round((float) $validated['amount'], 2);
        $paidDate = Carbon::parse($validated['paid_date'])->startOfDay();
        $receipt = trim($validated['receipt_number']);
        if (Payment::where('receipt_number', $receipt)->exists()) {
            throw ValidationException::withMessages(['receipt_number' => 'This receipt number is already used on another payment.']);
        }
        $note = $validated['note'] ?? null;

        if ($validated['applies_to'] === Payment::UTILITIES) {
            $share = $tenant->payments()->whereNotNull('utility_bill_id')
                ->whereKey($validated['utility_payment_id'])->lockForUpdate()->first();
            if (! $share || $share->hasRecordedPayment()) {
                throw ValidationException::withMessages(['utility_payment_id' => 'Choose an unpaid utility charge for this tenant.']);
            }
            if ((float) $share->amount !== $amount) {
                throw ValidationException::withMessages(['amount' => 'Enter the full balance of the selected utility charge.']);
            }
            $share->update([
                'receipt_number' => $receipt,
                'note' => $note,
                'paid_date' => $paidDate,
                'status' => 'paid',
                'payment_type' => Payment::TYPE_FULL,
                'days_overdue' => (int) max(0, $share->due_date->diffInDays($paidDate, false)),
            ]);

            return collect([$share]);
        }

        $allocations = $this->rentAllocations($tenant, $amount);
        if (! ($validated['apply_excess'] ?? false) && collect($allocations)->contains('upcoming', true)) {
            throw ValidationException::withMessages(['apply_excess' => 'Confirm that the excess should be applied to future rent.']);
        }

        $first = true;
        $nextReceipt = function () use ($receipt, &$first) {
            if (! $first) {
                return null;
            }
            $first = false;

            return $receipt;
        };

        $rows = collect();
        foreach ($allocations as $allocation) {
            $balance = $allocation['balance'];
            $portion = $allocation['amount'];
            $type = $allocation['upcoming'] ? Payment::TYPE_ADVANCE
                : ($portion < $balance['remaining'] ? Payment::TYPE_PARTIAL : Payment::TYPE_FULL);
            $rows = $rows->merge($this->applyToMonth(
                $tenant, $balance['charge'], $balance, $portion, $type, $paidDate, $nextReceipt, sharedReceipt: true
            ));
        }
        $rows->each(fn (Payment $row) => $row->update(['receipt_reference' => $receipt, 'note' => $note]));

        return $rows;
    }

    private function rentAllocations(Tenant $tenant, float $amount): array
    {
        $start = $this->policy->tenantStart($tenant);
        if (! $start) {
            throw ValidationException::withMessages(['tenant_id' => 'This tenant has not moved in yet, so no rent is due.']);
        }

        $today = Carbon::parse(Tenant::localDate());
        $lastMonth = $today->copy()->startOfMonth()->addMonthsNoOverflow(Payment::MAX_ADVANCE_MONTHS);
        $left = $amount;
        $allocations = [];
        for ($month = $start->copy()->startOfMonth(); $left > 0 && $month->lte($lastMonth); $month->addMonth()) {
            $balance = $this->balances->monthBalance($tenant, $month, rentOnly: true);
            if ($balance['remaining'] <= 0) {
                continue;
            }

            $due = $balance['unpaid_rows']->first()?->due_date ?? $balance['charge']->due_date;
            $portion = min($left, $balance['remaining']);
            $allocations[] = ['due' => $due, 'amount' => $portion, 'balance' => $balance, 'upcoming' => $due->gt($today)];
            $left = round($left - $portion, 2);
        }

        if ($left > 0) {
            throw ValidationException::withMessages(['amount' => 'The amount exceeds the rent available to pay. Check the tenant’s room assignment or reduce the payment amount.']);
        }

        return $allocations;
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
    private function applyToMonth(Tenant $tenant, ?Payment $charge, array $balance, float $amount, string $type, Carbon $paidDate, callable $nextReceipt, bool $sharedReceipt = false): Collection
    {
        $rows = collect();
        $left = $amount;
        $paidFields = fn (Carbon $due) => [
            'paid_date' => $paidDate->toDateString(),
            'days_overdue' => (int) max(0, $due->copy()->startOfDay()->diffInDays($paidDate, false)),
            'status' => 'paid',
            'payment_type' => $type,
        ];

        foreach ($balance['unpaid_rows'] as $payable) {
            if ($left <= 0.004) {
                break;
            }
            $row = Payment::whereKey($payable->id)->lockForUpdate()->firstOrFail();
            $portion = min($left, (float) $payable->amount);
            if ($portion + 0.004 >= (float) $row->amount) {
                $receipt = $sharedReceipt ? $nextReceipt() : ($row->receipt_number ?? $nextReceipt());
                $row->update([...$paidFields($row->due_date), 'receipt_number' => $receipt]);
                $rows->push($row->fresh());
            } else {
                // Leave any advance-covered portion on the source row and record only new cash.
                $row->update(['amount' => round((float) $row->amount - $portion, 2)]);
                $rows->push($tenant->payments()->create([
                    ...$paidFields($row->due_date),
                    'receipt_number' => $nextReceipt(),
                    'charge_type' => $row->charge_type,
                    'amount' => $portion,
                    'due_date' => $row->due_date->toDateString(),
                ]));
            }
            $left = round($left - $portion, 2);
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
            $tenant = Tenant::whereKey($payment->tenant_id)->lockForUpdate()->firstOrFail();
            $entry = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $entry->hasRecordedPayment() && $entry->category === Payment::RENT && (float) $tenant->advance_payment > 0) {
                $payable = $this->balances->monthBalance($tenant, $entry->due_date)['unpaid_rows']->firstWhere('id', $entry->id);
                if ($payable === null) {
                    throw ValidationException::withMessages(['receipt_number' => 'This charge is already covered by the advance payment.']);
                }
                if ((float) $payable->amount < (float) $entry->amount) {
                    $entry->update(['amount' => round((float) $entry->amount - (float) $payable->amount, 2)]);
                    $entry = $tenant->payments()->create([
                        'charge_type' => $entry->charge_type,
                        'amount' => $payable->amount,
                        'due_date' => $entry->due_date,
                        'status' => 'pending',
                        'days_overdue' => 0,
                    ]);
                }
            }

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
