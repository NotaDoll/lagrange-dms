<?php

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $room = $this->route('room');
        $number = $this->input('room_number');

        if ($room?->catalogType() && is_string($number) && trim($number) !== '') {
            $edited = new Room(['type' => $room->catalogType(), 'room_number' => $number]);
            $this->merge(['room_number' => $edited->displayName()]);
        }
    }

    public function rules(): array
    {
        $room = $this->route('room');

        return [
            'room_number' => [
                'required',
                'string',
                'max:255',
                Rule::unique('rooms', 'room_number')->ignore($room?->id),
            ],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'monthly_rate' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
}
