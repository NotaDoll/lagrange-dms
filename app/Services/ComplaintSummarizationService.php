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

        $facts = $this->computeFacts($complaints);
        $prompt = $this->buildPrompt($complaints, $facts);
        $text = $this->summarizePrompt($prompt, $facts)
            ?? "AI summary unavailable (check your API key and laravel.log).\n\n".$this->formatFacts($facts);

        return AiSummary::create([
            'generated_by'          => $options['generated_by'] ?? null,
            'filter_params'         => collect($options)->except('generated_by')->all(),
            'prompt_used'           => $prompt,
            'summary_result'        => $text,
            'response_time_seconds' => round(microtime(true) - $startedAt, 2),
        ]);
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

    private function summarizeWithAi(string $prompt): ?string
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
