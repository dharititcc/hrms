<?php

namespace App\Http\Requests\Payroll;

use App\Enums\PayrollCountry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalaryAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $ownerId = $this->user()->workspaceOwnerId();

        return [
            'basic_salary' => ['required', 'numeric', 'min:0', 'max:99999999999.99'],
            'country' => ['required', Rule::enum(PayrollCountry::class)],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'effective_from' => ['required', 'date'],
            'revision_reason' => ['nullable', 'string', 'max:255'],

            'salary_structure_id' => [
                'nullable', 'integer',
                Rule::exists('salary_structures', 'id')->where('owner_id', $ownerId)->whereNull('deleted_at'),
            ],

            // Per-employee overrides, keyed by component id.
            'component_values' => ['nullable', 'array'],
            'component_values.*' => ['numeric', 'min:0'],
        ];
    }

    /** Currency follows the country unless the caller states otherwise. */
    public function currencyCode(): string
    {
        return $this->input('currency_code')
            ?? PayrollCountry::from($this->input('country'))->currencyCode();
    }
}
