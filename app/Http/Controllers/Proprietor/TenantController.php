<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Payment;
use App\Models\Tenant;
use App\Services\PaymentRiskService;
use App\Services\TenantBalanceService;
use App\Services\TenantManagementService;
use App\Services\RoomAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class TenantController extends Controller
{
    public function __construct(private readonly TenantManagementService $tenantService, private readonly RoomAvailability $rooms)
    {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', '');

        $query = Tenant::query()->with(['user', 'currentAssignment.bed.room', 'consentRecorder']);

        if ($search !== '') {
            $hasContactProfile = Schema::hasColumn('tenants', 'email');
            $query->where(function ($tenantQuery) use ($search, $hasContactProfile) {
                $tenantQuery->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%");
                })->when($hasContactProfile, fn ($query) => $query
                    ->orWhere('tenants.name', 'like', "%{$search}%")
                    ->orWhere('tenants.email', 'like', "%{$search}%")
                    ->orWhere('tenants.contact_number', 'like', "%{$search}%"))
                    ->orWhere('guardian_name', 'like', "%{$search}%");
            });
        }

        if (is_string($status) && isset(Tenant::STATUS_LABELS[$status])) {
            $query->where('status', $status);
        } else {
            $status = '';
        }

        $tenants = $query
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $tenantCounts = [
            'all' => Tenant::count(),
            'active' => Tenant::where('status', 'active')->count(),
            'inactive' => Tenant::where('status', 'inactive')->count(),
        ];

        return view('proprietor.tenants.index', compact('tenants', 'search', 'status', 'tenantCounts'));
    }

    /** Everything about one tenant: details, balance and risk, payment history, complaints, reminders, rooms. */
    public function show(Tenant $tenant, TenantBalanceService $balances, PaymentRiskService $risk)
    {
        $tenant->load([
            'user', 'consentRecorder', 'currentAssignment.bed.room', 'roomAssignments.bed.room',
            'payments.utilityBill.room', 'monthModes',
            'complaints' => fn ($query) => $query->latest(),
            'smsLogs' => fn ($query) => $query->where('recipient_type', 'guardian')->latest('id')->limit(10),
        ]);

        $today = Carbon::parse(Tenant::localDate());
        // Include rows paid ahead (advance payments) as well as everything billed so far.
        $latestDue = $tenant->payments->max('due_date');
        $until = $latestDue && $latestDue->gt($today) ? $latestDue : $today;
        $history = $balances->billingEntries($tenant, $balances->ledgerStart($tenant), $until->toDateString())
            ->sortByDesc(fn (Payment $entry) => [$entry->due_date->toDateString(), $entry->id ?? PHP_INT_MAX])
            ->values();

        $evaluation = $tenant->hasMovedIn() ? $risk->evaluate($tenant) : null;

        return view('proprietor.tenants.show', [
            'tenant' => $tenant,
            'summary' => $balances->summary($tenant),
            'rent' => $balances->currentRent($tenant),
            'history' => $history,
            'risk' => $evaluation,
            'riskReasons' => $evaluation ? $risk->reasons($evaluation) : [],
            'roomHistory' => $tenant->roomAssignments->sortByDesc(fn ($assignment) => [$assignment->start_date->toDateString(), $assignment->id])->values(),
        ]);
    }

    public function create()
    {
        return view('proprietor.tenants.create', ['roomAvailability' => $this->rooms->forTenant()]);
    }

    public function store(StoreTenantRequest $request)
    {
        $tenant = $this->tenantService->create($request->validated(), $request->user()->id);

        return redirect()
            ->route('proprietor.tenants.show', $tenant)
            ->with($tenant->user_id ? 'success' : 'notice', $tenant->user_id
                ? 'Tenant created successfully.'
                : 'Tenant saved. A login with this email already exists, so no new account was created.');
    }

    public function edit(Tenant $tenant)
    {
        $tenant->load(['user', 'currentAssignment.bed.room']);
        return view('proprietor.tenants.edit', [
            'tenant' => $tenant,
            'roomAvailability' => $this->rooms->forTenant($tenant),
        ]);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant)
    {
        $loginEmailSkipped = $this->tenantService->update($tenant, $request->validated(), $request->user()->id);

        return redirect()
            ->route('proprietor.tenants.show', $tenant)
            ->with($loginEmailSkipped ? 'notice' : 'success', $loginEmailSkipped
                ? 'Tenant saved. The login email was kept because another account already uses that email.'
                : 'Tenant updated successfully.');
    }

    public function destroy(Tenant $tenant)
    {
        $this->tenantService->delete($tenant);

        return redirect()
            ->route('proprietor.tenants.index')
            ->with('success', 'Tenant deleted successfully.');
    }
}
