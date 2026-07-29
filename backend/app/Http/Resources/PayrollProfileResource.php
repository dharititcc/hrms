<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payroll profile as the API returns it.
 *
 * The account number, IBAN and tax identifier are only ever sent masked. There
 * is nothing in the product that needs the full value in a browser: confirming
 * which account is what the last four digits are for, and paying somebody is a
 * server-side job. Anything that later needs them in full — a bank file — can
 * read the model directly.
 */
class PayrollProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'staff_id' => $this->staff_id,
            'staff_name' => $this->whenLoaded('staff', fn () => $this->staff?->name),

            'country' => $this->country->value,
            'country_label' => $this->country->label(),
            'currency_code' => $this->currency_code,

            'bank_name' => $this->bank_name,
            'account_holder_name' => $this->account_holder_name,
            'bank_code' => $this->bank_code,
            'swift_code' => $this->swift_code,

            'account_number_masked' => $this->maskedAccountNumber(),
            'iban_masked' => $this->maskedIban(),
            'tax_identifier_masked' => $this->maskedTaxIdentifier(),

            // Lets a form distinguish "never set" from "set, shown masked",
            // which decides whether the field reads as empty or as unchanged.
            'has_account_number' => filled($this->account_number),
            'has_iban' => filled($this->iban),
            'has_tax_identifier' => filled($this->tax_identifier),

            'tax_regime' => $this->tax_regime,
            'tax_notes' => $this->tax_notes,

            /*
            | Whether payroll could actually pay this person. Surfacing it here
            | means the gap is visible before a run is approved rather than
            | when a transfer fails.
            */
            'is_payable' => $this->isPayable(),

            // The form asks for "IFSC code" or "Sort code" rather than making
            // somebody work out which of their numbers is wanted.
            'labels' => [
                'tax_identifier' => $this->country->taxIdLabel(),
                'bank_code' => $this->country->bankCodeLabel(),
            ],

            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
