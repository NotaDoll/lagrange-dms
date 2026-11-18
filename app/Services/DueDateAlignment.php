<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One-off clean-up for rows recorded before rent was billed on the move-in day
 * (e.g. the Excel import put every due date on the 30th). Each rent/storage row keeps
 * its month but moves to the tenant's move-in day; days late are recalculated for paid rows.
 * Utility shares and opening balances keep their own dates.
 */
class DueDateAlignment
{
    public function __construct(private readonly BillingPolicy $policy) {}

    /**
     * @return list<array{id: int, tenant: string, from: string, to: string, status: string, days_from: int, days_to: int}>
     */
    public function plan(): array
    {
        $changes = [];

        Payment::query()
            ->whereNull('utility_bill_id')
            ->where(fn ($query) => $query->whereNull('charge_type')->orWhere('charge_type', '!=', Payment::OPENING_BALANCE))
            ->with('tenant.user')
            ->orderBy('id')
            ->each(function (Payment $payment) use (&$changes) {
                $tenant = $payment->tenant;
                if (! $tenant instanceof Tenant || $payment->due_date === null) {
                    return;
                }

                $due = $this->policy->dueDate($tenant, $payment->due_date);
                if ($due === null || $due->isSameDay($payment->due_date)) {
                    return;
                }

                $days = $payment->hasRecordedPayment() && $payment->paid_date
                    ? (int) max(0, $due->diffInDays($payment->paid_date->copy()->startOfDay(), false))
                    : (int) $payment->days_overdue;

                $changes[] = [
                    'id' => $payment->id,
                    'tenant' => (string) $tenant->name,
                    'from' => $payment->due_date->toDateString(),
                    'to' => $due->toDateString(),
                    'status' => $payment->status,
                    'days_from' => (int) $payment->days_overdue,
                    'days_to' => $days,
                ];
            });

        return $changes;
    }

    /** Apply a plan from plan(). Unpaid rows get their stored status refreshed from the new date. */
    public function apply(array $changes): int
    {
        $today = Tenant::localDate();

        return DB::transaction(function () use ($changes, $today) {
            foreach ($changes as $change) {
                $payment = Payment::whereKey($change['id'])->lockForUpdate()->firstOrFail();
                $due = Carbon::parse($change['to']);
                $attributes = ['due_date' => $change['to'], 'days_overdue' => $change['days_to']];

                if (! $payment->hasRecordedPayment()) {
                    $attributes['status'] = $due->lt($today) ? 'overdue' : 'pending';
                    $attributes['days_overdue'] = (int) max(0, $due->diffInDays($today, false));
                }

                $payment->update($attributes);
            }

            return count($changes);
        });
    }
}
