<?php

namespace App\Services\Payroll;

use App\Enums\SalaryCalculation;
use App\Enums\SalaryComponentType;
use App\Models\EmployeeSalaryAssignment;
use App\Models\SalaryComponent;
use App\Support\Payroll\ComputedLine;
use App\Support\Payroll\SalaryBreakdown;
use InvalidArgumentException;

/**
 * Turns a salary assignment into a payslip breakdown.
 *
 * Order of calculation, which matters:
 *
 *   1. Basic salary is taken from the assignment.
 *   2. Earnings are computed. They may be fixed, a percentage of basic, or
 *      entered manually.
 *   3. Gross = basic + earnings.
 *   4. Deductions and employer contributions are computed, and only these may
 *      be a percentage of gross.
 *
 * Percentage-of-gross earnings are rejected rather than supported: an earning
 * derived from gross changes gross, which changes the earning. There is no
 * single correct answer, so the engine refuses instead of picking one
 * silently.
 *
 * Every line is rounded to two decimals as it is computed and totals are
 * summed from those rounded values, so a printed payslip adds up exactly.
 */
class SalaryCalculator
{
    /**
     * @param  array<string, float>  $manualAmounts  keyed by component code, for
     *                                               progressive taxes and one-off
     *                                               figures like overtime
     */
    public function calculate(EmployeeSalaryAssignment $assignment, array $manualAmounts = []): SalaryBreakdown
    {
        $basic = round((float) $assignment->basic_salary, 2);
        $components = $this->componentsFor($assignment);
        $overrides = $this->overridesFor($assignment);

        // Pass one: earnings, which may depend on basic but never on gross.
        $earnings = [];
        foreach ($components as $component) {
            if ($component->type !== SalaryComponentType::Earning) {
                continue;
            }

            $this->guardEarningCalculation($component);

            $earnings[] = $this->line(
                $component,
                $this->amountFor($component, $overrides, $manualAmounts, $basic, gross: null),
            );
        }

        $gross = round($basic + array_sum(array_map(fn (ComputedLine $l) => $l->amount, $earnings)), 2);

        // Pass two: everything that may depend on the gross just established.
        $others = [];
        foreach ($components as $component) {
            if ($component->type === SalaryComponentType::Earning) {
                continue;
            }

            $others[] = $this->line(
                $component,
                $this->amountFor($component, $overrides, $manualAmounts, $basic, $gross),
            );
        }

        return new SalaryBreakdown(
            basicSalary: $basic,
            lines: [...$earnings, ...$others],
            currencyCode: $assignment->currency_code,
        );
    }

    /**
     * Components in force for this assignment: those on its structure, plus
     * any workspace-wide components not tied to a structure.
     *
     * @return list<SalaryComponent>
     */
    private function componentsFor(EmployeeSalaryAssignment $assignment): array
    {
        return SalaryComponent::query()
            ->where('owner_id', $assignment->owner_id)
            ->active()
            ->where(function ($query) use ($assignment): void {
                $query->whereNull('salary_structure_id');

                if ($assignment->salary_structure_id !== null) {
                    $query->orWhere('salary_structure_id', $assignment->salary_structure_id);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    /** @return array<int, float> component id => overridden value */
    private function overridesFor(EmployeeSalaryAssignment $assignment): array
    {
        return $assignment->componentValues()
            ->pluck('value', 'salary_component_id')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    private function guardEarningCalculation(SalaryComponent $component): void
    {
        if ($component->calculation === SalaryCalculation::PercentOfGross) {
            throw new InvalidArgumentException(
                "Earning '{$component->code}' cannot be a percentage of gross: gross includes earnings, so the value would depend on itself. Use a percentage of basic, or a fixed amount."
            );
        }
    }

    /**
     * @param  array<int, float>  $overrides
     * @param  array<string, float>  $manualAmounts
     */
    private function amountFor(
        SalaryComponent $component,
        array $overrides,
        array $manualAmounts,
        float $basic,
        ?float $gross,
    ): float {
        // A per-employee value always beats the structure's default.
        $value = $overrides[$component->id] ?? (float) $component->value;

        return match ($component->calculation) {
            SalaryCalculation::Fixed => round($value, 2),
            SalaryCalculation::PercentOfBasic => round($basic * $value / 100, 2),
            SalaryCalculation::PercentOfGross => round(($gross ?? 0.0) * $value / 100, 2),
            // Supplied per payslip; absent means nothing to deduct this period.
            SalaryCalculation::Manual => round($manualAmounts[$component->code] ?? 0.0, 2),
        };
    }

    private function line(SalaryComponent $component, float $amount): ComputedLine
    {
        return new ComputedLine(
            type: $component->type,
            code: $component->code,
            name: $component->name,
            amount: $amount,
            isStatutory: $component->is_statutory,
            sortOrder: $component->sort_order,
        );
    }
}
