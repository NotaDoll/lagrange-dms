<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SmsService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function send(string $number, string $message, ?Tenant $recipientTenant = null): bool
    {
        $normalizedNumber = $this->normalizeNumber($number);
        $tenant = $recipientTenant ?? Tenant::with('user')->get()->first(function (Tenant $tenant) use ($normalizedNumber) {
            return $this->normalizeNumber($tenant->user?->contact_number) === $normalizedNumber
                || $this->normalizeNumber($tenant->emergency_contact_number) === $normalizedNumber
                || $this->normalizeNumber($tenant->guardian_contact_number) === $normalizedNumber;
        });

        $recipientType = ! $recipientTenant && $tenant && $this->normalizeNumber($tenant->guardian_contact_number) === $normalizedNumber
            ? 'guardian'
            : 'tenant';

        $status = 'failed';

        try {
            $apiKey = $this->settings->get('sms', 'api_key') ?? config('services.semaphore.key');

            $response = Http::asForm()->connectTimeout(5)->timeout(15)->post('https://api.semaphore.co/api/v4/messages', [
                'apikey' => $apiKey,
                'number' => $number,
                'message' => $message,
                'sendername' => $this->settings->get('sms', 'sender_name'),
            ]);

            $status = $response->successful() ? 'sent' : 'failed';
        } catch (\Throwable $e) {
            $status = 'failed';
        }

        SmsLog::create([
            'tenant_id' => $tenant?->id ?? Tenant::query()->value('id'),
            'recipient_number' => $number,
            'recipient_type' => $recipientType,
            'message' => $message,
            'status' => $status,
            'sent_at' => now(),
        ]);

        return $status === 'sent';
    }

    private function normalizeNumber(?string $number): string
    {
        return (string) Str::of((string) $number)->replaceMatches('/\D+/', '');
    }
}
