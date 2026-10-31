<?php

namespace App\Services;

use App\Models\AiSummary;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ComplaintSummarizationService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function summarize(Collection $complaints, array $filters): AiSummary
    {
        $prompt = $this->buildPrompt($complaints);

        if ($filters['structured'] ?? false) {
            $prompt .= "\nReturn only valid JSON: {\"themes\":[{\"theme\":\"specific theme\",\"count\":1}],\"recommended_action\":\"specific recommended action\"}. Counts must count actual supplied complaints supporting each theme, never exceed the supplied complaint count. Order themes by count. Do not include markdown fences.";
        }

        $startedAt = microtime(true);

        $response = Http::connectTimeout(5)->timeout(25)->withToken($this->settings->get('ai', 'api_key') ?? config('services.groq.key'))
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => $this->settings->get('ai', 'model') ?? config('services.groq.model'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.3,
                'max_tokens' => 1000,
            ]);

        $responseTimeSeconds = round(microtime(true) - $startedAt, 2);
        $response->throw();

        $content = data_get($response->json(), 'choices.0.message.content', '');
        if (! is_string($content) || trim($content) === '') {
            throw new \RuntimeException('The AI provider returned an empty summary.');
        }
        if ($filters['structured'] ?? false) {
            $parsed = json_decode($content, true);
            if (! is_array($parsed) || empty($parsed['themes']) || ! is_array($parsed['themes']) || ! is_string($parsed['recommended_action'] ?? null)) {
                throw new \RuntimeException('The AI provider returned an invalid structured summary.');
            }
            foreach ($parsed['themes'] as $theme) {
                if (! is_array($theme) || ! is_string($theme['theme'] ?? null) || ! is_int($theme['count'] ?? null) || $theme['count'] < 1 || $theme['count'] > $complaints->count()) {
                    throw new \RuntimeException('The AI provider returned invalid theme counts.');
                }
            }
            $filters['complaint_count'] = $complaints->count();
        }

        return AiSummary::create([
            'generated_by' => $filters['generated_by'],
            'filter_params' => Arr::only($filters, ['category', 'date_from', 'date_to', 'status', 'complaint_count']),
            'prompt_used' => $prompt,
            'summary_result' => $content,
            'response_time_seconds' => $responseTimeSeconds,
        ]);
    }

    private function buildPrompt(Collection $complaints): string
    {
        $categoryCounts = $complaints
            ->groupBy('category')
            ->map(fn ($group) => $group->count())
            ->map(fn ($count, $category) => strtoupper($category).": {$count}")
            ->implode("\n");

        $listedComplaints = $complaints
            ->map(function ($complaint, $i) {
                $date = $complaint->created_at->format('M j, Y');

                return ($i + 1).". [{$complaint->category}, {$date}] {$complaint->description}";
            })
            ->implode("\n");

        return "You are an assistant of a dormitory owner in the Philippines. Here are\n"
            ."{$complaints->count()} complaints from tenants, each labeled with its category\n"
            ."and the date it was filed. Give your concise summary in English (but keep the\n"
            ."Filipino or Bisaya terms if any are necessary for clarification).\n\n"
            ."ACTUAL COMPLAINT COUNTS PER CATEGORY (use these exact numbers, do not\n"
            ."recount or estimate them yourself):\n"
            ."{$categoryCounts}\n\n"
            ."COMPLAINTS:\n"
            ."{$listedComplaints}\n\n"
            ."In your summary, mention:\n"
            ."(1) the specific recurring issues within each category that appears most\n"
            ."    frequently — name the actual problem (e.g. \"repeated reports of a leaking\n"
            ."    faucet in the shared bathroom\"), not a generic label like \"maintenance\n"
            ."    issues\";\n"
            ."(2) the number of complaints per type, using the exact counts given above;\n"
            ."(3) suggested courses of action for the manager, tied directly to the specific\n"
            ."    issues you identified in (1).\n\n"
            ."Be specific and reference actual details from the complaints rather than\n"
            ."generic statements. Do not copy complaint sentences verbatim — synthesize\n"
            .'them into your own analysis. Keep the summary under 200 words.';
    }
}
