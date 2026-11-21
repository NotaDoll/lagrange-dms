<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\AiSummary;
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
        $summaries = AiSummary::latest()
            ->orderByDesc('id')
            ->paginate(10)
            ->through(fn (AiSummary $summary) => [
                ...$this->metadata($summary),
                'preview' => Str::limit(Str::squish($this->text($summary)), 100),
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

        // Every summary, from this page or the Dashboard, is saved in the same ai_summaries table
        // with its complaint IDs and the AI response time used in the evaluation.
        try {
            $summary = $summarizer->summarize($complaints, [
                'generated_by' => $request->user()->id,
                'complaint_ids' => $complaints->modelKeys(),
                'complaint_count' => $complaints->count(),
                'categories' => $complaints->pluck('category')->unique()->sort()->values()->all(),
                'period_start' => $complaints->first()->created_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->toDateString(),
                'period_end' => $complaints->last()->created_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->toDateString(),
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['message' => 'The AI could not generate a summary with reliable complaint groups. Please try again.'], 502);
        }

        return response()->json($this->detail($summary, $complaints), 201);
    }

    public function show(AiSummary $summary): JsonResponse
    {
        return response()->json($this->detail($summary, $this->loadComplaints($this->complaintIds($summary))));
    }

    public function destroy(AiSummary $summary): Response
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

    /** @return list<int> the complaints this summary covered (empty for old filter-based summaries) */
    private function complaintIds(AiSummary $summary): array
    {
        $ids = $summary->filter_params['complaint_ids'] ?? [];

        return is_array($ids) ? array_values(array_map('intval', array_filter($ids, fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id))))) : [];
    }

    private function param(AiSummary $summary, string $key): mixed
    {
        return $summary->filter_params[$key] ?? null;
    }

    private function metadata(AiSummary $summary): array
    {
        $ids = $this->complaintIds($summary);

        return [
            'id' => $summary->id,
            'url' => route('proprietor.complaints.summaries.show', $summary),
            'complaint_count' => (int) ($this->param($summary, 'complaint_count') ?? count($ids)),
            'complaints_url' => route('proprietor.complaints.index', ['complaint_ids' => $ids]),
            'categories' => (array) ($this->param($summary, 'categories') ?? $this->param($summary, 'category') ?? []),
            'themes' => $this->presentThemes($summary),
            'created_at' => $summary->created_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->format('M d, Y, g:i A'),
            'created_ago' => $summary->created_at->diffForHumans(),
        ];
    }

    private function detail(AiSummary $summary, Collection $complaints): array
    {
        $date = fn (?string $value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('M d, Y') : null;

        return [
            ...$this->metadata($summary),
            'period_start' => $date($this->param($summary, 'period_start') ?? $this->param($summary, 'date_from')),
            'period_end' => $date($this->param($summary, 'period_end') ?? $this->param($summary, 'date_to')),
            'summary' => $this->text($summary),
            'groups' => $this->presentCategories($summary),
            'recategorized' => $this->presentRecategorized($summary, $complaints),
            'recommended_action' => $this->analysis($summary)['recommended_action'] ?? null,
            'complaints_missing' => $complaints->count() < count($this->complaintIds($summary)),
            'complaints' => $complaints->map(fn (Complaint $complaint) => [
                'tenant' => $complaint->tenant?->name ?? 'Tenant unavailable',
                'category' => $complaint->category,
                'date' => $complaint->created_at->copy()->setTimezone(self::DISPLAY_TIMEZONE)->format('M d, Y'),
            ])->all(),
        ];
    }

    /** The AI's grouped answer saved in summary_result: {categories, themes, recategorized, recommended_action}. */
    private function analysis(AiSummary $summary): array
    {
        $stored = json_decode((string) $summary->summary_result, true);

        return is_array($stored) ? (array_is_list($stored) ? ['themes' => $stored] : $stored) : [];
    }

    /** Plain-text version for the history list: one line per category, then the most urgent issue. */
    private function text(AiSummary $summary): string
    {
        $analysis = $this->analysis($summary);
        if (($analysis['themes'] ?? []) === []) {
            return strip_tags((string) $summary->summary_result);
        }

        return collect($this->presentCategories($summary))
            ->map(fn ($group) => $group['label'].': '.collect($group['issues'])->map(fn ($issue) => $issue['theme'].' ('.$issue['count'].')')->implode(', '))
            ->when(is_string($analysis['recommended_action'] ?? null), fn ($lines) => $lines->push('Most urgent: '.$analysis['recommended_action']))
            ->implode("\n");
    }

    private function presentThemes(AiSummary $summary): array
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
    private function presentCategories(AiSummary $summary): array
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
    private function presentRecategorized(AiSummary $summary, Collection $complaints): array
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
