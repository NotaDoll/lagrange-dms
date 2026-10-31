<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TenantBalanceService
{
    private const CLASS_START_DATE = '2026-08-01';

    /** Read actual ledger entries plus the unrecorded portion of accrued rent. */
    public function billingEntries(Tenant $tenant, string $dateFrom, string $dateTo): Collection
    {
        $tenant->loadMissing(['roomAssignments.bed.room', 'payments']);
        $entries = collect($tenant->payments->all());
        $firstMonth = Carbon::parse(self::CLASS_START_DATE)->startOfMonth();
        if ($tenant->move_in_date && $tenant->move_in_date->gt($firstMonth)) {
            $firstMonth = $tenant->move_in_date->copy()->startOfMonth();
        }
        $firstMonth = $firstMonth->max(Carbon::parse($dateFrom)->startOfMonth());
        $lastDate = Carbon::parse($dateTo)->min(today());
        for ($month = $firstMonth->copy(); $month->copy()->endOfMonth()->startOfDay()->lte($lastDate); $month->addMonth()) {
            $dueDate = $month->copy()->endOfMonth()->startOfDay();
            $assignment = $tenant->roomAssignments
                ->filter(fn ($item) => $item->start_date->lte($dueDate->copy()->endOfDay())
                    && (! $item->end_date || $item->end_date->gte($month)))
                ->sort(fn ($left, $right) => [$right->start_date->getTimestamp(), $right->id] <=> [$left->start_date->getTimestamp(), $left->id])->first();
            $rate = (float) ($assignment?->bed?->room?->monthly_rate ?? 0);
            $recorded = (float) $tenant->payments
                ->filter(fn ($payment) => ! $payment->utility_bill_id && $payment->due_date->format('Y-m') === $month->format('Y-m'))->sum('amount');
            $unrecorded = round(max(0, $rate - $recorded), 2);
            if ($unrecorded > 0) {
                // A computed report entry, never inserted into the payment ledger.
                $entries->push(new Payment([
                    'tenant_id' => $tenant->id,
                    'amount' => $unrecorded,
                    'due_date' => $dueDate->toDateString(),
                    'paid_date' => null,
                    'status' => $dueDate->lt(today()) ? 'overdue' : 'pending',
                    'days_overdue' => (int) max(0, $dueDate->diffInDays(today(), false)),
                ]));
            }
        }

        return $entries->filter(fn ($entry) => $entry->due_date->gte(Carbon::parse($dateFrom))
            && $entry->due_date->lte(Carbon::parse($dateTo)))->values();
    }

    /** Reconcile accrued monthly rent with recorded ledger charges without double counting. */
    public function outstandingSummary(Tenant $tenant): array
    {
        $tenant->loadMissing('payments');
        $earliest = $tenant->payments->min('due_date');
        $start = $earliest && $earliest->lt(Carbon::parse(self::CLASS_START_DATE))
            ? $earliest->toDateString() : self::CLASS_START_DATE;
        $latestUtilityDue = $tenant->payments->whereNotNull('utility_bill_id')->max('due_date');
        $end = $latestUtilityDue && $latestUtilityDue->gt(today()) ? $latestUtilityDue->toDateString() : today()->toDateString();
        $unpaid = $this->billingEntries($tenant, $start, $end)
            ->filter(fn ($payment) => $payment->utility_bill_id || $payment->due_date->lte(today()))
            ->whereIn('status', ['pending', 'overdue']);

        return [
            'outstanding_balance' => round((float) $unpaid->sum('amount'), 2),
            'unpaid_months' => $unpaid->map(fn ($payment) => $payment->due_date->format('Y-m'))->unique()->count(),
        ];
    }

    /** Current room terms are shown separately from debt accrued in earlier months. */
    public function currentRent(Tenant $tenant): ?array
    {
        $assignment = $tenant->currentAssignment()->with('bed.room')
            ->orderByDesc('start_date')->orderByDesc('id')->first();
        $room = $assignment?->bed?->room;
        if (! $room) {
            return null;
        }

        return [
            'room' => $room->room_number,
            'bed' => $assignment->bed->bed_label,
            'monthly_rate' => (float) $room->monthly_rate,
            'effective_from' => $assignment->start_date,
            'due_date' => today()->endOfMonth(),
        ];
    }

    /** Unified tenant balance: accrued rent plus recorded utility shares. */
    public function summary(Tenant $tenant): array
    {
        $balance = $this->outstandingSummary($tenant);
        $paid = $tenant->payments->where('status', 'paid');

        return [
            ...$balance,
            'total_paid' => round((float) $paid->sum('amount'), 2),
            'paid_count' => $paid->count(),
        ];
    }
}
