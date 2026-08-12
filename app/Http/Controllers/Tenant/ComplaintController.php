<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplaintRequest;
use App\Models\Complaint;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $tenant = auth()->user()->tenant()->firstOrFail();

        // Start building the query
        $query = Complaint::where('tenant_id', $tenant->id);

        // 1. Filter by Status (if checkboxes are selected)
        if ($request->has('status') && !empty($request->status)) {
            $query->whereIn('status', $request->status);
        }

        // 2. Filter by Category (if checkboxes are selected)
        if ($request->has('category') && !empty($request->category)) {
            $query->whereIn('category', $request->category);
        }

        // 3. Filter by Date Submitted
        if ($request->filled('date_filter') && $request->date_filter !== 'all') {
            $days = (int) $request->date_filter;
            $query->where('created_at', '>=', now()->subDays($days));
        }

        // 4. Get the filtered results, ordered by newest first
        $complaints = $query->latest()->paginate(10);

        // 5. CRITICAL: Append the current filters to the pagination links
        // This ensures if you go to Page 2, your filters stay active!
        $complaints->appends($request->query());

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
