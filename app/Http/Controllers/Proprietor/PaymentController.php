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
use App\Imports\PaymentsImport;
use Maatwebsite\Excel\Facades\Excel;

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

        $tenants = Tenant::with('user')
            ->get()->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $rows = Tenant::with(['user', 'currentAssignment.bed.room', 'payments.utilityBill.room', 'monthModes', 'roomAssignments.bed.room'])
            ->when($tenantId, fn ($query) => $query->whereKey($tenantId))
            ->get()->flatMap(fn (Tenant $tenant) => $balances->ledger($tenant)
                ->each(fn (Payment $payment) => $payment->setRelation('tenant', $tenant)))
            ->when(is_string($status) && isset(Payment::STATUS_LABELS[$status]), fn ($entries) => $entries->where('current_status', $status))
            ->when($category, fn ($entries) => $entries->where('category', $category))
            ->sortByDesc(fn (Payment $payment) => $payment->due_date->toDateString())->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $payments = (new LengthAwarePaginator($rows->forPage($page, 10), $rows->count(), 10, $page, [
            'path' => $request->url(),
        ]))->withQueryString();
        $categories = Payment::CATEGORY_LABELS;

        return view('proprietor.payments.index', compact('payments', 'tenants', 'tenantId', 'status', 'category', 'categories'));
    }

    public function create(Request $request)
    {
        $tenants = Tenant::with('user')
            ->get()->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $selectedTenantId = $request->integer('tenant_id');
        $selectedUtilityPaymentId = $request->integer('utility_payment_id');

        return view('proprietor.payments.create', compact('tenants', 'selectedTenantId', 'selectedUtilityPaymentId'));
    }

    public function settleAccrued(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'charge_type' => ['required', Rule::in([Payment::OPENING_BALANCE, Payment::STORAGE])],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'paid_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.Tenant::localDate()],
            'receipt_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9#\/\-. ]+$/', Rule::unique('payments', 'receipt_number')],
        ], ['receipt_number.required' => 'Enter the receipt number before marking it paid.', 'receipt_number.unique' => 'This receipt number is already used on another payment.']);

        $this->paymentLedgerService->settleAccrued($tenant, $validated['charge_type'], $validated['due_date'], $validated['paid_date'], $validated['receipt_number'] ?? null);

        return redirect()->route('proprietor.payments.index', ['tenant_id' => $tenant->id])
            ->with('success', 'Payment Ledger updated.');
    }

    public function settle(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'paid_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.Tenant::localDate()],
            'receipt_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9#\/\-. ]+$/', Rule::unique('payments', 'receipt_number')],
        ], ['receipt_number.required' => 'Enter the receipt number before marking it paid.', 'receipt_number.unique' => 'This receipt number is already used on another payment.']);
        $this->paymentLedgerService->settle($payment, $validated['paid_date'], $validated['receipt_number'] ?? null);

        return redirect()->route('proprietor.payments.index')->with('success', 'Payment marked as paid. Financial Report has been updated.');
    }

    /**
     * JSON for the Record Payment form: which due date this payment type would go to
     * (worked out from the tenant's move-in day) and that month's balance.
     */
    public function monthBalance(Request $request, TenantBalanceService $balances)
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'payment_type' => ['required', Rule::in(array_keys(Payment::PAYMENT_TYPE_LABELS))],
        ]);
        $tenant = Tenant::findOrFail($validated['tenant_id']);
        $target = $this->paymentLedgerService->targetMonth($tenant, $validated['payment_type']);

        if ($target['month'] === null) {
            return response()->json(['ok' => false, 'message' => $target['reason']]);
        }

        $balance = $balances->monthBalance($tenant, $target['month']);
        $due = $balance['unpaid_rows']->first()?->due_date ?? $balance['charge']?->due_date;

        return response()->json([
            'ok' => true,
            'month' => $target['month']->format('F Y'),
            'due_date' => $due?->format('M d, Y'),
            'overdue' => $due !== null && $due->lt(Tenant::localDate()),
            'amount' => $balance['amount'],
            'paid' => $balance['paid'],
            'advance_applied' => $balance['advance_applied'],
            'remaining' => $balance['remaining'],
            'storage' => $balance['storage'],
        ]);
    }

    public function preview(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'applies_to' => ['required', Rule::in([Payment::RENT, Payment::UTILITIES])],
            'amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
        ]);
        $tenant = Tenant::findOrFail($validated['tenant_id']);
        if ($validated['applies_to'] === Payment::UTILITIES) {
            $bills = $tenant->payments()->whereNotNull('utility_bill_id')->where('status', '!=', 'paid')
                ->with('utilityBill.room')->orderBy('due_date')->orderBy('id')->get()->map(fn (Payment $share) => [
                    'id' => $share->id,
                    'label' => $share->description,
                    'due_date' => $share->due_date->format('M d, Y'),
                    'amount' => (float) $share->amount,
                ]);

            return response()->json(['bills' => $bills]);
        }

        $allocations = isset($validated['amount'])
            ? $this->paymentLedgerService->previewPayment($tenant, (float) $validated['amount'])
            : [];

        return response()->json(['allocations' => $allocations]);
    }

    public function store(StorePaymentRequest $request)
    {
        $rows = $this->paymentLedgerService->recordPayment($request->validated());
        $first = $rows->first();
        $first->load('tenant.user');
        $typeLabel = $request->filled('applies_to')
            ? ($first->category === Payment::UTILITIES ? 'Utility' : 'Rent')
            : Payment::PAYMENT_TYPE_LABELS[$first->payment_type];
        $months = $rows->map(fn (Payment $row) => $row->due_date->format('M Y'))->unique()->implode(', ');
        $total = number_format((float) $rows->sum('amount'), 2);
        // Advance rows share one receipt with a per-month suffix (RCPT-X-1, RCPT-X-2, ...).
        $receipt = $first->receipt_reference
            ?? ($rows->count() > 1 ? preg_replace('/-\d+$/', '', $first->receipt_number) : $first->receipt_number);

        $this->notificationService->notify(
            $first->tenant->user,
            'payment_confirmation',
            "Your {$typeLabel} payment of PHP {$total} for {$months} has been recorded. Receipt No: {$receipt}"
        );

        return redirect()
            ->route('proprietor.payments.index')
            ->with('success', "{$typeLabel} payment of PHP {$total} recorded for {$months}.");
    }

    public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|mimes:xlsx,xls,csv|max:5120'
        ]);

        $import = new PaymentsImport($this->paymentLedgerService);

        try {
            Excel::import($import, $request->file('excel_file'));

            $successCount = $import->getSuccessCount();
            $errors = $import->getErrors();

            if (count($errors) > 0) {
                return redirect()->back()
                    ->with('warning', "Successfully imported {$successCount} payments, but some rows failed.")
                    ->with('import_errors', $errors);
            }

            return redirect()->back()->with('success', "Successfully imported {$successCount} payments!");

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }

    /**
     * Display the payment history for a specific tenant.
     */
    public function history(Tenant $tenant, Request $request, TenantBalanceService $balances)
    {
        $tenant->load('payments.utilityBill.room');
        $rows = $balances->ledger($tenant);
        $page = LengthAwarePaginator::resolveCurrentPage();
        $payments = new LengthAwarePaginator($rows->forPage($page, 10), $rows->count(), 10, $page, ['path' => $request->url()]);

        $tenant->load('user', 'currentAssignment.bed.room');

        return view('proprietor.payments.history', compact('tenant', 'payments'));
    }
}
