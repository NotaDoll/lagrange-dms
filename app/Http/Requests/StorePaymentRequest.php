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
            'paid_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['pending', 'paid', 'overdue'])],
        ];
    }
}
