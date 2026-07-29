<?php

namespace App\Http\Requests\Payroll;

use App\Enums\PayrollCountry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePayrollProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'country' => ['required', Rule::enum(PayrollCountry::class)],
            'currency_code' => ['nullable', 'string', 'size:3'],

            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_holder_name' => ['nullable', 'string', 'max:255'],

            /*
            | Nullable rather than required, and omitted rather than blank means
            | "leave the stored one alone" — the API only ever returns these
            | masked, so a form cannot prefill them to send back unchanged.
            |
            | Deliberately loose on format: account numbers vary by country far
            | more than any single pattern would allow, and rejecting a valid
            | one is worse than accepting a typo the bank will bounce.
            */
            'account_number' => ['nullable', 'string', 'max:64'],
            'iban' => ['nullable', 'string', 'max:34'],
            'bank_code' => ['nullable', 'string', 'max:40'],
            'swift_code' => ['nullable', 'string', 'max:11'],

            'tax_identifier' => ['nullable', 'string', 'max:64'],
            'tax_regime' => ['nullable', 'string', 'max:100'],
            'tax_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** Currency follows the country unless the caller states otherwise. */
    public function currencyCode(): string
    {
        return $this->input('currency_code')
            ?? PayrollCountry::from($this->input('country'))->currencyCode();
    }

    /**
     * The attributes to write, with the secrets dropped when not supplied.
     *
     * Sending an empty string is how a value is cleared; omitting the key
     * entirely keeps what is already stored.
     *
     * Not named attributes(): FormRequest already uses that for validation
     * message labels, and overriding it would rename every field in an error.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $validated = $this->safe()->all();

        foreach (['account_number', 'iban', 'tax_identifier'] as $secret) {
            if (! $this->has($secret)) {
                unset($validated[$secret]);
            }
        }

        return [...$validated, 'currency_code' => $this->currencyCode()];
    }
}
