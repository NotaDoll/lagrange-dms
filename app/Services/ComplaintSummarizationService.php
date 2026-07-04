<?php

namespace App\Services;

use App\Models\AiSummary;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class ComplaintSummarizationService
{
    public function summarize(Collection $complaintTexts, array $filters): AiSummary
    {
        $prompt = $this->buildPrompt($complaintTexts);

        $startedAt = microtime(true);

        $response = Http::withToken(config('services.groq.key'))
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => config('services.groq.model'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.3,
                'max_tokens' => 1000,
            ]);

        $responseTimeSeconds = round(microtime(true) - $startedAt, 2);
        $response->throw();

        return AiSummary::create([
            'generated_by' => $filters['generated_by'],
            'filter_params' => Arr::only($filters, ['category', 'date_from', 'date_to']),
            'prompt_used' => $prompt,
            'summary_result' => data_get($response->json(), 'choices.0.message.content', ''),
            'response_time_seconds' => $responseTimeSeconds,
        ]);
    }

    private function buildPrompt(Collection $complaintTexts): string
    {
        return "You are an assistant of a dormitory owner in the Philippines. Here are the\n"
            . "complaints from tenants. Give your concise summary in English (but keep the\n"
            . "Filipino or Bisaya terms if any are necessary for clarification). Mention:\n"
            . "(1) types of complaints that appear most frequently, (2) number of complaints\n"
            . "per type, (3) suggested courses of action for the manager. Complaints:\n"
            . $complaintTexts->implode("\n") . ".";
    }
}
