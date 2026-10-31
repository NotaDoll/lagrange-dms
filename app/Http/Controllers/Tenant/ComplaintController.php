<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplaintRequest;
use App\Models\Complaint;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
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
            ->route('tenant.complaints.create')
            ->with('success', 'Complaint submitted successfully.');
    }
}



