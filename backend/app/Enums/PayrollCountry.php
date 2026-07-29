<?php

namespace App\Enums;

/**
 * Countries payroll can be run for. Each has its own currency, date format and
 * statutory deductions, defined in config/payroll.php.
 */
enum PayrollCountry: string
{
    case India = 'IN';
    case UnitedStates = 'US';
    case UnitedKingdom = 'GB';
    case UnitedArabEmirates = 'AE';
    case Canada = 'CA';
    case Australia = 'AU';
    case Singapore = 'SG';

    /** @return array<string, mixed> */
    public function config(): array
    {
        return config("payroll.countries.{$this->value}", []);
    }

    public function label(): string
    {
        return $this->config()['name'] ?? $this->value;
    }

    public function currencyCode(): string
    {
        return $this->config()['currency_code'] ?? 'USD';
    }

    public function currencySymbol(): string
    {
        return $this->config()['currency_symbol'] ?? '$';
    }

    public function dateFormat(): string
    {
        return $this->config()['date_format'] ?? 'Y-m-d';
    }

    /**
     * What this country calls its tax identifier: PAN, SSN, National Insurance
     * number. Showing "Tax identifier" everywhere would leave people guessing
     * which of their several numbers is wanted.
     */
    public function taxIdLabel(): string
    {
        return $this->config()['tax_id_label'] ?? 'Tax identifier';
    }

    /** IFSC code, routing number, sort code — same reasoning. */
    public function bankCodeLabel(): string
    {
        return $this->config()['bank_code_label'] ?? 'Bank code';
    }

    /** Statutory components the country mandates, as component definitions. */
    public function statutoryComponents(): array
    {
        return $this->config()['statutory_components'] ?? [];
    }

    public function hasIncomeTax(): bool
    {
        return (bool) ($this->config()['has_income_tax'] ?? true);
    }

    public function format(float|string $amount): string
    {
        return $this->currencySymbol().number_format((float) $amount, 2);
    }
}
