<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\TenantBalanceService;

class PaymentController extends Controller
{
    public function index(TenantBalanceService $balanceService)
    {
        $tenant = auth()->user()->tenant()->firstOrFail();
        $balance = $balanceService->summary($tenant);
        $rentTerms = $balanceService->currentRent($tenant);

        $outstandingBalance = $balance['outstanding_balance'];
        $unpaidMonths = $balance['unpaid_months'];
        $totalPaid = $balance['total_paid'];
        $paidCount = $balance['paid_count'];
        $paymentStatus = $outstandingBalance > 0 ? 'Outstanding' : 'Paid';

        $payments = Payment::with('utilityBill.room')
            ->where('tenant_id', $tenant->id)

            ->latest('due_date')
            ->paginate(15);

        return view('tenant.payments.index', compact(
            'payments',
            'rentTerms',
            'outstandingBalance',
            'unpaidMonths',
            'totalPaid',
            'paidCount',
            'paymentStatus'
        ));
    }
}
