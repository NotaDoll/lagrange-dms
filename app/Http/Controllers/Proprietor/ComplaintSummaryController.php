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

        $generated = null;
        try {
            $generated = $summarizer->generateWithThemes($complaints);
        } catch (\Throwable $exception) {
            report($exception);
        }

        if ($generated === null) {
            return response()->json(['message' => 'The AI could not generate a summary with reliable complaint groups. Please try again.'], 502);
        }

        $summary = ComplaintSummary::create([
            'user_id' => $request->user()->id,
            'complaint_ids' => $complaints->modelKeys(),
            'complaint_count' => $complaints->count(),
            'categories_included' => $complaints->pluck('category')->unique()->sort()->values()->all(),
            'period_start' => $complaints->first()->created_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->toDateString(),
            'period_end' => $complaints->last()->created_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->toDateString(),
            'summary' => $generated['summary'],
            'theme_complaint_ids' => [
                'themes' => $generated['themes'],
                'recategorized' => $generated['recategorized'],
                'recommended_action' => $generated['recommended_action'],
            ],
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
            'complaints_url' => route('proprietor.complaints.index', ['complaint_ids' => $summary->complaint_ids]),
            'categories' => $summary->categories_included ?? [],
            'themes' => $this->presentThemes($summary),
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
            'groups' => $this->presentCategories($summary),
            'recategorized' => $this->presentRecategorized($summary, $complaints),
            'recommended_action' => $this->analysis($summary)['recommended_action'] ?? null,
            'complaints_missing' => $complaints->count() < $summary->complaint_count,
            'complaints' => $complaints->map(fn (Complaint $complaint) => [
                'tenant' => $complaint->tenant?->name ?? 'Tenant unavailable',
                'category' => $complaint->category,
                'date' => $complaint->created_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->format('M d, Y'),
            ])->all(),
        ];
    }

    /** Stored analysis: a list of themes (older summaries) or {themes, recategorized, recommended_action}. */
    private function analysis(ComplaintSummary $summary): array
    {
        $stored = $summary->theme_complaint_ids ?? [];

        return array_is_list($stored) ? ['themes' => $stored] : $stored;
    }

    private function presentThemes(ComplaintSummary $summary): array
    {
        return collect($this->analysis($summary)['themes'] ?? [])->map(function (array $theme) {
            $ids = array_values(array_filter($theme['complaint_ids'] ?? [], fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id))));

            return [
                'theme' => (string) ($theme['theme'] ?? ''),
                'category' => $theme['category'] ?? null,
                'detail' => (string) ($theme['detail'] ?? ''),
                'count' => (int) ($theme['count'] ?? count($ids)),
                'url' => $ids === [] ? null : route('proprietor.complaints.index', ['complaint_ids' => $ids]),
            ];
        })->filter(fn ($theme) => $theme['theme'] !== '' && $theme['url'] !== null)->values()->all();
    }

    /** Issues grouped under the category the AI gave them, largest category first. */
    private function presentCategories(ComplaintSummary $summary): array
    {
        $labels = ComplaintSummarizationService::CATEGORY_LABELS;

        return collect($this->presentThemes($summary))->groupBy(fn ($theme) => $theme['category'] ?? '')
            ->map(fn ($issues, $category) => [
                'label' => $labels[$category] ?? 'Issues',
                'count' => $issues->sum('count'),
                'issues' => $issues->values()->all(),
            ])->sortByDesc('count')->values()->all();
    }

    /** Complaints whose text the AI read as a different category from the one the tenant chose. */
    private function presentRecategorized(ComplaintSummary $summary, Collection $complaints): array
    {
        $labels = ComplaintSummarizationService::CATEGORY_LABELS;
        $byId = $complaints->keyBy('id');

        return collect($this->analysis($summary)['recategorized'] ?? [])->map(fn ($moved) => [
            'tenant' => $byId->get($moved['complaint_id'])?->tenant?->name ?? 'Complaint #'.$moved['complaint_id'],
            'filed_as' => $labels[$moved['filed_as']] ?? ucfirst((string) $moved['filed_as']),
            'category' => $labels[$moved['category']] ?? ucfirst((string) $moved['category']),
            'url' => route('proprietor.complaints.index', ['complaint_ids' => [$moved['complaint_id']]]),
        ])->values()->all();
    }
}
