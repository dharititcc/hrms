<?php

namespace App\Models;

use App\Enums\PayrollCountry;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bank and tax details for one employee. Account numbers are hidden by default
 * so they are only exposed where a resource asks for them explicitly.
 */
#[Fillable([
    'owner_id', 'staff_id', 'country', 'currency_code', 'bank_name',
    'account_holder_name', 'account_number', 'bank_code', 'swift_code', 'iban',
    'tax_identifier', 'tax_regime', 'tax_notes',
])]
#[Hidden(['owner_id', 'account_number', 'iban', 'tax_identifier'])]
class EmployeePayrollProfile extends Model
{
    protected function casts(): array
    {
        return ['country' => PayrollCountry::class];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /** Last four digits, for confirming an account without exposing it. */
    public function maskedAccountNumber(): ?string
    {
        if ($this->account_number === null || $this->account_number === '') {
            return null;
        }

        return str_repeat('•', max(0, strlen($this->account_number) - 4)).substr($this->account_number, -4);
    }
}
