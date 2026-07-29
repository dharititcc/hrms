<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account number, IBAN and tax identifier are encrypted at rest.
 *
 * Laravel's ciphertext is a base64 envelope several times the length of what
 * it wraps, so a short account number does not fit in the varchar(255) these
 * started as. Widening them to text is what makes the cast on the model
 * possible at all.
 *
 * No data migration: the endpoints that write these did not exist before this
 * change, so there are no plaintext rows to convert.
 */
return new class extends Migration
{
    private const COLUMNS = ['account_number', 'iban', 'tax_identifier'];

    public function up(): void
    {
        Schema::table('employee_payroll_profiles', function (Blueprint $table): void {
            foreach (self::COLUMNS as $column) {
                $table->text($column)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('employee_payroll_profiles', function (Blueprint $table): void {
            foreach (self::COLUMNS as $column) {
                // Anything encrypted will not survive the narrowing, which is
                // the point: rolling this back means losing those values.
                $table->string($column)->nullable()->change();
            }
        });
    }
};
