<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplaintRequest;
use App\Models\Complaint;

class ComplaintController extends Controller
{
    public function index()
    {
        $tenant = auth()->user()->tenant()->firstOrFail();

        $complaints = Complaint::where('tenant_id', $tenant->id)
            ->latest()
            ->paginate(10);

        return view('tenant.complaints.index', compact('complaints'));
    }

    public function create()
    {
        return view('tenant.complaints.create');
    }

    public function store(StoreComplaintRequest $request)
    {
        $tenant = auth()->user()->tenant()->firstOrFail();

        Complaint::create([
            'tenant_id' => $tenant->id,
            'category' => $request->validated('category'),
            'description' => $request->validated('description'),
            'status' => 'submitted',
        ]);

        return redirect()
            ->route('tenant.complaints.index')
            ->with('success', 'Complaint submitted successfully.');
    }
}
