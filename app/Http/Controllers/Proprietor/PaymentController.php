<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\NotificationService;
use App\Services\PaymentLedgerService;
use App\Services\TenantBalanceService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentLedgerService $paymentLedgerService,
        private readonly NotificationService $notificationService
    ) {}

    public function index(Request $request, TenantBalanceService $balances)
    {
        $tenantId = $request->query('tenant_id');
        $status = $request->query('status');
        $category = $request->validate(['category' => ['nullable', Rule::in(array_keys(Payment::CATEGORY_LABELS))]])['category'] ?? null;
        $payments = Payment::with(['tenant.user', 'tenant.currentAssignment.bed.room', 'utilityBill.room'])
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($category === Payment::UTILITIES, fn ($query) => $query->whereNotNull('utility_bill_id'))
            ->when($category === Payment::RENT, fn ($query) => $query->whereNull('utility_bill_id')
                ->where(fn ($rent) => $rent->whereNull('charge_type')->orWhere('charge_type', Payment::RENT)))
            ->when(in_array($category, [Payment::OPENING_BALANCE, Payment::STORAGE], true),
                fn ($query) => $query->where('charge_type', $category))
            ->latest('due_date')
            ->paginate(15)
            ->withQueryString();

        $tenants = Tenant::with('user')
            ->get()->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $accrued = collect();
        if ($status !== 'paid' && in_array($category, [null, Payment::STORAGE, Payment::OPENING_BALANCE], true)) {
            $accrued = Tenant::movedIn()->with(['user', 'payments', 'monthModes', 'roomAssignments.bed.room'])
                ->when($tenantId, fn ($query) => $query->whereKey($tenantId))
                ->where(fn ($query) => $query->where('opening_balance', '>', 0)->orWhereHas('monthModes'))
                ->get()->flatMap(fn ($tenant) => $balances->billingEntries(
                    $tenant, $balances->ledgerStart($tenant), Tenant::localDate(), specialOnly: true
                ))
                ->when($status, fn ($entries) => $entries->where('status', $status))
                ->when($category, fn ($entries) => $entries->where('category', $category))
                ->sortByDesc(fn ($entry) => $entry->due_date->toDateString())->values();
        }

        $page = LengthAwarePaginator::resolveCurrentPage('accrued_page');
        $accruedPayments = new LengthAwarePaginator($accrued->forPage($page, 15), $accrued->count(), 15, $page, [
            'path' => $request->url(), 'pageName' => 'accrued_page',
        ]);
        $accruedPayments->withQueryString();
        $categories = Payment::CATEGORY_LABELS;

        return view('proprietor.payments.index', compact('payments', 'accruedPayments', 'tenants', 'tenantId', 'status', 'category', 'categories'));
    }

    public function create()
    {
        $tenants = Tenant::with('user')
            ->get()->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        return view('proprietor.payments.create', compact('tenants'));
    }

    public function settleAccrued(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'charge_type' => ['required', Rule::in([Payment::OPENING_BALANCE, Payment::STORAGE])],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'paid_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $this->paymentLedgerService->settleAccrued($tenant, $validated['charge_type'], $validated['due_date'], $validated['paid_date']);

        return redirect()->route('proprietor.payments.index', ['tenant_id' => $tenant->id])
            ->with('success', 'Payment Ledger updated.');
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
