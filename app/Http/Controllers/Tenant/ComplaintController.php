<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplaintRequest;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ComplaintController extends Controller
{
    public function create()
    {
        return view('tenant.complaints.create');
    }

    public function index(Request $request): View
    {
        $tenant = auth()->user()->tenant()->firstOrFail();
        $statuses = array_values(array_intersect(
            array_filter((array) $request->query('status'), 'is_string'),
            ['submitted', 'in_progress', 'resolved']
        ));
        $categories = array_values(array_intersect(
            array_filter((array) $request->query('category'), 'is_string'),
            ['maintenance', 'noise', 'billing', 'other']
        ));
        $dateFilter = $request->query('date_filter', 'all');
        if (! in_array($dateFilter, ['7', '30', '90', 'all'], true)) {
            $dateFilter = 'all';
        }

        $complaints = Complaint::where('tenant_id', $tenant->id)
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
            ->when($categories !== [], fn ($query) => $query->whereIn('category', $categories))
            ->when($dateFilter !== 'all', fn ($query) => $query->where('created_at', '>=', now()->subDays((int) $dateFilter)->startOfDay()))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('tenant.complaints.index', compact('complaints', 'dateFilter'));
    }

    public function store(StoreComplaintRequest $request)
    {
        $tenant = auth()->user()->tenant()->firstOrFail();

        Complaint::create([
            'tenant_id' => $tenant->id,
            'category' => $request->validated('category'),
            'description' => $request->validated('description'),
            'status' => Complaint::STATUS_IN_PROGRESS,
        ]);

        return redirect()
            ->route('tenant.complaints.index')
            ->with('success', 'Complaint submitted successfully.');
    }
}



