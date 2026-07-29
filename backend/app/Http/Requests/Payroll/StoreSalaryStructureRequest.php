<?php

namespace App\Http\Requests\Payroll;

use App\Enums\PayrollCountry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalaryStructureRequest extends FormRequest
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
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('salary_structures', 'name')
                    ->where('owner_id', $ownerId)
                    ->whereNull('deleted_at')
                    ->ignore($this->route('structure')),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'country' => ['required', Rule::enum(PayrollCountry::class)],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'is_active' => ['nullable', 'boolean'],

            /*
            | Copy the country's statutory deductions from config/payroll.php in
            | as components. Opt-in, and only on create: re-seeding an existing
            | structure would duplicate or silently overwrite rates someone has
            | already corrected.
            */
            'seed_statutory' => ['nullable', 'boolean'],
        ];
    }

    /** Currency follows the country unless the caller states otherwise. */
    public function currencyCode(): string
    {
        return $this->input('currency_code')
            ?? PayrollCountry::from($this->input('country'))->currencyCode();
    }
}
