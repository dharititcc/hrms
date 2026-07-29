<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordSalaryPaymentRequest extends FormRequest
{
    /** How the money left, for reconciling against a bank statement. */
    public const METHODS = ['bank_transfer', 'cash', 'cheque', 'card', 'other'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            // The ceiling is the slip's outstanding balance, which needs the
            // slip to check, so the service enforces it rather than this.
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999999.99'],
            // Backdating is normal: payroll is often recorded after the fact.
            'paid_at' => ['nullable', 'date', 'before_or_equal:now'],
            'method' => ['nullable', Rule::in(self::METHODS)],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return ['paid_at.before_or_equal' => 'A payment cannot be recorded in the future.'];
    }
}
