<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUtilityBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'proprietor';
    }

    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'bill_type' => ['required', Rule::in(['electricity', 'water', 'internet', 'other'])],
            'other_description' => ['required_if:bill_type,other', 'nullable', 'string', 'max:100'],
            'billing_period_start' => ['required', 'date_format:Y-m-d'],
            'billing_period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:billing_period_start'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'paid_date' => ['required_if:status,paid', 'prohibited_unless:status,paid', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['pending', 'paid', 'overdue'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
