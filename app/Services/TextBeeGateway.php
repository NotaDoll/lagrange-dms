<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class TextBeeGateway
{
    public function __construct(private readonly SettingsService $settings) {}

    public function send(string $number, string $message): array
    {
        $payload = ['recipients' => [$this->formatNumber($number)], 'message' => $message];
        $deviceId = $this->settings->get('sms', 'textbee_device_id');
        $simId = $this->settings->get('sms', 'textbee_sim_id');

        if (filled($deviceId)) {
            $payload['deviceId'] = $deviceId;
        }

        if (filled($simId)) {
            $payload['simSubscriptionId'] = (int) $simId;
        }

        try {
            $response = $this->client()->post('/gateway/send-sms', $payload);
        } catch (ConnectionException) {
            return ['status' => 'unknown', 'batch_id' => null];
        }

        $this->requireSuccess($response);
        $batchId = $response->json('data.smsBatchId');

        if ($response->json('data.success') === true && is_string($batchId) && $batchId !== '') {
            return ['status' => 'queued', 'batch_id' => $batchId];
        }

        if ((int) $response->json('data.successCount', 0) === 1) {
            return ['status' => 'unknown', 'batch_id' => null];
        }

        throw new RuntimeException('TextBee did not confirm acceptance. Check its message history before sending again.');
    }

    public function messageStatus(string $batchId): ?array
    {
        try {
            $response = $this->client()->get('/gateway/messages', [
                'smsBatchId' => $batchId,
                'direction' => 'sent',
                'limit' => 1,
            ]);
        } catch (ConnectionException) {
            throw new RuntimeException('TextBee status check timed out.');
        }

        $this->requireSuccess($response);
        $message = $response->json('data.0');

        return is_array($message) ? $message : null;
    }

    private function client(): PendingRequest
    {
        $key = config('services.textbee.key');

        if (blank($key)) {
            throw new RuntimeException('Set TEXTBEE_API_KEY in the server environment.');
        }

        return Http::baseUrl('https://api.textbee.dev/api/v1')
            ->acceptJson()
            ->withHeader('x-api-key', $key)
            ->connectTimeout(5)
            ->timeout(15);
    }

    private function requireSuccess(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw new RuntimeException(match ($response->status()) {
            400, 404 => 'TextBee rejected the request. Check the recipient, verified account, and registered sending device.',
            401, 403 => 'TextBee rejected access. Check the API key and account permissions.',
            429 => 'TextBee rate or message limit reached. Try again after the limit resets.',
            default => 'TextBee is temporarily unavailable.',
        });
    }

    private function formatNumber(string $number): string
    {
        $number = preg_replace('/[\s().-]+/', '', trim($number));

        if (preg_match('/^(?:0|63)?(9[0-9]{9})$/D', $number, $parts)) {
            return '+63'.$parts[1];
        }

        if (preg_match('/^\+[1-9][0-9]{7,14}$/D', $number)) {
            return $number;
        }

        throw new InvalidArgumentException('Enter a valid mobile number such as 09171234567 or +639171234567.');
    }
}
