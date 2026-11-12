<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Tenant;
use App\Services\TenantManagementService;
use App\Services\RoomAvailability;
use Illuminate\Http\Request;
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
                    ->orWhere('emergency_contact_name', 'like', "%{$search}%")
                    ->orWhere('emergency_contact_number', 'like', "%{$search}%");
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

    public function create()
    {
        return view('proprietor.tenants.create', ['roomAvailability' => $this->rooms->forTenant()]);
    }

    public function store(StoreTenantRequest $request)
    {
        $tenant = $this->tenantService->create($request->validated(), $request->user()->id);

        return redirect()
            ->route('proprietor.tenants.index')
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
            ->route('proprietor.tenants.index')
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
