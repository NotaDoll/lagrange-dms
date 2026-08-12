<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Payment;

class PaymentController extends Controller
{
    public function index()
    {
        $tenant = auth()->user()->tenant()->firstOrFail();

        $paymentQuery = Payment::where('tenant_id', $tenant->id);

        $outstandingBalance = (clone $paymentQuery)
            ->whereIn('status', ['pending', 'overdue'])
            ->sum('amount');

        $outstandingCount = (clone $paymentQuery)
            ->whereIn('status', ['pending', 'overdue'])
            ->count();

        $totalPaid = (clone $paymentQuery)
            ->where('status', 'paid')
            ->sum('amount');

        $paidCount = (clone $paymentQuery)
            ->where('status', 'paid')
            ->count();

        $paymentStatus = (clone $paymentQuery)->where('status', 'overdue')->exists()
            ? 'Overdue'
            : ((clone $paymentQuery)->where('status', 'pending')->exists() ? 'Pending' : 'Paid');

        $payments = (clone $paymentQuery)
            ->latest('due_date')
            ->paginate(15);

        return view('tenant.payments.index', compact(
            'payments',
            'outstandingBalance',
            'outstandingCount',
            'totalPaid',
            'paidCount',
            'paymentStatus'
        ));
    }
}
