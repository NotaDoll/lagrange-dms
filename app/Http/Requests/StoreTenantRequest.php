<?php

namespace App\Http\Requests;

use App\Models\Room;
use App\Models\Tenant;
use App\Rules\PhilippineMobile;
use App\Services\RoomAvailability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');
        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }

        $status = $this->input('status');
        $tenant = $this->route('tenant');
        if ($status === Tenant::STATUS_RESERVED || ($status !== Tenant::STATUS_ACTIVE && ! $tenant?->actualMoveInDate())) {
            $this->merge(['move_in_date' => null]);
        } elseif ($status === Tenant::STATUS_ACTIVE && ! $this->has('move_in_date') && $tenant?->actualMoveInDate()) {
            $this->merge(['move_in_date' => $tenant->actualMoveInDate()->toDateString()]);
        }
    }

    public function rules(): array
    {
        return $this->tenantRules();
    }

    protected function tenantRules(?Tenant $tenant = null): array
    {
        $requiredOnCreate = $tenant ? 'nullable' : 'required';
        $statuses = $tenant ? array_keys(Tenant::STATUS_LABELS) : [Tenant::STATUS_RESERVED, Tenant::STATUS_ACTIVE];
        if ($tenant && ! $tenant->canBeReserved()) {
            $statuses = array_diff($statuses, [Tenant::STATUS_RESERVED]);
        }

        $roomTypes = Room::availableTypes();
        if ($tenant?->currentAssignment?->bed?->room && $tenant->currentAssignment->bed->room->catalogType() === null) {
            $roomTypes[] = RoomAvailability::LEGACY_TYPE;
        }

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'contact_number' => [$requiredOnCreate, 'string', 'max:50', new PhilippineMobile($tenant?->contact_number)],
            'home_address' => [$requiredOnCreate, 'string', 'max:5000'],
            'room_type' => ['nullable', 'required_with:room_id', 'string', Rule::in($roomTypes)],
            'room_id' => ['nullable', 'required_with:room_type', 'integer', 'exists:rooms,id'],
            'bed_id' => [$tenant && ! $tenant->currentAssignment ? 'nullable' : 'required', 'required_with:room_type,room_id', 'integer', 'exists:beds,id'],
            'move_in_date' => ['required_if:status,'.Tenant::STATUS_ACTIVE, 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.Tenant::localDate()],
            'status' => ['required', Rule::in($statuses)],
            'security_deposit' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'advance_payment' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'opening_balance' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'emergency_contact_name' => [$requiredOnCreate, 'string', 'max:255'],
            'emergency_contact_number' => [$requiredOnCreate, 'string', 'max:50', new PhilippineMobile($tenant?->emergency_contact_number)],
            'emergency_contact_relationship' => [$requiredOnCreate, Rule::in(array_keys(Tenant::EMERGENCY_RELATIONSHIPS))],
            'guardian_name' => ['nullable', 'string', 'max:255'],
            'guardian_contact_number' => ['nullable', 'string', 'max:50', new PhilippineMobile($tenant?->guardian_contact_number)],
            'data_privacy_consent' => $tenant ? ['sometimes', 'accepted'] : ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'bed_id.required' => 'Select a room and bed.',
            'bed_id.required_with' => 'Select a bed in the selected room.',
            'room_type.required_with' => 'Select a room type.',
            'room_id.required_with' => 'Select a room.',
            'move_in_date.required_if' => 'Enter a move-in date for an active tenant.',
            'move_in_date.before_or_equal' => 'The move-in date must be today or earlier.',
            'status.in' => 'Choose a status available for this tenant.',
            'bed_id.exists' => 'The selected bed is no longer available. Please choose another.',
            'data_privacy_consent.required' => 'Record the tenant or guardian’s consent before creating the tenant.',
            'data_privacy_consent.accepted' => 'Confirm that the tenant or guardian gave consent.',
        ];
    }
}
