<?php

namespace App\Services;

use App\Models\AiSummary;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ComplaintSummarizationService
{
    public const MAX_COMPLAINTS_SENT = 50;

    public function summarize(Collection $complaints, array $options = []): AiSummary
    {
        $startedAt = microtime(true);

        $prompt = $this->buildGroupedPrompt($complaints);
        $grouped = $this->requestGroupedSummary($prompt, $complaints);
        if ($grouped === null) {
            // Every summary must come from the AI; nothing is saved without a valid AI answer.
            throw new \RuntimeException('The AI could not return a valid issue-to-complaint mapping. Your previous summary was kept.');
        }

        $facts = $this->computeFacts($complaints);
        $result = [
            'categories' => $grouped['categories'],
            'themes' => $grouped['themes'],
            'recategorized' => $grouped['recategorized'],
            'recommended_action' => $grouped['recommended_action'],
            'analysis_note' => sprintf('Based on %d complaint(s) · %d waiting more than 7 days · oldest %d days.',
                $facts['total'], $facts['overdue'], $facts['oldest_days']),
        ];

        return AiSummary::create([
            'generated_by'          => $options['generated_by'] ?? null,
            'filter_params'         => collect($options)->except(['generated_by', 'fallback'])->all(),
            'prompt_used'           => $prompt,
            'summary_result'        => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'response_time_seconds' => round(microtime(true) - $startedAt, 2),
        ]);
    }

    /** Return a readable summary plus the exact complaint IDs assigned to every issue. */
    public function generateWithThemes(Collection $complaints): ?array
    {
        $prompt = $this->buildGroupedPrompt($complaints);
        $grouped = $this->requestGroupedSummary($prompt, $complaints);

        if ($grouped === null) {
            return null;
        }

        // Plain-text version (used for the history preview): one line per category, then the urgent action.
        $summary = collect($grouped['categories'])
            ->map(fn ($group) => $group['label'].': '.collect($group['issues'])
                ->map(fn ($theme) => $theme['theme'].' ('.$theme['count'].')')->implode(', '))
            ->push('Most urgent: '.$grouped['recommended_action'])
            ->implode("\n");

        return [...$grouped, 'summary' => $summary];
    }

    public function generate(Collection $complaints): ?string
    {
        $facts = $this->computeFacts($complaints);

        return $this->summarizePrompt($this->buildPrompt($complaints, $facts), $facts);
    }

    private function summarizePrompt(string $prompt, array $facts): ?string
    {
        $narrative = $this->summarizeWithAi($prompt);

        return $narrative === null ? null : $narrative."\n\n".sprintf(
            'Based on %d complaint(s) · %d waiting more than 7 days · oldest %d days.',
            $facts['total'],
            $facts['overdue'],
            $facts['oldest_days']
        );
    }

    private function age($complaint): int
    {
        return (int) now()->diffInDays($complaint->created_at, true);
    }

    private function computeFacts(Collection $complaints): array
    {
        return [
            'total'       => $complaints->count(),
            'by_category' => $complaints->groupBy('category')->map->count()->sortDesc()->all(),
            'by_status'   => $complaints->groupBy('status')->map->count()->sortDesc()->all(),
            'overdue'     => $complaints->filter(fn ($c) => $this->age($c) > 7)->count(),
            'oldest_days' => $complaints->map(fn ($c) => $this->age($c))->max() ?? 0,
            'analyzed'    => min($complaints->count(), self::MAX_COMPLAINTS_SENT),
        ];
    }

    private function formatFacts(array $f): string
    {
        $join = fn (array $items) => collect($items)
            ->map(fn ($count, $name) => str_replace('_', ' ', $name)." ($count)")
            ->implode(', ');

        $lines = [
            "Total complaints: {$f['total']}",
            'By category: '.$join($f['by_category']),
            'By status: '.$join($f['by_status']),
            "Waiting more than 7 days: {$f['overdue']}",
            "Oldest complaint: {$f['oldest_days']} days",
        ];

        if ($f['total'] > $f['analyzed']) {
            $lines[] = "Note: the analysis covers {$f['analyzed']} of {$f['total']} complaints.";
        }

        return implode("\n", $lines);
    }

    private function buildPrompt(Collection $complaints, array $facts): string
    {
        $lines = $complaints->take(self::MAX_COMPLAINTS_SENT)->map(fn ($c, $i) => sprintf(
            '%d. [%s | %s | %d days old] %s',
            $i + 1,
            $c->category,
            str_replace('_', ' ', $c->status),
            $this->age($c),
            str($c->description)->limit(500)
        ))->implode("\n");

        return <<<PROMPT
You help a dormitory proprietor in the Philippines. Read the tenant complaints below and explain what tenants are actually reporting.

LANGUAGE:
- Complaints may be written in English, Bisaya (Cebuano), Tagalog/Filipino, a mix of these (Taglish, Bislish), or any other language, including with slang, abbreviations, texting shortcuts and spelling mistakes.
- Understand each complaint in its original language. Never call a complaint unreadable just because it is not in English.
- Write the summary in English. Complaints in different languages that describe the same problem belong in the same issue.

INSTRUCTIONS:
- Group similar complaints into issues. For each issue, say what the problem is, where (room or area) and when if the tenant mentioned it, and how many complaints mention it.
- Use details only if they appear in the complaint text (room numbers, items, times, amounts). Never invent details.
- Only complaints that are random characters or have no meaning in any language count as unreadable. Mention once how many.
- Finish with one line: the most urgent issue and a suggested first action.
- Plain text, short lines starting with "- ", under 150 words. No markdown headings.

COMPLAINTS:
{$lines}
PROMPT;
    }

    private function buildGroupedPrompt(Collection $complaints): string
    {
        $lines = $complaints->take(self::MAX_COMPLAINTS_SENT)->map(fn ($complaint) => sprintf(
            'ID %d | tenant chose: %s | %s | %d days old | %s',
            $complaint->id,
            $complaint->category,
            str_replace('_', ' ', $complaint->status),
            $this->age($complaint),
            str($complaint->description)->limit(500)
        ))->implode("\n");
        $categories = implode(', ', array_keys(self::CATEGORY_LABELS));

        return <<<PROMPT
You help a dormitory proprietor in the Philippines sort and summarize tenant complaints.

Understand English, Bisaya/Cebuano, Tagalog/Filipino, mixed language, slang, abbreviations, and spelling mistakes. Use only details stated in the complaints; do not invent rooms, dates, amounts, or facts.

STEP 1. Decide the correct category of every complaint from what it actually says, ignoring the category the tenant chose (tenants sometimes pick the wrong one). Categories:
- maintenance: something broken, leaking, dirty or not working (water, electricity, plumbing, doors, beds, Wi-Fi, pests, cleanliness)
- noise: loud neighbors, music, parties, disturbances at night
- billing: rent, payments, receipts, balances, charges, deposits, utility bills
- other: anything else, including meaningless text

STEP 2. Within each category, group complaints that describe the same problem into one issue.

Return only valid JSON with this exact shape:
{"complaints":[{"id":101,"category":"billing"}],"issues":[{"category":"billing","issue":"Short issue name","detail":"One plain sentence: what is wrong, and where or when if stated.","complaint_ids":[101]}],"recommended_action":"One plain sentence: the single most urgent issue and the first thing the proprietor should do."}

Rules:
- "complaints" lists every ID below exactly once; category is one of: {$categories}.
- Every issue's category matches the category you gave each of its complaints, and every complaint appears in at least one issue.
- Use only the integer IDs below. Issue names are 2 to 5 plain English words, no jargon. Do not add other fields or markdown.

COMPLAINTS:
{$lines}
PROMPT;
    }

    public const CATEGORY_LABELS = [
        'maintenance' => 'Maintenance',
        'noise' => 'Noise',
        'billing' => 'Billing',
        'other' => 'Other',
    ];

    /**
     * Ask the AI to categorize and group the complaints, then check every ID and category it returns.
     * Counts, the category order and the list of miscategorized complaints are worked out here, not by the AI.
     *
     * @return ?array{categories: list<array>, themes: list<array>, recategorized: list<array>, recommended_action: string}
     */
    private function requestGroupedSummary(string $prompt, Collection $complaints): ?array
    {
        $raw = $this->summarizeWithAi($prompt, json: true);
        if ($raw === null) {
            return null;
        }

        $parsed = json_decode(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($raw)), true);
        if (! is_array($parsed) || ! is_array($parsed['complaints'] ?? null) || ! is_array($parsed['issues'] ?? null)
            || ! is_string($parsed['recommended_action'] ?? null) || trim($parsed['recommended_action']) === '') {
            return $this->rejected('the answer did not match the expected JSON format');
        }

        $source = $complaints->take(self::MAX_COMPLAINTS_SENT)->keyBy(fn ($complaint) => (int) $complaint->getKey());
        $isId = fn ($id) => (is_int($id) || (is_string($id) && ctype_digit($id))) && $source->has((int) $id);

        // Step 1: one category per complaint.
        $aiCategory = [];
        foreach ($parsed['complaints'] as $item) {
            $id = $item['id'] ?? null;
            $category = is_string($item['category'] ?? null) ? strtolower(trim($item['category'])) : null;
            if (! $isId($id) || ! isset(self::CATEGORY_LABELS[$category]) || isset($aiCategory[(int) $id])) {
                return $this->rejected('a complaint had an unknown ID, an unknown category, or was listed twice');
            }
            $aiCategory[(int) $id] = $category;
        }
        if (count($aiCategory) !== $source->count()) {
            return $this->rejected('not every complaint was categorized');
        }

        // Step 2: issues, each inside one category.
        $themes = [];
        $covered = [];
        foreach ($parsed['issues'] as $item) {
            $name = is_string($item['issue'] ?? null) ? trim($item['issue']) : '';
            $category = is_string($item['category'] ?? null) ? strtolower(trim($item['category'])) : null;
            if ($name === '' || ! isset(self::CATEGORY_LABELS[$category]) || ! is_array($item['complaint_ids'] ?? null) || $item['complaint_ids'] === []) {
                return $this->rejected('an issue was missing its name, category or complaints');
            }
            $ids = [];
            foreach ($item['complaint_ids'] as $id) {
                if (! $isId($id) || $aiCategory[(int) $id] !== $category) {
                    return $this->rejected('an issue listed an unknown complaint or one from another category');
                }
                $ids[] = (int) $id;
            }
            $ids = array_values(array_unique($ids));
            $covered += array_fill_keys($ids, true);
            $themes[] = [
                'theme' => $name,
                'category' => $category,
                'detail' => is_string($item['detail'] ?? null) ? trim($item['detail']) : '',
                'count' => count($ids),
                'complaint_ids' => $ids,
            ];
        }
        if (count($covered) !== $source->count()) {
            return $this->rejected('not every complaint was placed in an issue');
        }

        usort($themes, fn ($a, $b) => $b['count'] <=> $a['count']);
        $categories = collect($themes)->groupBy('category')
            ->map(fn ($issues, $category) => [
                'category' => $category,
                'label' => self::CATEGORY_LABELS[$category],
                'count' => count(array_filter($aiCategory, fn ($value) => $value === $category)),
                'issues' => $issues->values()->all(),
            ])
            ->sortByDesc('count')->values()->all();

        $recategorized = $source->filter(fn ($complaint, $id) => $complaint->category !== $aiCategory[$id])
            ->map(fn ($complaint, $id) => ['complaint_id' => $id, 'filed_as' => $complaint->category, 'category' => $aiCategory[$id]])
            ->values()->all();

        return [
            'categories' => $categories,
            'themes' => $themes,
            'recategorized' => $recategorized,
            'recommended_action' => trim($parsed['recommended_action']),
        ];
    }

    private function rejected(string $reason): null
    {
        Log::warning('Complaint summary rejected: '.$reason.'.');

        return null;
    }

    private function summarizeWithAi(string $prompt, bool $json = false): ?string
    {
        $key = config('services.groq.key');
        if (! $key) {
            Log::warning('Complaint summary skipped: GROQ_API_KEY is not set.');
            return null;
        }

        $payload = [
            'model'       => config('services.groq.model'),
            'temperature' => 0,
            'max_tokens'  => 2048,
            'messages'    => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];

        if (str_starts_with((string) config('services.groq.model'), 'openai/gpt-oss')) {
            $payload['reasoning_effort'] = 'low';
        }
        if ($json) {
            // Groq's JSON mode: the model must answer with a single valid JSON object.
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = Http::withToken($key)
                ->timeout(60)
                ->post('https://api.groq.com/openai/v1/chat/completions', $payload);

            if ($response->successful()) {
                return trim($response->json('choices.0.message.content', '')) ?: null;
            }

            Log::warning('Complaint summary API failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Complaint summary error: '.$e->getMessage());
        }

        return null;
    }
}
