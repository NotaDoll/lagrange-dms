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
    ) {}

    public function index(Request $request)
    {
        $tenantId = $request->query('tenant_id');
        $status = $request->query('status');
        $payments = Payment::with(['tenant.user', 'utilityBill.room'])
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

    public function settle(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'paid_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);
        $this->paymentLedgerService->settle($payment, $validated['paid_date']);

        return redirect()->route('proprietor.payments.index')->with('success', 'Payment marked as paid. Financial Report has been updated.');
    }

    public function store(StorePaymentRequest $request)
    {
        $payment = $this->paymentLedgerService->create($request->validated());
        $payment->load('tenant.user');

        if ($payment->status === 'paid') {
            $type = 'payment_confirmation';
            $message = sprintf(
                'Your payment of PHP %s due on %s has been recorded as paid.',
                number_format((float) $payment->amount, 2),
                $payment->due_date->format('M d, Y')
            );
        } elseif ($payment->status === 'overdue') {
            $type = 'payment_overdue';
            $message = sprintf(
                'A payment of PHP %s is overdue. Its due date was %s.',
                number_format((float) $payment->amount, 2),
                $payment->due_date->format('M d, Y')
            );
        } else {
            $type = 'payment_due';
            $message = sprintf(
                'A payment of PHP %s is due on %s.',
                number_format((float) $payment->amount, 2),
                $payment->due_date->format('M d, Y')
            );
        }

        $this->notificationService->notify($payment->tenant->user, $type, $message);

        return redirect()
            ->route('proprietor.payments.index')
            ->with('success', 'Payment recorded successfully.');
    }
}
