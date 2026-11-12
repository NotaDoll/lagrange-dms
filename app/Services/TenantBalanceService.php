<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Tenant;
use App\Models\TenantMonthMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TenantBalanceService
{
    public function __construct(private readonly BillingPolicy $policy) {}

    /** Read actual ledger entries plus the unrecorded portion of accrued monthly charges. */
    public function billingEntries(Tenant $tenant, string $dateFrom, string $dateTo, bool $specialOnly = false): Collection
    {
        $tenant->loadMissing(['roomAssignments.bed.room', 'payments', 'monthModes']);
        $from = Carbon::parse($dateFrom);
        $to = Carbon::parse($dateTo);
        $today = Carbon::parse(Tenant::localDate());
        $entries = $specialOnly ? collect() : $tenant->payments
            ->filter(fn ($entry) => $entry->due_date->betweenIncluded($from, $to))->values();
        $start = $this->policy->tenantStart($tenant);

        if ($start === null) {
            return $entries;
        }

        $openingDate = $this->policy->startDate();
        $openingRecorded = $tenant->payments->where('charge_type', Payment::OPENING_BALANCE);
        $opening = (float) $tenant->opening_balance;
        if ($opening > 0 && $openingRecorded->isEmpty() && $openingDate->betweenIncluded($from, $to) && $openingDate->lte($today)) {
            $entries->push($this->computedEntry($tenant, $opening, $openingDate, Payment::OPENING_BALANCE));
        }

        $firstMonth = $start->copy()->startOfMonth()->max($from->copy()->startOfMonth());
        $lastDate = $to->copy()->min($today);
        $monthModes = $tenant->monthModes->keyBy(fn ($mode) => $mode->month->format('Y-m'));
        $recordedMonths = $tenant->payments->whereNull('utility_bill_id')
            ->where('charge_type', '!=', Payment::OPENING_BALANCE)
            ->groupBy(fn ($entry) => $entry->due_date->format('Y-m'));

        for ($month = $firstMonth->copy(); $month->lte($lastDate); $month->addMonth()) {
            $due = $month->copy()->endOfMonth()->startOfDay();
            if ($due->lt($from) || $due->gt($lastDate)) {
                continue;
            }

            $key = $month->format('Y-m');
            $recorded = $recordedMonths->get($key, collect());
            $mode = $monthModes->get($key);
            $storage = $mode?->mode === TenantMonthMode::STORAGE;
            if ($specialOnly && ! $storage) {
                continue;
            }

            $charge = $this->monthlyCharge($tenant, $month, $mode);
            if ($charge === null) {
                continue;
            }

            if ($recorded->contains(fn ($entry) => $entry->hasRecordedPayment() && ($entry->category === Payment::STORAGE || $entry->category !== $charge->category))) {
                continue;
            }
            $unrecorded = round($charge->amount - (float) $recorded->sum('amount'), 2);
            if ($unrecorded > 0) {
                $charge->amount = $unrecorded;
                $entries->push($charge);
            }
        }

        return $entries;
    }

    public function monthlyCharge(Tenant $tenant, Carbon $month, ?TenantMonthMode $mode): ?Payment
    {
        $start = $this->policy->tenantStart($tenant);
        $due = $month->copy()->endOfMonth()->startOfDay();
        if ($start === null || $start->gt($due)) {
            return null;
        }

        $periodStart = $month->copy()->max($start);
        $tenant->loadMissing('roomAssignments.bed.room');
        $assignment = $tenant->roomAssignments
            ->filter(fn ($item) => $item->start_date->lte($due)
                && (! $item->end_date || ($item->end_date->gte($item->start_date) && $item->end_date->gte($periodStart))))
            ->sort(fn ($left, $right) => [$right->start_date->getTimestamp(), $right->id] <=> [$left->start_date->getTimestamp(), $left->id])->first();
        if (! $assignment) {
            return null;
        }

        if ($mode?->mode === TenantMonthMode::STORAGE) {
            return $this->computedEntry($tenant, $mode->storageFee(), $due, Payment::STORAGE);
        }

        $rate = $assignment->bed?->room?->monthlyRateFor($month->format('Y-m')) ?? 0;
        $moveIn = $tenant->actualMoveInDate();
        if ($moveIn->equalTo($start) && $moveIn->isSameMonth($month) && $moveIn->day > 1) {
            $rate = round($rate * ($month->daysInMonth - $moveIn->day + 1) / $month->daysInMonth, 2);
        }

        return $this->computedEntry($tenant, $rate, $due, Payment::RENT);
    }

    private function computedEntry(Tenant $tenant, float $amount, Carbon $due, string $type): Payment
    {
        $today = Carbon::parse(Tenant::localDate());
        $payment = new Payment([
            'tenant_id' => $tenant->id,
            'charge_type' => $type,
            'amount' => $amount,
            'due_date' => $due->toDateString(),
            'status' => $due->lt($today) ? 'overdue' : 'pending',
            'days_overdue' => (int) max(0, $due->diffInDays($today, false)),
        ]);
        $payment->setRelation('tenant', $tenant);

        return $payment;
    }

    public function ledgerStart(Tenant $tenant): string
    {
        $tenant->loadMissing('payments');
        $start = $this->policy->startDate()->toDateString();

        return min($start, $tenant->payments->min('due_date')?->toDateString() ?? $start);
    }

    public function outstandingSummary(Tenant $tenant): array
    {
        $tenant->loadMissing('payments');
        $latestUtilityDue = $tenant->payments->whereNotNull('utility_bill_id')->max('due_date');
        $today = Carbon::parse(Tenant::localDate());
        $end = $latestUtilityDue && $latestUtilityDue->gt($today) ? $latestUtilityDue->toDateString() : $today->toDateString();
        $unpaid = $this->billingEntries($tenant, $this->ledgerStart($tenant), $end)
            ->filter(fn ($payment) => $payment->utility_bill_id || $payment->due_date->lte($today))
            ->whereIn('status', ['pending', 'overdue']);

        return [
            'outstanding_balance' => round((float) $unpaid->sum('amount'), 2),
            'unpaid_months' => $unpaid->where('charge_type', '!=', Payment::OPENING_BALANCE)
                ->map(fn ($payment) => $payment->due_date->format('Y-m'))->unique()->count(),
        ];
    }

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

        $month = Carbon::parse(Tenant::localDate())->startOfMonth();
        $tenant->loadMissing('monthModes');
        $mode = $tenant->monthModes->first(fn ($item) => $item->month->equalTo($month));
        $storage = $mode?->mode === TenantMonthMode::STORAGE && $this->policy->billableMonth($tenant, $month);

        return [
            'room' => $room->room_number,
            'bed' => $assignment->bed->bed_label,
            'location' => $assignment->bed->locationLabel(),
            'monthly_rate' => $storage ? $mode->storageFee() : (float) $room->monthly_rate,
            'label' => $storage ? 'Storage fee' : 'Rent',
            'effective_from' => $storage ? $month : $assignment->start_date,
            'due_date' => $month->copy()->endOfMonth(),
        ];
    }

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
