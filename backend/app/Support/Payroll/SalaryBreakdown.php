<?php

namespace App\Support\Payroll;

use App\Enums\SalaryComponentType;

/**
 * The full result of calculating one employee's pay for one period.
 *
 * Totals are derived from the rounded lines rather than from unrounded
 * intermediates, so the figures on a payslip add up exactly as printed.
 */
final readonly class SalaryBreakdown
{
    /** @param list<ComputedLine> $lines */
    public function __construct(
        public float $basicSalary,
        public array $lines,
        public string $currencyCode,
    ) {}

    /** @return list<ComputedLine> */
    public function earnings(): array
    {
        return $this->ofType(SalaryComponentType::Earning);
    }

    /** @return list<ComputedLine> */
    public function deductions(): array
    {
        return $this->ofType(SalaryComponentType::Deduction);
    }

    /** @return list<ComputedLine> */
    public function employerContributions(): array
    {
        return $this->ofType(SalaryComponentType::EmployerContribution);
    }

    /** Allowances only; basic salary is held separately. */
    public function totalEarnings(): float
    {
        return $this->sum($this->earnings());
    }

    public function totalDeductions(): float
    {
        return $this->sum($this->deductions());
    }

    public function totalEmployerContributions(): float
    {
        return $this->sum($this->employerContributions());
    }

    /** Basic plus every earning, before deductions. */
    public function grossSalary(): float
    {
        return round($this->basicSalary + $this->totalEarnings(), 2);
    }

    /**
     * Take-home pay. Employer contributions are excluded deliberately: they
     * are a cost to the business, not money withheld from the employee.
     */
    public function netSalary(): float
    {
        return round($this->grossSalary() - $this->totalDeductions(), 2);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'currency_code' => $this->currencyCode,
            'basic_salary' => $this->basicSalary,
            'total_earnings' => $this->totalEarnings(),
            'total_deductions' => $this->totalDeductions(),
            'employer_contributions' => $this->totalEmployerContributions(),
            'gross_salary' => $this->grossSalary(),
            'net_salary' => $this->netSalary(),
            'lines' => array_map(fn (ComputedLine $line) => $line->toArray(), $this->lines),
        ];
    }

    /** @return list<ComputedLine> */
    private function ofType(SalaryComponentType $type): array
    {
        return array_values(array_filter($this->lines, fn (ComputedLine $line) => $line->type === $type));
    }

    /** @param list<ComputedLine> $lines */
    private function sum(array $lines): float
    {
        return round(array_sum(array_map(fn (ComputedLine $line) => $line->amount, $lines)), 2);
    }
}
