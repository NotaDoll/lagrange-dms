<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'exists:tenants,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'due_date' => ['required', 'date'],
            'paid_date' => ['required_if:status,paid', 'prohibited_unless:status,paid', 'nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'status' => ['required', Rule::in(['pending', 'paid', 'overdue'])],
        ];
    }
}
