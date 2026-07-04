<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateComplaintStatusRequest;
use App\Models\Complaint;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ComplaintController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function index(Request $request)
    {
        $category = $request->query('category');
        $status = $request->query('status');

        $complaints = Complaint::with('tenant.user')
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('proprietor.complaints.index', compact('complaints', 'category', 'status'));
    }

    public function update(UpdateComplaintStatusRequest $request, Complaint $complaint)
    {
        $previousStatus = $complaint->status;
        $complaint->update([
            'status' => $request->validated('status'),
        ]);

        $complaint->load('tenant.user');
        $this->notificationService->notify(
            $complaint->tenant->user,
            'complaint_status',
            sprintf(
                'Your complaint status has been updated from %s to %s.',
                str_replace('_', ' ', $previousStatus),
                str_replace('_', ' ', $complaint->status)
            )
        );

        return redirect()
            ->route('proprietor.complaints.index', $request->query())
            ->with('success', 'Complaint status updated successfully.');
    }
}
