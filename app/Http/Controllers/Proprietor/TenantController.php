<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdateTenantRequest;
use App\Models\Tenant;
use App\Services\TenantManagementService;
use App\Models\Bed;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(private readonly TenantManagementService $tenantService)
    {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', '');

        $query = Tenant::query()->with(['user', 'currentAssignment.bed.room']);

        if ($search !== '') {
            $query->where(function ($tenantQuery) use ($search) {
                $tenantQuery->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%");
                })->orWhere('emergency_contact_name', 'like', "%{$search}%")
                    ->orWhere('emergency_contact_number', 'like', "%{$search}%");
            });
        }

        if (in_array($status, ['active', 'inactive'], true)) {
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
    $availableBeds = Bed::with('room')
        ->where('status', 'available')
        ->get()
        ->sortBy(fn ($bed) => $bed->room->room_number . $bed->bed_label);

    return view('proprietor.tenants.create', compact('availableBeds'));
}

    public function store(StoreTenantRequest $request)
{
    $result = $this->tenantService->create($request->validated());

    return redirect()
        ->route('proprietor.tenants.index')
        ->with('success', 'Tenant created successfully.')
        ->with('temp_password', $result['temp_password'])
        ->with('temp_password_email', $result['tenant']->user->email);
}
    public function edit(Tenant $tenant)
{
    $tenant->load(['user', 'currentAssignment.bed.room']);

    $currentBedId = $tenant->currentAssignment?->bed_id;

    $availableBeds = Bed::with('room')
        ->where(function ($query) use ($currentBedId) {
            $query->where('status', 'available');

            if ($currentBedId) {
                $query->orWhere('id', $currentBedId);
            }
        })
        ->get()
        ->sortBy(fn ($bed) => $bed->room->room_number . $bed->bed_label);

    return view('proprietor.tenants.edit', compact('tenant', 'availableBeds'));
}

    public function update(UpdateTenantRequest $request, Tenant $tenant)
    {
        $this->tenantService->update($tenant, $request->validated());

        return redirect()
            ->route('proprietor.tenants.index')
            ->with('success', 'Tenant updated successfully.');
    }

    public function destroy(Tenant $tenant)
    {
        $this->tenantService->delete($tenant);

        return redirect()
            ->route('proprietor.tenants.index')
            ->with('success', 'Tenant deleted successfully.');
    }
}
