<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSmsSettingsRequest;
use App\Services\SettingsService;

class SmsSettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function edit()
    {
        $hasApiKey = filled($this->settings->get('sms', 'api_key'));
        $senderName = $this->settings->get('sms', 'sender_name');

        return view('proprietor.settings.sms', compact('hasApiKey', 'senderName'));
    }

    public function update(UpdateSmsSettingsRequest $request)
    {
        if ($request->filled('api_key')) {
            $this->settings->set('sms', 'api_key', $request->string('api_key'), secret: true);
        }

        $this->settings->set('sms', 'sender_name', $request->input('sender_name'));

        return redirect()
            ->route('proprietor.settings.sms.edit')
            ->with('success', 'SMS configuration updated.');
    }
}