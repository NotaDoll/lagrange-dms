<?php

namespace App\Services;

use App\Models\SmsLog;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class SmsService
{
    public function __construct(private readonly TextBeeGateway $textBee) {}

    public function send(string $number, string $message, ?Tenant $recipientTenant = null, ?string $recipientType = null): SmsLog
    {
        $normalizedNumber = $this->normalizeNumber($number);
        $tenant = $recipientTenant ?? Tenant::with('user')->get()->first(function (Tenant $tenant) use ($normalizedNumber) {
            return $this->normalizeNumber($tenant->contact_number) === $normalizedNumber
                || $this->normalizeNumber($tenant->guardian_contact_number) === $normalizedNumber;
        });

        $recipientType ??= ! $recipientTenant && $tenant && $this->normalizeNumber($tenant->guardian_contact_number) === $normalizedNumber
            ? 'guardian'
            : 'tenant';

        $logData = [
            'tenant_id' => $tenant?->id ?? Tenant::query()->value('id'),
            'recipient_number' => $number,
            'recipient_type' => $recipientType,
            'message' => $message,
        ];

        // All SMS go through TextBee. Create the local record before sending so a missing migration
        // cannot trigger an untracked SMS; it stays "failed" unless TextBee accepts the message.
        $log = SmsLog::create([...$logData, 'status' => 'failed', 'textbee_batch_id' => null]);

        try {
            $result = $this->textBee->send($number, $message);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            // Keep the reason (bad API key, invalid number, rate limit...) so the proprietor can fix it.
            $log->update(['error_message' => mb_substr($exception->getMessage(), 0, 255)]);

            return $log;
        }

        $log->update(['status' => $result['status'], 'textbee_batch_id' => $result['batch_id']]);

        return $log;
    }

    /**
     * Payment reminder to the tenant's guardian. Without $message, the proprietor's template from
     * Settings is filled in for this tenant; with it, the proprietor's edited text is sent as-is.
     */
    public function sendGuardianPaymentFollowUp(Tenant $tenant, ?string $message = null): SmsLog
    {
        return $this->send(
            $tenant->guardian_contact_number,
            filled($message) ? trim($message) : app(GuardianSmsMessage::class)->forTenant($tenant)['message'],
            $tenant,
            recipientType: 'guardian',
        );
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
                $sentAt = isset($message['sentAt']) ? Carbon::parse($message['sentAt'])->setTimezone(config('app.timezone')) : now();
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
