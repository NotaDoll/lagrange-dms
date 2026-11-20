<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Models\AiSummary;
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
        $hasComplaintScope = $request->query->has('complaint_ids');
        $rawComplaintIds = (array) $request->query('complaint_ids', []);
        $complaintScopeIds = $hasComplaintScope
            ? array_slice(array_values(array_unique(array_filter(array_map(function ($id) {
                if (is_int($id) && $id > 0) {
                    return $id;
                }

                return is_string($id) && ctype_digit($id) && (int) $id > 0 ? (int) $id : null;
            }, $rawComplaintIds)))), 0, 50)
            : null;
        $summaryId = $request->query('summary_id');
        $summary = is_string($summaryId) && ctype_digit($summaryId) ? AiSummary::find((int) $summaryId) : null;
        $summaryComplaintIds = $summary?->filter_params['complaint_ids'] ?? null;
        $summaryComplaintIds = is_array($summaryComplaintIds)
            ? array_values(array_filter($summaryComplaintIds, fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id))))
            : null;
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

        $complaintsQuery = Complaint::with('tenant.user');
        if ($complaintScopeIds !== null) {
            $complaintsQuery->whereKey($complaintScopeIds);
        } elseif ($summary) {
            if ($summaryComplaintIds !== null) {
                $complaintsQuery->whereKey($summaryComplaintIds);
            } else {
                // Older saved summaries did not keep their source IDs; show the closest available set.
                $complaintsQuery->open();
            }
        }

        $complaints = $complaintsQuery
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
            ->paginate(10)
            ->withQueryString();

        return view('proprietor.complaints.index', compact(
            'complaints', 'category', 'status', 'search', 'dateFrom', 'dateTo', 'dateErrors', 'summary', 'summaryComplaintIds', 'complaintScopeIds'
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
