<?php

namespace App\Http\Controllers\Proprietor;

use App\Http\Controllers\Controller;
use App\Services\PaymentRiskService;
use App\Services\SettingsService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function edit()
    {
        $general = $this->settings->getGroup('general');

        $riskThresholds = app(PaymentRiskService::class)->thresholds();
        $guardianSms = app(\App\Services\GuardianSmsMessage::class);
        $guardianTemplate = $guardianSms->template();
        $guardianSample = $guardianSms->sample();

        return view('proprietor.settings.index', compact('general', 'riskThresholds', 'guardianTemplate', 'guardianSample'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'dormitory_name' => ['required', 'string', 'max:255'],
            'billing_start_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'risk_late_payments' => ['sometimes', 'required', 'integer', 'min:1', 'max:255'],
            'risk_consecutive_months' => ['sometimes', 'required', 'integer', 'min:1', 'max:255'],
            'risk_average_days' => ['sometimes', 'required', 'integer', 'min:0', 'max:3650'],
            'risk_recent_default_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:3650'],
            'sms_guardian_template' => ['sometimes', 'nullable', 'string', 'max:'.\App\Services\GuardianSmsMessage::MAX_LENGTH],
        ]);

        foreach ($validated as $key => $value) {
            if ($key === 'sms_guardian_template') {
                // Blank (or "Reset to default") goes back to the built-in wording.
                $text = str_replace("\r\n", "\n", trim((string) $value));
                $this->settings->set('sms', 'guardian_template', $text === '' || $text === \App\Services\GuardianSmsMessage::DEFAULT_TEMPLATE ? null : $text);
            } elseif (str_starts_with($key, 'risk_')) {
                $this->settings->set('risk', substr($key, 5), (string) $value);
            } else {
                $this->settings->set('general', $key, $value);
            }
        }

        return redirect()
            ->route('proprietor.settings.edit')
            ->with('success', 'Settings updated.');
    }
}
