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
        return [
            'api_key' => ['nullable', 'string', 'max:255'],
            'sender_name' => ['nullable', 'string', 'max:50'],
        ];
    }
}