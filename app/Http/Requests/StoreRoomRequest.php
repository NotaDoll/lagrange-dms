<?php

namespace App\Http\Requests;

use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoomRequest extends FormRequest
{
    public const ADD_TYPE = '__add__';

    protected function prepareForValidation(): void
    {
        $newType = $this->input('new_type');
        if (is_string($newType)) {
            $this->merge(['new_type' => Room::normalizeType($newType)]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in([...Room::availableTypes(), self::ADD_TYPE])],
            'new_type' => [
                'exclude_unless:type,'.self::ADD_TYPE,
                'required',
                'string',
                'max:20',
                'regex:/^\p{L}[\p{L}\p{N} -]*$/u',
            ],
            'room_number' => ['required', 'string', 'max:234'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
            'monthly_rate' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    public function roomAttributes(): array
    {
        $validated = $this->validated();
        $type = $validated['type'] === self::ADD_TYPE ? $validated['new_type'] : $validated['type'];
        $room = new Room(['type' => $type, 'room_number' => $validated['room_number']]);

        return [
            'type' => $type,
            'room_number' => $room->displayName(),
            'capacity' => $validated['capacity'],
            'monthly_rate' => $validated['monthly_rate'],
        ];
    }
}
