<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateComplaintStatusRequest;
use App\Models\Complaint;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ComplaintController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService)
    {
    }

    public function index(Request $request)
    {
        $category = $request->query('category');
        $category = is_string($category) ? $category : null;
        $status = $request->query('status');
        $status = is_string($status) && isset(Complaint::STATUS_LABELS[$status]) ? $status : null;
        $search = $request->query('search', '');
        $search = is_string($search) ? $search : '';
        $term = mb_strtolower(trim($search));
        $pattern = '%'.strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';

        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $dateFrom = is_string($dateFrom) ? $dateFrom : null;
        $dateTo = is_string($dateTo) ? $dateTo : null;
        $dates = Validator::make(
            ['date_from' => $dateFrom, 'date_to' => $dateTo],
            [
                'date_from' => ['nullable', 'date_format:Y-m-d'],
                'date_to' => ['nullable', 'date_format:Y-m-d', ...($dateFrom ? ['after_or_equal:date_from'] : [])],
            ],
            ['date_to.after_or_equal' => 'Date To must be on or after Date From.']
        );
        $dateErrors = $dates->errors();

        $complaints = Complaint::with('tenant.user')
            ->when($category, fn ($query) => $query->where('category', $category))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($dateFrom && $dateErrors->isEmpty(), fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo && $dateErrors->isEmpty(), fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->when($term !== '', fn ($query) => $query->where(function ($query) use ($pattern) {
                $query->whereRaw("LOWER(category) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(description) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereHas('tenant.user', fn ($user) => $user
                        ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$pattern]));
            }))
            ->latest()
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('proprietor.complaints.index', compact(
            'complaints', 'category', 'status', 'search', 'dateFrom', 'dateTo', 'dateErrors'
        ));
    }

    public function markRead(Complaint $complaint)
    {
        Complaint::whereKey($complaint->getKey())
            ->whereNull('read_at')
            ->toBase()
            ->update(['read_at' => now()]);

        return response()->noContent();
    }

    public function update(UpdateComplaintStatusRequest $request, Complaint $complaint)
    {
        $previousStatus = $complaint->status;
        $complaint->update([
            'status' => $request->validated('status'),
        ]);

        $complaint->load('tenant.user');

        if ($complaint->tenant?->user) {
            $this->notificationService->notify(
                $complaint->tenant->user,
                'complaint_status',
                sprintf(
                    'Your complaint status has been updated from %s to %s.',
                    str_replace('_', ' ', $previousStatus),
                    str_replace('_', ' ', $complaint->status)
                )
            );
        }

        return redirect()
            ->route('proprietor.complaints.index', $request->query())
            ->with('success', 'Complaint status updated successfully.');
    }
}
