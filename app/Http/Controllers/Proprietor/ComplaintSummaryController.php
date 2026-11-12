<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\ComplaintSummary;
use App\Services\ComplaintSummarizationService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ComplaintSummaryController extends Controller
{
    private const DISPLAY_TIMEZONE = 'Asia/Manila';

    public function index(): JsonResponse
    {
        $summaries = ComplaintSummary::latest()
            ->orderByDesc('id')
            ->paginate(10)
            ->through(fn (ComplaintSummary $summary) => [
                ...$this->metadata($summary),
                'preview' => Str::limit(Str::squish($summary->summary), 100),
            ]);

        return response()->json($summaries);
    }

    public function store(Request $request, ComplaintSummarizationService $summarizer): JsonResponse
    {
        $limit = ComplaintSummarizationService::MAX_COMPLAINTS_SENT;
        $validator = Validator::make($request->all(), [
            'complaint_ids' => ['bail', 'required', 'array', 'list', 'min:1', "max:$limit"],
            'complaint_ids.*' => ['bail', 'required', 'integer', 'distinct'],
        ], [
            'complaint_ids.required' => 'Select at least one complaint to summarize.',
            'complaint_ids.min' => 'Select at least one complaint to summarize.',
            'complaint_ids.max' => "Select at most $limit complaints to summarize.",
            'complaint_ids.array' => 'Complaint IDs must be provided as an array.',
            'complaint_ids.list' => 'Complaint IDs must be provided as a list.',
            'complaint_ids.*.integer' => 'Each complaint ID must be an integer.',
            'complaint_ids.*.distinct' => 'Each complaint can be selected only once.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $ids = $validator->validated()['complaint_ids'];
        $complaints = $this->loadComplaints($ids);

        if ($complaints->count() !== count($ids)) {
            return response()->json([
                'message' => 'Some selected complaints are no longer available. Refresh the page and select them again.',
            ], 422);
        }

        try {
            $text = $summarizer->generate($complaints);
        } catch (\Throwable $exception) {
            report($exception);
            $text = null;
        }

        if ($text === null) {
            return response()->json(['message' => 'The AI could not generate a summary. Please try again.'], 502);
        }

        $summary = ComplaintSummary::create([
            'user_id' => $request->user()->id,
            'complaint_ids' => $complaints->modelKeys(),
            'complaint_count' => $complaints->count(),
            'categories_included' => $complaints->pluck('category')->unique()->sort()->values()->all(),
            'period_start' => $complaints->first()->created_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->toDateString(),
            'period_end' => $complaints->last()->created_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->toDateString(),
            'summary' => $text,
        ]);

        return response()->json($this->detail($summary, $complaints), 201);
    }

    public function show(ComplaintSummary $summary): JsonResponse
    {
        return response()->json($this->detail($summary, $this->loadComplaints($summary->complaint_ids)));
    }

    public function destroy(ComplaintSummary $summary): Response
    {
        $summary->delete();

        return response()->noContent();
    }

    private function loadComplaints(array $ids): Collection
    {
        return Complaint::with('tenant.user')
            ->whereKey($ids)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    private function metadata(ComplaintSummary $summary): array
    {
        return [
            'id' => $summary->id,
            'url' => route('proprietor.complaints.summaries.show', $summary),
            'complaint_count' => $summary->complaint_count,
            'categories' => $summary->categories_included ?? [],
            'created_at' => $summary->created_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->format('M d, Y, g:i A'),
            'created_ago' => $summary->created_at->diffForHumans(),
        ];
    }

    private function detail(ComplaintSummary $summary, Collection $complaints): array
    {
        return [
            ...$this->metadata($summary),
            'period_start' => $summary->period_start?->format('M d, Y'),
            'period_end' => $summary->period_end?->format('M d, Y'),
            'summary' => $summary->summary,
            'complaints_missing' => $complaints->count() < $summary->complaint_count,
            'complaints' => $complaints->map(fn (Complaint $complaint) => [
                'tenant' => $complaint->tenant?->name ?? 'Tenant unavailable',
                'category' => $complaint->category,
                'date' => $complaint->created_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->format('M d, Y'),
            ])->all(),
        ];
    }
}
