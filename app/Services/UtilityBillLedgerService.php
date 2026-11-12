<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\TenantMonthMode;
use App\Models\UtilityBill;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UtilityBillLedgerService
{
    private const CHARGE_FIELDS = ['room_id', 'bill_type', 'other_description', 'billing_period_start', 'billing_period_end', 'amount', 'due_date'];

    public function __construct(private readonly BillingPolicy $policy) {}

    public function create(array $validated): UtilityBill
    {
        return DB::transaction(function () use ($validated) {
            Room::whereKey($validated['room_id'])->lockForUpdate()->firstOrFail();
            $bill = UtilityBill::create($this->attributes($validated));
            $this->allocate($bill);

            return $bill;
        });
    }

    public function update(UtilityBill $bill, array $validated): UtilityBill
    {
        return DB::transaction(function () use ($bill, $validated) {
            $bill = UtilityBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            $shares = $bill->payments()->lockForUpdate()->get();
            $bill->fill($this->attributes($validated));
            $changed = $bill->isDirty(self::CHARGE_FIELDS);
            if ($changed && $shares->contains(fn ($entry) => $entry->hasRecordedPayment())) {
                throw ValidationException::withMessages(['amount' => 'This bill has collected tenant payments. Its room, type, period, amount and due date cannot be changed. Provider status and notes can still be updated.']);
            }
            $bill->save();
            if ($changed) {
                Room::whereKey($bill->room_id)->lockForUpdate()->firstOrFail();
                $bill->payments()->delete();
                $this->allocate($bill);
            }

            return $bill;
        });
    }

    public function delete(UtilityBill $bill): void
    {
        DB::transaction(function () use ($bill) {
            $bill = UtilityBill::whereKey($bill->id)->lockForUpdate()->firstOrFail();
            if ($bill->payments()->lockForUpdate()->get()->contains(fn ($entry) => $entry->hasRecordedPayment())) {
                throw ValidationException::withMessages(['bill' => 'A bill with collected tenant payments cannot be deleted.']);
            }
            $bill->payments()->delete();
            $bill->delete();
        });
    }

    private function allocate(UtilityBill $bill): void
    {
        $from = $bill->billing_period_start->copy()->max($this->policy->startDate());
        $to = $bill->billing_period_end;
        if ($from->gt($to)) {
            throw ValidationException::withMessages(['billing_period_end' => 'The utility period ends before the billing start date.']);
        }
        if ($bill->due_date->lt($this->policy->startDate())) {
            throw ValidationException::withMessages(['due_date' => 'The due date must be on or after the billing start date.']);
        }

        $assignments = RoomAssignment::billable()->with(['tenant.monthModes'])
            ->whereHas('bed', fn ($query) => $query->where('room_id', $bill->room_id))
            ->whereHas('tenant', fn ($query) => $query->whereDate('move_in_date', '<=', $to))
            ->whereDate('start_date', '<=', $to)
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $from))
            ->orderBy('tenant_id')->get();
        $assignments = $assignments->filter(fn ($assignment) => $this->overlaps($assignment, $from, $to));
        if ($assignments->isEmpty()) {
            throw ValidationException::withMessages(['room_id' => 'No tenants were assigned to this room during the billing period. Choose an occupied room or adjust the period.']);
        }

        $cents = (int) round((float) $bill->amount * 100);
        $shares = $this->shares($assignments, $from, $to, $cents);
        foreach ($shares as $tenantId => $share) {
            $bill->payments()->create([
                'tenant_id' => $tenantId,
                'amount' => $share / 100,
                'due_date' => $bill->due_date->toDateString(),
                'status' => $bill->due_date->lt(today()) ? 'overdue' : 'pending',
                'days_overdue' => (int) max(0, $bill->due_date->diffInDays(today(), false)),
            ]);
        }
    }

    private function overlaps(RoomAssignment $assignment, Carbon $from, Carbon $to): bool
    {
        $start = $from->copy()->max($assignment->start_date)->max($assignment->tenant->actualMoveInDate());
        $end = $assignment->end_date ? $to->copy()->min($assignment->end_date) : $to;

        return $start->lte($end);
    }

    private function shares(Collection $assignments, Carbon $from, Carbon $to, int $cents): array
    {
        $storage = [];
        if (config('dorm.storage_skips_utilities')) {
            foreach ($assignments as $assignment) {
                foreach ($assignment->tenant->monthModes->where('mode', TenantMonthMode::STORAGE) as $mode) {
                    $start = $mode->month->copy()->max($from);
                    $end = $mode->month->copy()->endOfMonth()->startOfDay()->min($to);
                    if ($start->lte($end) && $this->overlaps($assignment, $start, $end)) {
                        $storage[$mode->month->format('Y-m')][$assignment->tenant_id] = true;
                    }
                }
            }
        }

        if ($storage === []) {
            return $this->split($cents, $assignments->pluck('tenant_id')->unique()->values()->all());
        }

        // Divide a mixed-period bill by calendar days, then split each month among regular occupants.
        $totalDays = (int) $from->diffInDays($to) + 1;
        $elapsedDays = 0;
        $shares = [];
        for ($start = $from->copy(); $start->lte($to); $start = $end->copy()->addDay()) {
            $end = $start->copy()->endOfMonth()->startOfDay()->min($to);
            $days = (int) $start->diffInDays($end) + 1;
            $monthCents = intdiv($cents * ($elapsedDays + $days), $totalDays)
                - intdiv($cents * $elapsedDays, $totalDays);
            $elapsedDays += $days;
            $skip = $storage[$start->format('Y-m')] ?? [];
            $tenantIds = $assignments->filter(fn ($assignment) => ! isset($skip[$assignment->tenant_id])
                && $this->overlaps($assignment, $start, $end))->pluck('tenant_id')->unique()->values()->all();
            foreach ($this->split($monthCents, $tenantIds) as $tenantId => $share) {
                $shares[$tenantId] = ($shares[$tenantId] ?? 0) + $share;
            }
        }

        return $shares;
    }

    private function split(int $cents, array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }
        if ($cents < count($tenantIds)) {
            throw ValidationException::withMessages(['amount' => 'The amount must allow at least ₱0.01 for each assigned tenant.']);
        }

        $base = intdiv($cents, count($tenantIds));
        $remainder = $cents % count($tenantIds);
        $shares = [];
        foreach ($tenantIds as $index => $id) {
            $shares[$id] = $base + ($index < $remainder ? 1 : 0);
        }

        return $shares;
    }

    private function attributes(array $validated): array
    {
        $due = Carbon::parse($validated['due_date'])->startOfDay();
        $paid = $validated['status'] === 'paid';
        $end = $paid ? Carbon::parse($validated['paid_date'])->startOfDay() : today();

        return [
            ...$validated,
            'other_description' => $validated['bill_type'] === 'other' ? ($validated['other_description'] ?? null) : null,
            'status' => $paid ? 'paid' : ($due->lt(today()) ? 'overdue' : 'pending'),
            'paid_date' => $paid ? $validated['paid_date'] : null,
            'days_overdue' => (int) max(0, $due->diffInDays($end, false)),
        ];
    }
}
