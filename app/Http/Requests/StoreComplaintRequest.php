<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComplaintRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['required', Rule::in(['maintenance', 'noise', 'billing', 'other'])],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
