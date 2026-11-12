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
        $from = Carbon::parse($dateFrom);
        $to = Carbon::parse($dateTo);
        $entries = $tenant->payments->filter(fn ($entry) => $entry->due_date->betweenIncluded($from, $to))->values();

        if (! $tenant->hasMovedIn()) {
            return $entries;
        }

        $moveIn = $tenant->actualMoveInDate();
        $today = Carbon::parse(Tenant::localDate());
        $firstMonth = Carbon::parse(self::CLASS_START_DATE)->startOfMonth()
            ->max($from->copy()->startOfMonth());
        if ($moveIn) {
            $firstMonth = $firstMonth->max($moveIn->copy()->startOfMonth());
        }

        $lastDate = $to->min($today);
        for ($month = $firstMonth->copy(); $month->copy()->endOfMonth()->startOfDay()->lte($lastDate); $month->addMonth()) {
            $dueDate = $month->copy()->endOfMonth()->startOfDay();
            $periodStart = $moveIn ? $month->copy()->max($moveIn) : $month;
            $assignment = $tenant->roomAssignments
                ->filter(fn ($item) => $item->start_date->lte($dueDate->copy()->endOfDay())
                    && (! $item->end_date || ($item->end_date->gte($item->start_date) && $item->end_date->gte($periodStart))))
                ->sort(fn ($left, $right) => [$right->start_date->getTimestamp(), $right->id] <=> [$left->start_date->getTimestamp(), $left->id])->first();
            $rate = $assignment?->bed?->room?->monthlyRateFor($month->format('Y-m')) ?? 0;
            if ($moveIn && $moveIn->isSameMonth($month)) {
                $daysInMonth = $month->daysInMonth;
                $rate = round($rate * ($daysInMonth - $moveIn->day + 1) / $daysInMonth, 2);
            }
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
                    'status' => $dueDate->lt($today) ? 'overdue' : 'pending',
                    'days_overdue' => (int) max(0, $dueDate->diffInDays($today, false)),
                ]));
            }
        }

        return $entries;
    }

    /** Reconcile accrued monthly rent with recorded ledger charges without double counting. */
    public function outstandingSummary(Tenant $tenant): array
    {
        $tenant->loadMissing('payments');
        $earliest = $tenant->payments->min('due_date');
        $start = $earliest && $earliest->lt(Carbon::parse(self::CLASS_START_DATE))
            ? $earliest->toDateString() : self::CLASS_START_DATE;
        $latestUtilityDue = $tenant->payments->whereNotNull('utility_bill_id')->max('due_date');
        $today = Carbon::parse(Tenant::localDate());
        $end = $latestUtilityDue && $latestUtilityDue->gt($today) ? $latestUtilityDue->toDateString() : $today->toDateString();
        $unpaid = $this->billingEntries($tenant, $start, $end)
            ->filter(fn ($payment) => $payment->utility_bill_id || $payment->due_date->lte($today))
            ->whereIn('status', ['pending', 'overdue']);

        return [
            'outstanding_balance' => round((float) $unpaid->sum('amount'), 2),
            'unpaid_months' => $unpaid->map(fn ($payment) => $payment->due_date->format('Y-m'))->unique()->count(),
        ];
    }

    /** Current room terms are shown separately from debt accrued in earlier months. */
    public function currentRent(Tenant $tenant): ?array
    {
        if (! $tenant->hasMovedIn()) {
            return null;
        }

        $assignment = $tenant->currentAssignment()->with('bed.room')
            ->orderByDesc('start_date')->orderByDesc('id')->first();
        $room = $assignment?->bed?->room;
        if (! $room) {
            return null;
        }

        return [
            'room' => $room->room_number,
            'bed' => $assignment->bed->bed_label,
            'location' => $assignment->bed->locationLabel(),
            'monthly_rate' => (float) $room->monthly_rate,
            'effective_from' => $assignment->start_date,
            'due_date' => Carbon::parse(Tenant::localDate())->endOfMonth(),
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
