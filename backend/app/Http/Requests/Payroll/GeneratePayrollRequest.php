<?php

namespace App\Http\Requests\Payroll;

use App\Enums\PayrollCountry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneratePayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'country' => ['required', Rule::enum(PayrollCountry::class)],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'pay_date' => ['nullable', 'date', 'after_or_equal:period_end'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Progressive taxes and one-off figures, keyed by employee id then by
            // component code: {"12": {"TDS": 3200}}
            'manual_amounts' => ['nullable', 'array'],
            'manual_amounts.*' => ['array'],
            'manual_amounts.*.*' => ['numeric'],
        ];
    }

    /** @return array<int, array<string, float>> */
    public function manualAmounts(): array
    {
        $amounts = [];

        foreach ((array) $this->input('manual_amounts', []) as $employeeId => $components) {
            $amounts[(int) $employeeId] = array_map(fn ($value) => (float) $value, (array) $components);
        }

        return $amounts;
    }
}
