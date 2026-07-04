<?php

namespace App\Http\Requests;

use App\Models\Bed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenant = $this->route('tenant');
        $currentBedId = $tenant?->currentAssignment?->bed_id;

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($tenant?->user_id),
            ],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'emergency_contact_name' => ['required', 'string', 'max:255'],
            'emergency_contact_number' => ['required', 'string', 'max:50'],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_contact_number' => ['nullable', 'string', 'max:50'],
            'move_in_date' => ['required', 'date'],
            'status' => ['required', 'in:active,inactive'],
            'bed_id' => [
                'nullable',
                function ($attribute, $value, $fail) use ($currentBedId) {
                    if (! $value) {
                        return; // empty = remove from room, always allowed
                    }

                    $bed = Bed::find($value);

                    if (! $bed) {
                        $fail('The selected bed does not exist.');
                        return;
                    }

                    if ($bed->status !== 'available' && (int) $bed->id !== (int) $currentBedId) {
                        $fail('The selected bed is no longer available.');
                    }
                },
            ],
        ];
    }
}