<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomAssignment;
use App\Models\UtilityBill;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UtilityBillLedgerService
{
    private const CHARGE_FIELDS = ['room_id', 'bill_type', 'other_description', 'billing_period_start', 'billing_period_end', 'amount', 'due_date'];

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
            if ($changed && $shares->contains('status', 'paid')) {
                throw ValidationException::withMessages(['amount' => 'This bill has collected tenant payments. Its room, type, period, amount and due date cannot be changed. Provider status and notes can still be updated.']);
            }
            $bill->save();
            if ($changed || $shares->isEmpty()) {
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
            if ($bill->payments()->lockForUpdate()->get()->contains('status', 'paid')) {
                throw ValidationException::withMessages(['bill' => 'A bill with collected tenant payments cannot be deleted.']);
            }
            $bill->payments()->delete();
            $bill->delete();
        });
    }

    private function allocate(UtilityBill $bill): void
    {
        $tenantIds = RoomAssignment::whereHas('bed', fn ($query) => $query->where('room_id', $bill->room_id))
            ->whereDate('start_date', '<=', $bill->billing_period_end)
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $bill->billing_period_start))
            ->orderBy('tenant_id')->pluck('tenant_id')->unique()->values();
        if ($tenantIds->isEmpty()) {
            throw ValidationException::withMessages(['room_id' => 'No tenants were assigned to this room during the billing period. Choose an occupied room or adjust the period.']);
        }
        $cents = (int) round((float) $bill->amount * 100);
        if ($cents < $tenantIds->count()) {
            throw ValidationException::withMessages(['amount' => 'The amount must allow at least ₱0.01 for each assigned tenant.']);
        }
        $base = intdiv($cents, $tenantIds->count());
        $remainder = $cents % $tenantIds->count();
        foreach ($tenantIds as $index => $tenantId) {
            $bill->payments()->create([
                'tenant_id' => $tenantId,
                'amount' => ($base + ($index < $remainder ? 1 : 0)) / 100,
                'due_date' => $bill->due_date->toDateString(),
                'status' => $bill->due_date->lt(today()) ? 'overdue' : 'pending',
                'days_overdue' => (int) max(0, $bill->due_date->diffInDays(today(), false)),
            ]);
        }
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
