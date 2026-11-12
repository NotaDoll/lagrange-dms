<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class SmsService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly TextBeeGateway $textBee,
    ) {}

    public function send(string $number, string $message, ?Tenant $recipientTenant = null): SmsLog
    {
        $normalizedNumber = $this->normalizeNumber($number);
        $tenant = $recipientTenant ?? Tenant::with('user')->get()->first(function (Tenant $tenant) use ($normalizedNumber) {
            return $this->normalizeNumber($tenant->contact_number) === $normalizedNumber
                || $this->normalizeNumber($tenant->emergency_contact_number) === $normalizedNumber
                || $this->normalizeNumber($tenant->guardian_contact_number) === $normalizedNumber;
        });

        $recipientType = ! $recipientTenant && $tenant && $this->normalizeNumber($tenant->guardian_contact_number) === $normalizedNumber
            ? 'guardian'
            : 'tenant';

        $logData = [
            'tenant_id' => $tenant?->id ?? Tenant::query()->value('id'),
            'recipient_number' => $number,
            'recipient_type' => $recipientType,
            'message' => $message,
        ];

        if (config('services.sms_provider') === 'textbee') {
            // Create the local record before sending so a missing migration cannot trigger an untracked SMS.
            $log = SmsLog::create([...$logData, 'status' => 'failed', 'textbee_batch_id' => null]);

            try {
                $result = $this->textBee->send($number, $message);
            } catch (InvalidArgumentException | RuntimeException) {
                return $log;
            }

            $log->update(['status' => $result['status'], 'textbee_batch_id' => $result['batch_id']]);

            return $log;
        }

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

        return SmsLog::create([...$logData, 'status' => $status, 'sent_at' => now()]);
    }

    public function syncTextBeeStatuses(): int
    {
        $updated = 0;
        $logs = SmsLog::whereNotNull('textbee_batch_id')
            ->whereIn('status', ['queued', 'unknown', 'sent'])
            ->where('created_at', '>=', now()->subDays(2));

        foreach ($logs->lazyById() as $log) {
            $message = $this->textBee->messageStatus($log->textbee_batch_id);
            $status = match ($message['status'] ?? null) {
                'pending', 'dispatched' => 'queued',
                'sent', 'delivered', 'failed', 'unknown' => $message['status'],
                default => null,
            };

            if ($status === null || $status === $log->status || ($log->status === 'sent' && $status !== 'delivered')) {
                continue;
            }

            $sentAt = $log->sent_at;
            if (in_array($status, ['sent', 'delivered'], true)) {
                $sentAt = isset($message['sentAt']) ? Carbon::parse($message['sentAt'])->utc() : now();
            }

            $updated += SmsLog::whereKey($log->id)->where('status', $log->status)->update([
                'status' => $status,
                'sent_at' => $sentAt,
            ]);
        }

        return $updated;
    }

    private function normalizeNumber(?string $number): string
    {
        return (string) Str::of((string) $number)->replaceMatches('/\D+/', '');
    }
}
