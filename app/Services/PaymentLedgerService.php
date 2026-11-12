<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantMonthMode;
use Illuminate\Support\Carbon;
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

            return $tenant->payments()->create([
                'charge_type' => $charge?->charge_type,
                'amount' => $validated['amount'],
                'due_date' => $dueDate->toDateString(),
                'paid_date' => $paidDate,
                'days_overdue' => $daysOverdue,
                'status' => $validated['status'] === 'paid' ? 'paid' : ($daysOverdue > 0 ? 'overdue' : 'pending'),
            ]);
        }, 3);
    }

    public function settleAccrued(Tenant $tenant, string $type, string $dueDate, string $paidDate): void
    {
        DB::transaction(function () use ($tenant, $type, $dueDate, $paidDate) {
            $tenant = Tenant::whereKey($tenant->id)->lockForUpdate()->firstOrFail();
            $charge = $this->balances->billingEntries($tenant, $dueDate, $dueDate, specialOnly: true)
                ->firstWhere('charge_type', $type);

            if ($charge === null) {
                return;
            }

            $charge->save();
            $this->markPaid($charge, $paidDate);
        }, 3);
    }

    public function settle(Payment $payment, string $paidDate): Payment
    {
        return DB::transaction(function () use ($payment, $paidDate) {
            Tenant::whereKey($payment->tenant_id)->lockForUpdate()->firstOrFail();
            $entry = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            return $this->markPaid($entry, $paidDate);
        }, 3);
    }

    private function markPaid(Payment $entry, string $paidDate): Payment
    {
        if ($entry->hasRecordedPayment()) {
            return $entry;
        }

        $entry->update([
            'status' => 'paid',
            'paid_date' => $paidDate,
            'days_overdue' => (int) max(0, $entry->due_date->diffInDays(Carbon::parse($paidDate)->startOfDay(), false)),
        ]);
        app(NotificationService::class)->notify(
            $entry->tenant->user,
            'payment_confirmation',
            sprintf('Your payment of PHP %s due on %s has been recorded as paid.', number_format((float) $entry->amount, 2), $entry->due_date->format('M d, Y'))
        );

        return $entry;
    }
}
