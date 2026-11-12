<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Services\ComplaintSummarizationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AiSummaryController extends Controller
{
    public function __construct(private readonly ComplaintSummarizationService $complaintSummarizationService)
    {
    }

    public function store(Request $request)
    {
        $filters = $request->validate([
            'category'  => ['nullable', Rule::in(['maintenance', 'noise', 'billing', 'other'])],
            'date_from' => ['nullable', 'date'],
            'date_to'   => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $complaints = Complaint::query()
            ->when($filters['category'] ?? null, fn ($q, $category) => $q->where('category', $category))
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest()
            ->get(['category', 'description', 'status', 'created_at']);

        if ($complaints->isEmpty()) {
            // Return a clean message instead of a 422 error
            return response()->json([
                'summary' => "No complaints were found matching your selected filters. Try expanding your date range or choosing a different category.",
                'message' => 'No complaints match those filters.'
            ]);
        }

        try {
            $summary = $this->complaintSummarizationService->summarize($complaints, [
                ...$filters,
                'generated_by' => $request->user()->id,
            ]);

            return response()->json(array_merge($summary->toArray(), [
                'message'         => 'Summary generated and saved.',
                'summary'         => $summary->summary_result,
                'complaint_count' => $complaints->count(),
                'created_at'      => $summary->created_at->diffForHumans(),
            ]));
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'Unable to generate a summary: '.$e->getMessage(),
            ], 502);
        }
    }
}
