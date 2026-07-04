<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Carbon;

class PaymentLedgerService
{
    public function create(array $validated): Payment
    {
        return Payment::create([
            'tenant_id' => $validated['tenant_id'],
            'amount' => $validated['amount'],
            'due_date' => $validated['due_date'],
            'paid_date' => $validated['paid_date'] ?? null,
            'days_overdue' => $this->calculateDaysOverdue($validated),
            'status' => $validated['status'],
        ]);
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
