<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'emergency_contact_name' => ['required', 'string', 'max:255'],
            'emergency_contact_number' => ['required', 'string', 'max:50'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_contact_number' => ['nullable', 'string', 'max:50'],
            'move_in_date' => ['required', 'date'],
            'status' => ['required', 'in:active,inactive'],
            'bed_id' => ['nullable', Rule::exists('beds', 'id')->where('status', 'available')],
        ];
    }

    public function messages(): array
    {
        return [
            'bed_id.exists' => 'The selected bed is no longer available. Please choose another.',
        ];
    }
}
