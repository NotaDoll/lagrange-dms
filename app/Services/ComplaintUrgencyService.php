<?php

namespace App\Services;

use App\Models\Complaint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asks the AI once per complaint whether it is urgent, and saves the answer on the complaint.
 * A complaint that already has a priority is never sent again, so each one costs a single small request.
 * If the AI is unavailable the complaint stays unchecked (priority null) and the 7-day waiting rule still applies.
 */
class ComplaintUrgencyService
{
    public const URGENT = 'urgent';

    public const NORMAL = 'normal';

    public function assess(Complaint $complaint): ?string
    {
        if ($complaint->priority !== null) {
            return $complaint->priority;
        }

        $answer = $this->ask($complaint);
        if ($answer === null) {
            return null;
        }

        $complaint->forceFill([
            'priority' => $answer['urgent'] ? self::URGENT : self::NORMAL,
            'priority_reason' => $answer['urgent'] ? $answer['reason'] : null,
        ])->save();

        return $complaint->priority;
    }

    /** @return ?array{urgent: bool, reason: string} */
    private function ask(Complaint $complaint): ?array
    {
        $key = config('services.groq.key');
        if (! $key) {
            return null;
        }

        $description = str($complaint->description)->limit(800);
        $prompt = <<<PROMPT
You check one tenant complaint for a dormitory in the Philippines. It may be in English, Bisaya/Cebuano, Tagalog/Filipino or a mix, with slang or misspellings.

Mark it urgent ONLY if it needs action today because of: a safety risk (fire, smoke, sparking or exposed wires, gas smell, injury, flooding, broken lock or door that leaves a room unsecured), no water or no electricity, a problem affecting many tenants or a whole floor, or a threat or harassment. Everything else is not urgent (minor repairs, noise, billing questions, requests).

Return only JSON: {"urgent": true or false, "reason": "at most 8 plain English words"}

Complaint (category chosen by tenant: {$complaint->category}):
{$description}
PROMPT;

        $payload = [
            'model' => config('services.groq.model'),
            'temperature' => 0,
            'max_tokens' => 300,
            'response_format' => ['type' => 'json_object'],
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];
        if (str_starts_with((string) config('services.groq.model'), 'openai/gpt-oss')) {
            $payload['reasoning_effort'] = 'low';
        }

        try {
            $response = Http::withToken($key)->timeout(20)->post('https://api.groq.com/openai/v1/chat/completions', $payload);
            if (! $response->successful()) {
                Log::warning('Complaint urgency check failed', ['complaint' => $complaint->id, 'status' => $response->status()]);

                return null;
            }
            $parsed = json_decode((string) $response->json('choices.0.message.content', ''), true);
        } catch (\Throwable $e) {
            Log::warning('Complaint urgency check error: '.$e->getMessage(), ['complaint' => $complaint->id]);

            return null;
        }

        if (! is_array($parsed) || ! is_bool($parsed['urgent'] ?? null)) {
            Log::warning('Complaint urgency check returned an unexpected answer', ['complaint' => $complaint->id]);

            return null;
        }

        return ['urgent' => $parsed['urgent'], 'reason' => trim(str((string) ($parsed['reason'] ?? ''))->limit(120))];
    }
}
