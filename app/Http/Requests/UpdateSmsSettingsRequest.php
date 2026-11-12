<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSmsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if (config('services.sms_provider') === 'textbee') {
            return [
                'device_id' => ['nullable', 'string', 'max:100', 'alpha_dash:ascii'],
                'sim_subscription_id' => ['nullable', 'integer', 'min:0', 'max:2147483647'],
            ];
        }

        return [
            'api_key' => ['nullable', 'string', 'max:255'],
            'sender_name' => ['nullable', 'string', 'max:50'],
        ];
    }
}