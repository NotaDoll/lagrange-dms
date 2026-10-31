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

        return view('proprietor.settings.index', compact('general', 'riskThresholds'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'dormitory_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:20'],
            'risk_late_payments' => ['sometimes', 'required', 'integer', 'min:1', 'max:255'],
            'risk_consecutive_months' => ['sometimes', 'required', 'integer', 'min:1', 'max:255'],
            'risk_average_days' => ['sometimes', 'required', 'integer', 'min:0', 'max:3650'],
            'risk_recent_default_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:3650'],
        ]);

        foreach ($validated as $key => $value) {
            if (str_starts_with($key, 'risk_')) {
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
