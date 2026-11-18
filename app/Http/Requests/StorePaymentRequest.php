<?php

namespace App\Http\Requests;

use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Money received from a tenant. Status is not entered: recorded payments are Paid,
 * and unpaid charges become Not paid / Overdue automatically from their due date.
 */
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
            'payment_type' => ['required_without:applies_to', Rule::in(array_keys(Payment::PAYMENT_TYPE_LABELS))],
            'applies_to' => ['required_without:payment_type', Rule::in([Payment::RENT, Payment::UTILITIES])],
            'utility_payment_id' => ['required_if:applies_to,utilities', 'nullable', 'integer'],
            'apply_excess' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:99999999.99'],
            // Due date is not entered: it follows the tenant's move-in day (see PaymentLedgerService::targetMonth).
            'receipt_number' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9#\/\-. ]+$/', Rule::unique('payments', 'receipt_number')],
            'paid_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.Tenant::localDate()],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_type.required' => 'Choose whether this is a Full, Partial, or Advance payment.',
            'paid_date.before_or_equal' => 'The paid date cannot be in the future.',
            'receipt_number.required' => 'Enter the receipt number from the receipt you gave the tenant.',
            'receipt_number.unique' => 'This receipt number is already used on another payment.',
            'receipt_number.regex' => 'Use letters, numbers, spaces, and - / . # only.',
        ];
    }
}
