<?php

namespace Tests\Feature;

use App\Enums\PayrollCountry;
use App\Enums\SalaryCalculation;
use App\Enums\SalaryComponentType;
use App\Models\Employee;
use App\Models\EmployeeSalaryAssignment;
use App\Models\EmployeeSalaryComponentValue;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Services\Payroll\SalaryCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class SalaryCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private SalaryStructure $structure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->structure = SalaryStructure::create([
            'owner_id' => $this->owner->id,
            'name' => 'India standard',
            'country' => PayrollCountry::India,
            'currency_code' => 'INR',
        ]);
    }

    private function makeComponent(array $attributes): SalaryComponent
    {
        return SalaryComponent::create([
            'owner_id' => $this->owner->id,
            'salary_structure_id' => $this->structure->id,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function assignment(float $basic = 50000): EmployeeSalaryAssignment
    {
        $employee = Employee::create([
            'owner_id' => $this->owner->id,
            'name' => 'Grace Hopper',
            'email' => 'grace'.uniqid().'@example.com',
            'role' => 'member',
            'status' => 'active',
        ]);

        return EmployeeSalaryAssignment::create([
            'owner_id' => $this->owner->id,
            'staff_id' => $employee->id,
            'salary_structure_id' => $this->structure->id,
            'basic_salary' => $basic,
            'currency_code' => 'INR',
            'country' => PayrollCountry::India,
            'effective_from' => now()->startOfMonth()->toDateString(),
            'status' => 'active',
        ]);
    }

    public function test_fixed_and_percentage_earnings_build_the_gross(): void
    {
        $this->makeComponent(['code' => 'HRA', 'name' => 'House Rent Allowance', 'type' => SalaryComponentType::Earning, 'calculation' => SalaryCalculation::PercentOfBasic, 'value' => 40, 'sort_order' => 1]);
        $this->makeComponent(['code' => 'TRANSPORT', 'name' => 'Transport', 'type' => SalaryComponentType::Earning, 'calculation' => SalaryCalculation::Fixed, 'value' => 2500, 'sort_order' => 2]);

        $result = app(SalaryCalculator::class)->calculate($this->assignment(50000));

        // 50,000 basic + 20,000 HRA + 2,500 transport
        $this->assertSame(22500.0, $result->totalEarnings());
        $this->assertSame(72500.0, $result->grossSalary());
        $this->assertSame(72500.0, $result->netSalary());
    }

    public function test_deductions_may_be_a_percentage_of_gross(): void
    {
        $this->makeComponent(['code' => 'HRA', 'name' => 'HRA', 'type' => SalaryComponentType::Earning, 'calculation' => SalaryCalculation::PercentOfBasic, 'value' => 40]);
        $this->makeComponent(['code' => 'PF', 'name' => 'Provident Fund', 'type' => SalaryComponentType::Deduction, 'calculation' => SalaryCalculation::PercentOfBasic, 'value' => 12, 'is_statutory' => true]);
        $this->makeComponent(['code' => 'ESIC', 'name' => 'ESIC', 'type' => SalaryComponentType::Deduction, 'calculation' => SalaryCalculation::PercentOfGross, 'value' => 0.75, 'is_statutory' => true]);

        $result = app(SalaryCalculator::class)->calculate($this->assignment(50000));

        // Gross 70,000. PF is 12% of basic = 6,000; ESIC is 0.75% of gross = 525.
        $this->assertSame(70000.0, $result->grossSalary());
        $this->assertSame(6525.0, $result->totalDeductions());
        $this->assertSame(63475.0, $result->netSalary());
    }

    public function test_an_earning_cannot_be_a_percentage_of_gross(): void
    {
        $this->makeComponent(['code' => 'CIRCULAR', 'name' => 'Circular', 'type' => SalaryComponentType::Earning, 'calculation' => SalaryCalculation::PercentOfGross, 'value' => 10]);

        // Gross includes earnings, so such an earning depends on itself. The
        // engine refuses rather than quietly choosing an interpretation.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/percentage of gross/');

        app(SalaryCalculator::class)->calculate($this->assignment());
    }

    public function test_manual_components_take_the_amount_supplied_for_the_period(): void
    {
        $this->makeComponent(['code' => 'TDS', 'name' => 'Income Tax', 'type' => SalaryComponentType::Deduction, 'calculation' => SalaryCalculation::Manual, 'value' => 0, 'is_statutory' => true]);
        $this->makeComponent(['code' => 'OVERTIME', 'name' => 'Overtime', 'type' => SalaryComponentType::Earning, 'calculation' => SalaryCalculation::Manual, 'value' => 0]);

        $calculator = app(SalaryCalculator::class);
        $assignment = $this->assignment(40000);

        $result = $calculator->calculate($assignment, ['TDS' => 3200, 'OVERTIME' => 1500]);

        $this->assertSame(41500.0, $result->grossSalary());
        $this->assertSame(3200.0, $result->totalDeductions());
        $this->assertSame(38300.0, $result->netSalary());

        // Omitted means nothing this period, not last period's figure.
        $none = $calculator->calculate($assignment);
        $this->assertSame(0.0, $none->totalDeductions());
        $this->assertSame(40000.0, $none->grossSalary());
    }

    public function test_employer_contributions_appear_but_do_not_reduce_net_pay(): void
    {
        $this->makeComponent(['code' => 'SUPER', 'name' => 'Superannuation', 'type' => SalaryComponentType::EmployerContribution, 'calculation' => SalaryCalculation::PercentOfBasic, 'value' => 11.5]);

        $result = app(SalaryCalculator::class)->calculate($this->assignment(60000));

        $this->assertSame(6900.0, $result->totalEmployerContributions());
        $this->assertCount(1, $result->employerContributions());
        // The employee still takes home the full gross.
        $this->assertSame(60000.0, $result->netSalary());
        $this->assertSame(0.0, $result->totalDeductions());
    }

    public function test_a_per_employee_value_overrides_the_structure_default(): void
    {
        $hra = $this->makeComponent(['code' => 'HRA', 'name' => 'HRA', 'type' => SalaryComponentType::Earning, 'calculation' => SalaryCalculation::PercentOfBasic, 'value' => 40]);
        $assignment = $this->assignment(50000);

        EmployeeSalaryComponentValue::create([
            'employee_salary_assignment_id' => $assignment->id,
            'salary_component_id' => $hra->id,
            'value' => 50,
        ]);

        $result = app(SalaryCalculator::class)->calculate($assignment->fresh());

        // 50% of basic, not the structure's 40%.
        $this->assertSame(25000.0, $result->totalEarnings());
    }

    public function test_totals_are_summed_from_rounded_lines_so_a_payslip_adds_up(): void
    {
        // Values chosen to produce repeating decimals before rounding.
        $this->makeComponent(['code' => 'A', 'name' => 'A', 'type' => SalaryComponentType::Earning, 'calculation' => SalaryCalculation::PercentOfBasic, 'value' => 33.333]);
        $this->makeComponent(['code' => 'B', 'name' => 'B', 'type' => SalaryComponentType::Earning, 'calculation' => SalaryCalculation::PercentOfBasic, 'value' => 16.667]);

        $result = app(SalaryCalculator::class)->calculate($this->assignment(12345.67));

        $printed = array_sum(array_map(fn ($line) => $line->amount, $result->earnings()));

        // What is printed on the slip must equal the stated total exactly.
        $this->assertSame($result->totalEarnings(), round($printed, 2));
        $this->assertSame(
            $result->grossSalary(),
            round($result->basicSalary + $result->totalEarnings(), 2),
        );
    }

    public function test_workspace_wide_components_apply_alongside_structure_components(): void
    {
        $this->makeComponent(['code' => 'HRA', 'name' => 'HRA', 'type' => SalaryComponentType::Earning, 'calculation' => SalaryCalculation::PercentOfBasic, 'value' => 40]);

        // Not tied to any structure, so it applies to everyone.
        SalaryComponent::create([
            'owner_id' => $this->owner->id,
            'salary_structure_id' => null,
            'code' => 'PT',
            'name' => 'Professional Tax',
            'type' => SalaryComponentType::Deduction,
            'calculation' => SalaryCalculation::Fixed,
            'value' => 200,
            'is_statutory' => true,
            'is_active' => true,
        ]);

        $result = app(SalaryCalculator::class)->calculate($this->assignment(50000));

        $this->assertSame(200.0, $result->totalDeductions());
        $this->assertSame(69800.0, $result->netSalary());
    }

    public function test_inactive_components_are_ignored(): void
    {
        $this->makeComponent(['code' => 'OLD', 'name' => 'Retired allowance', 'type' => SalaryComponentType::Earning, 'calculation' => SalaryCalculation::Fixed, 'value' => 5000, 'is_active' => false]);

        $result = app(SalaryCalculator::class)->calculate($this->assignment(30000));

        $this->assertSame(0.0, $result->totalEarnings());
        $this->assertSame(30000.0, $result->netSalary());
    }
}
