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

    public function index()
    {
        $recentSummaries = \App\Models\AiSummary::with('generatedBy')
            ->latest()
            ->take(10)
            ->get();

        return view('proprietor.complaints.summarize', compact('recentSummaries'));
    }

    public function store(Request $request)
    {
        $filters = $request->validate([
            'category' => ['nullable', Rule::in(['maintenance', 'noise', 'billing', 'other'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $complaints = Complaint::query()
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($filters['date_from'] ?? null, fn ($query, $dateFrom) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, $dateTo) => $query->whereDate('created_at', '<=', $dateTo))
            ->latest()
            ->get(['category', 'description', 'status', 'created_at']);

        $summary = $this->complaintSummarizationService->summarize($complaints, [
            ...$filters,
            'generated_by' => $request->user()->id,
        ]);

        return response()->json($summary);
    }
}
