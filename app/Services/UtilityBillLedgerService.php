<?php

namespace App\Services;

use App\Models\UtilityBill;
use Illuminate\Support\Carbon;

class UtilityBillLedgerService
{
    public function create(array $validated): UtilityBill
    {
        return UtilityBill::create([
            ...$validated,
            'days_overdue' => $this->calculateDaysOverdue($validated),
        ]);
    }

    public function update(UtilityBill $bill, array $validated): UtilityBill
    {
        $bill->update([
            ...$validated,
            'days_overdue' => $this->calculateDaysOverdue($validated),
        ]);

        return $bill;
    }

    private function calculateDaysOverdue(array $validated): int
    {
        if ($validated['status'] !== 'overdue') {
            return 0;
        }

        $dueDate = Carbon::parse($validated['due_date'])->startOfDay();
        $endDate = isset($validated['paid_date'])
            ? Carbon::parse($validated['paid_date'])->startOfDay()
            : Carbon::today();

        return max(0, $dueDate->diffInDays($endDate, false));
    }
}