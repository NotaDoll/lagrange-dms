<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\NotificationService;
use App\Services\PaymentLedgerService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentLedgerService $paymentLedgerService,
        private readonly NotificationService $notificationService
    ) {
    }

    public function index(Request $request)
    {
        $tenantId = $request->query('tenant_id');
        $status = $request->query('status');

        $payments = Payment::with('tenant.user')
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('due_date')
            ->paginate(15)
            ->withQueryString();

        $tenants = Tenant::with('user')
            ->join('users', 'tenants.user_id', '=', 'users.id')
            ->orderBy('users.name')
            ->select('tenants.*')
            ->get();

        return view('proprietor.payments.index', compact('payments', 'tenants', 'tenantId', 'status'));
    }

    public function create()
    {
        $tenants = Tenant::with('user')
            ->join('users', 'tenants.user_id', '=', 'users.id')
            ->orderBy('users.name')
            ->select('tenants.*')
            ->get();

        return view('proprietor.payments.create', compact('tenants'));
    }

    public function store(StorePaymentRequest $request)
    {
        $payment = $this->paymentLedgerService->create($request->validated());

        if ($payment->status === 'paid') {
            $payment->load('tenant.user');

            $this->notificationService->notify(
                $payment->tenant->user,
                'payment_confirmation',
                sprintf(
                    'Your payment of PHP %s due on %s has been recorded as paid.',
                    number_format((float) $payment->amount, 2),
                    $payment->due_date->format('M d, Y')
                )
            );
        }

        return redirect()
            ->route('proprietor.payments.index')
            ->with('success', 'Payment recorded successfully.');
    }
}
