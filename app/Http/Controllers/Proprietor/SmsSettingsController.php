<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSmsSettingsRequest;
use App\Services\SettingsService;
use App\Services\TextBeeGateway;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class SmsSettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function edit()
    {
        if (config('services.sms_provider') === 'textbee') {
            return view('proprietor.settings.sms', [
                'usesTextBee' => true,
                'hasApiKey' => filled(config('services.textbee.key')),
                'deviceId' => $this->settings->get('sms', 'textbee_device_id'),
                'simId' => $this->settings->get('sms', 'textbee_sim_id'),
            ]);
        }

        return view('proprietor.settings.sms', [
            'usesTextBee' => false,
            'hasApiKey' => filled($this->settings->get('sms', 'api_key') ?? config('services.semaphore.key')),
            'senderName' => $this->settings->get('sms', 'sender_name'),
        ]);
    }

    public function update(UpdateSmsSettingsRequest $request)
    {
        if (config('services.sms_provider') === 'textbee') {
            $simId = $request->validated('sim_subscription_id');
            $this->settings->set('sms', 'textbee_device_id', $request->validated('device_id'));
            $this->settings->set('sms', 'textbee_sim_id', $simId === null ? null : (string) $simId);
        } else {
            if ($request->filled('api_key')) {
                $this->settings->set('sms', 'api_key', $request->string('api_key'), secret: true);
            }

            $this->settings->set('sms', 'sender_name', $request->validated('sender_name'));
        }

        return redirect()
            ->route('proprietor.settings.sms.edit')
            ->with('success', 'SMS configuration updated.');
    }

    public function test(Request $request, TextBeeGateway $gateway)
    {
        abort_unless(config('services.sms_provider') === 'textbee', 404);
        $data = $request->validate(['test_number' => ['required', 'string', 'max:32']]);

        try {
            $result = $gateway->send($data['test_number'], 'La Grange DMS SMS gateway test.');
        } catch (InvalidArgumentException | RuntimeException $exception) {
            return redirect()->route('proprietor.settings.sms.edit')
                ->withErrors(['test_number' => $exception->getMessage()])
                ->withInput($data);
        }

        return redirect()->route('proprietor.settings.sms.edit')->with('success',
            $result['status'] === 'queued'
                ? 'Test SMS queued in TextBee. Check the recipient phone and TextBee history.'
                : 'Test SMS status is unconfirmed. Check the recipient phone and TextBee history before trying again.'
        );
    }
}
