<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\TenantBalanceService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentController extends Controller
{
    public function index(Request $request, TenantBalanceService $balanceService)
    {
        $tenant = auth()->user()->tenant()->firstOrFail();
        $tenant->load('payments.utilityBill.room');
        $balance = $balanceService->summary($tenant);
        $rentTerms = $balanceService->currentRent($tenant);

        $outstandingBalance = $balance['outstanding_balance'];
        $unpaidMonths = $balance['unpaid_months'];
        $totalPaid = $balance['total_paid'];
        $paidCount = $balance['paid_count'];
        $advancePayment = (float) $tenant->advance_payment;
        $paymentStatus = $outstandingBalance > 0 ? 'Outstanding' : 'Paid';

        $rows = $balanceService->ledger($tenant);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $payments = new LengthAwarePaginator($rows->forPage($page, 15), $rows->count(), 15, $page, ['path' => $request->url()]);

        return view('tenant.payments.index', compact(
            'payments',
            'rentTerms',
            'outstandingBalance',
            'unpaidMonths',
            'totalPaid',
            'paidCount',
            'advancePayment',
            'paymentStatus'
        ));
    }
}
