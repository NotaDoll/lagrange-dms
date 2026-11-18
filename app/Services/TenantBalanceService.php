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
    public function billingEntries(Tenant $tenant, string $dateFrom, string $dateTo, bool $specialOnly = false, ?Carbon $showFirstDue = null): Collection
    {
        if ($specialOnly || (float) $tenant->advance_payment <= 0) {
            return $this->baseBillingEntries($tenant, $dateFrom, $dateTo, $specialOnly, $showFirstDue);
        }

        $credits = $this->advanceCredits($tenant, Carbon::parse($dateTo));
        if ($credits === []) {
            return $this->baseBillingEntries($tenant, $dateFrom, $dateTo, $specialOnly, $showFirstDue);
        }

        // Allocate against complete due months before applying the report's exact date range.
        $from = Carbon::parse($dateFrom);
        $to = Carbon::parse($dateTo);
        $entries = $this->baseBillingEntries($tenant, $from->copy()->startOfMonth()->toDateString(), $to->copy()->endOfMonth()->toDateString(),
            showFirstDue: $showFirstDue);
        $hasCredit = fn (Payment $entry) => $entry->category === Payment::RENT && ! $entry->hasRecordedPayment()
            && isset($credits[$entry->due_date->format('Y-m')]);
        $unpaidRent = $entries->filter($hasCredit)
            ->sortBy(fn (Payment $entry) => $entry->id ?? PHP_INT_MAX);

        $adjusted = collect();
        foreach ($unpaidRent as $entry) {
            $key = $entry->due_date->format('Y-m');
            $credit = round(min($credits[$key], (float) $entry->amount), 2);
            $credits[$key] = round($credits[$key] - $credit, 2);
            if (! $entry->due_date->betweenIncluded($from, $to)) {
                continue;
            }

            if ($credit > 0) {
                $applied = $this->computedEntry($tenant, $credit, $entry->due_date, Payment::RENT);
                $applied->forceFill(['status' => 'paid', 'days_overdue' => 0, 'is_advance_credit' => true]);
                $adjusted->push($applied);
            }
            $remaining = round((float) $entry->amount - $credit, 2);
            if ($remaining > 0) {
                $remainingEntry = clone $entry;
                $remainingEntry->amount = $remaining;
                $adjusted->push($remainingEntry);
            }
        }

        return $entries->reject($hasCredit)->filter(fn (Payment $entry) => $entry->due_date->betweenIncluded($from, $to))
            ->concat($adjusted)->values();
    }

    private function baseBillingEntries(Tenant $tenant, string $dateFrom, string $dateTo, bool $specialOnly = false, ?Carbon $showFirstDue = null): Collection
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
        $lastDate = $to->copy()->min($showFirstDue ?? $today);
        $monthModes = $tenant->monthModes->keyBy(fn ($mode) => $mode->month->format('Y-m'));
        $recordedMonths = $tenant->payments->whereNull('utility_bill_id')
            ->where('charge_type', '!=', Payment::OPENING_BALANCE)
            ->groupBy(fn ($entry) => $entry->due_date->format('Y-m'));

        for ($month = $firstMonth->copy(); $month->lte($lastDate); $month->addMonth()) {
            $due = $this->policy->dueDate($tenant, $month);
            if ($due === null || $due->lt($start) || $due->lt($from) || $due->gt($lastDate)) {
                continue;
            }

            $key = $month->format('Y-m');
            $recorded = $recordedMonths->get($key, collect());
            $mode = $monthModes->get($key);
            $storage = $mode?->mode === TenantMonthMode::STORAGE;
            if ($due->gt($today) && ($storage || ! $showFirstDue?->isSameDay($due))) {
                continue;
            }
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

    /**
     * What a tenant owes for one month's rent or storage fee, and how much of it is already paid.
     * Utility shares and the opening balance are separate charges and are not included.
     *
     * @return array{charge: ?Payment, amount: float, paid: float, remaining: float, unrecorded: float, unpaid_rows: Collection, has_unpaid_record: bool, storage: bool, advance_applied: float}
     */
    public function monthBalance(Tenant $tenant, Carbon $month, bool $rentOnly = false): array
    {
        $balance = $this->grossMonthBalance($tenant, $month, $rentOnly);
        $credit = min($balance['remaining'], $this->advanceCredits($tenant, $month)[$month->format('Y-m')] ?? 0.0);
        $balance['advance_applied'] = $credit;
        $balance['remaining'] = round($balance['remaining'] - $credit, 2);
        $balance['unpaid_rows'] = $balance['unpaid_rows']->map(function (Payment $row) use (&$credit) {
            if ($row->category !== Payment::RENT || $credit <= 0) {
                return $row;
            }
            $applied = min($credit, (float) $row->amount);
            $credit = round($credit - $applied, 2);
            $net = clone $row;
            $net->amount = round((float) $row->amount - $applied, 2);

            return $net;
        })->filter(fn (Payment $row) => (float) $row->amount > 0)->values();
        $balance['has_unpaid_record'] = $balance['unpaid_rows']->isNotEmpty();
        $balance['unrecorded'] = round($balance['unrecorded'] - $credit, 2);

        return $balance;
    }

    private function advanceCredits(Tenant $tenant, Carbon $through): array
    {
        $left = (float) $tenant->advance_payment;
        $start = $this->policy->tenantStart($tenant);
        if ($left <= 0 || $start === null) {
            return [];
        }

        $credits = [];
        for ($month = $start->copy()->startOfMonth(); $left > 0 && $month->lte($through); $month->addMonth()) {
            $balance = $this->grossMonthBalance($tenant, $month);
            if ($balance['storage']) {
                continue;
            }
            $rent = (float) $balance['unpaid_rows']->filter(fn (Payment $row) => $row->category === Payment::RENT)->sum('amount')
                + $balance['unrecorded'];
            $credit = round(min($left, $rent), 2);
            if ($credit > 0) {
                $credits[$month->format('Y-m')] = $credit;
                $left = round($left - $credit, 2);
            }
        }

        return $credits;
    }

    private function grossMonthBalance(Tenant $tenant, Carbon $month, bool $rentOnly = false): array
    {
        $month = $month->copy()->startOfMonth();
        $tenant->loadMissing(['roomAssignments.bed.room', 'payments', 'monthModes']);
        $mode = $tenant->monthModes->first(fn ($item) => $item->month->isSameMonth($month));
        $charge = $this->monthlyCharge($tenant, $month, $mode);
        $recorded = $tenant->payments->whereNull('utility_bill_id')
            ->filter(fn (Payment $entry) => $entry->charge_type !== Payment::OPENING_BALANCE && $entry->due_date->isSameMonth($month));
        if ($rentOnly) {
            if ($charge?->category !== Payment::RENT || $recorded->contains(fn (Payment $entry) => $entry->category === Payment::STORAGE && $entry->hasRecordedPayment())) {
                $charge = null;
            }
            $recorded = $recorded->where('category', Payment::RENT);
        }
        $unpaidRows = $recorded->reject->hasRecordedPayment()->sortBy('id')->values();
        $paid = round((float) $recorded->filter->hasRecordedPayment()->sum('amount'), 2);
        $unpaidRecorded = round((float) $unpaidRows->sum('amount'), 2);
        $amount = round((float) ($charge?->amount ?? 0), 2);
        // Same rule as billingEntries(): recorded rows (paid or not) cover the computed charge first;
        // whatever is left of it is still owed, plus any recorded charge not yet paid.
        $unrecorded = max(0, round($amount - (float) $recorded->sum('amount'), 2));
        if ($charge && $recorded->contains(fn (Payment $entry) => $entry->hasRecordedPayment()
            && ($charge->category === Payment::STORAGE || $entry->category === Payment::STORAGE || $entry->category !== $charge->category))) {
            $unrecorded = 0;
        }

        return [
            'charge' => $charge,
            'amount' => max($amount, round($paid + $unpaidRecorded, 2)),
            'paid' => $paid,
            'remaining' => round($unpaidRecorded + $unrecorded, 2),
            'unrecorded' => $unrecorded,
            'unpaid_rows' => $unpaidRows,
            'has_unpaid_record' => $unpaidRows->isNotEmpty(),
            'storage' => $charge?->charge_type === Payment::STORAGE,
        ];
    }

    public function monthlyCharge(Tenant $tenant, Carbon $month, ?TenantMonthMode $mode): ?Payment
    {
        $start = $this->policy->tenantStart($tenant);
        $due = $this->policy->dueDate($tenant, $month);
        if ($start === null || $due === null || $start->gt($due)) {
            return null;
        }

        $storage = $mode?->mode === TenantMonthMode::STORAGE;
        if (! $storage && $due->lt($this->policy->firstRentDueDate($tenant))) {
            return null;
        }

        // Rent is due at the end of the cycle and uses the room and rate at its start.
        $periodStart = $storage ? $due : $this->policy->dueDate($tenant, $month->copy()->startOfMonth()->subMonth());
        $tenant->loadMissing('roomAssignments.bed.room');
        $assignment = $tenant->roomAssignments
            ->filter(fn ($item) => $item->start_date->lte($periodStart)
                && (! $item->end_date || ($item->end_date->gte($item->start_date) && $item->end_date->gte($periodStart))))
            ->sort(fn ($left, $right) => [$right->start_date->getTimestamp(), $right->id] <=> [$left->start_date->getTimestamp(), $left->id])->first();
        if (! $assignment) {
            return null;
        }

        if ($storage) {
            return $this->computedEntry($tenant, $mode->storageFee(), $due, Payment::STORAGE);
        }

        // Billing runs from move-in day to move-in day, so every month (including the first) is a full month.
        $rate = $assignment->bed?->room?->monthlyRateFor($periodStart->format('Y-m')) ?? 0;

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

    public function ledger(Tenant $tenant): Collection
    {
        $tenant->loadMissing('payments');
        $lastRecorded = $tenant->payments->max('due_date')?->toDateString();
        $today = Tenant::localDate();
        $firstDue = $this->policy->firstRentDueDate($tenant);
        $upcoming = $tenant->hasMovedIn() && $firstDue?->gt($today) ? $firstDue : null;

        return $this->billingEntries($tenant, $this->ledgerStart($tenant), max($today, $lastRecorded ?? '', $upcoming?->toDateString() ?? ''),
            showFirstDue: $upcoming)
            ->sortByDesc(fn (Payment $entry) => $entry->due_date->toDateString())->values();
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
            'due_date' => $storage ? $this->policy->dueDate($tenant, $month)
                : $this->policy->dueDate($tenant, $month)->max($this->policy->firstRentDueDate($tenant)),
        ];
    }

    public function summary(Tenant $tenant): array
    {
        $balance = $this->outstandingSummary($tenant);
        $paid = $tenant->payments->where('status', 'paid');

        return [
            ...$balance,
            'total_paid' => round((float) $paid->sum('amount') + (float) $tenant->advance_payment, 2),
            'paid_count' => $paid->whereNull('receipt_reference')->count()
                + $paid->whereNotNull('receipt_reference')->pluck('receipt_reference')->unique()->count(),
        ];
    }
}
