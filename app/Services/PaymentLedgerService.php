<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentLedgerService
{
    public function create(array $validated): Payment
    {
        $tenant = Tenant::findOrFail($validated['tenant_id']);
        $dueDate = Carbon::parse($validated['due_date'])->startOfDay();

        if (! $tenant->hasMovedIn()) {
            throw ValidationException::withMessages(['tenant_id' => 'Billing starts on the tenant’s move-in date.']);
        }
        if ($tenant->move_in_date && $dueDate->lt($tenant->move_in_date)) {
            throw ValidationException::withMessages(['due_date' => 'The due date must be on or after the tenant’s move-in date.']);
        }
        $paidDate = $validated['status'] === 'paid' ? $validated['paid_date'] : null;
        $endDate = $paidDate ? Carbon::parse($paidDate)->startOfDay() : Carbon::today();
        $daysOverdue = (int) max(0, $dueDate->diffInDays($endDate, false));

        return Payment::create([
            'tenant_id' => $validated['tenant_id'],
            'amount' => $validated['amount'],
            'due_date' => $validated['due_date'],
            'paid_date' => $paidDate,
            'days_overdue' => $daysOverdue,
            'status' => $validated['status'] === 'paid' ? 'paid' : ($daysOverdue > 0 ? 'overdue' : 'pending'),
        ]);
    }

    public function settle(Payment $payment, string $paidDate): Payment
    {
        return DB::transaction(function () use ($payment, $paidDate) {
            $entry = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            // Retried submissions must not duplicate a receipt or change its date.
            if ($entry->status === 'paid') {
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
        });
    }
}
