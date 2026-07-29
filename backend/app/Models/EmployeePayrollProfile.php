<?php

namespace App\Models;

use App\Enums\PayrollCountry;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bank and tax details for one employee.
 *
 * The account number, IBAN and tax identifier are encrypted at rest and hidden
 * from array conversion, so they are only ever exposed where a resource asks
 * for them by name. Everything else — bank name, sort code, SWIFT — identifies
 * an institution rather than an account and is stored plainly.
 *
 * Changing bank details is the classic payroll diversion attack, so the model
 * logs activity. The values are withheld from the log; that they changed, and
 * who changed them, is the part worth keeping.
 */
#[Fillable([
    'owner_id', 'staff_id', 'country', 'currency_code', 'bank_name',
    'account_holder_name', 'account_number', 'bank_code', 'swift_code', 'iban',
    'tax_identifier', 'tax_regime', 'tax_notes',
])]
#[Hidden(['owner_id', 'account_number', 'iban', 'tax_identifier'])]
class EmployeePayrollProfile extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'country' => PayrollCountry::class,
            // Requires the text columns from the accompanying migration:
            // ciphertext does not fit in a varchar(255).
            'account_number' => 'encrypted',
            'iban' => 'encrypted',
            'tax_identifier' => 'encrypted',
        ];
    }

    /** @return list<string> Values withheld from the activity log. */
    public function redactedAuditAttributes(): array
    {
        return ['account_number', 'iban', 'tax_identifier'];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Last four digits, for confirming an account without exposing it. */
    public function maskedAccountNumber(): ?string
    {
        return $this->mask($this->account_number);
    }

    public function maskedIban(): ?string
    {
        return $this->mask($this->iban);
    }

    public function maskedTaxIdentifier(): ?string
    {
        return $this->mask($this->tax_identifier);
    }

    /** Whether there is enough here to actually pay somebody. */
    public function isPayable(): bool
    {
        return filled($this->account_holder_name)
            && (filled($this->account_number) || filled($this->iban));
    }

    private function mask(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Short values are hidden outright: revealing the last four of a
        // five-character identifier reveals most of it.
        if (strlen($value) <= 4) {
            return str_repeat('•', strlen($value));
        }

        return str_repeat('•', strlen($value) - 4).substr($value, -4);
    }
}
