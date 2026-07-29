<?php

namespace Tests\Feature;

use App\Enums\PayrollCountry;
use App\Enums\SalaryAssignmentStatus;
use App\Enums\SalaryCalculation;
use App\Enums\SalaryComponentType;
use App\Models\EmployeeSalaryAssignment;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Models\SalarySlip;
use App\Models\SalaryStructure;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayrollGenerationTest extends TestCase
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
            'name' => 'Standard',
            'country' => PayrollCountry::India,
            'currency_code' => 'INR',
        ]);

        SalaryComponent::create([
            'owner_id' => $this->owner->id, 'salary_structure_id' => $this->structure->id,
            'code' => 'HRA', 'name' => 'HRA', 'type' => SalaryComponentType::Earning,
            'calculation' => SalaryCalculation::PercentOfBasic, 'value' => 40, 'is_active' => true,
        ]);
        SalaryComponent::create([
            'owner_id' => $this->owner->id, 'salary_structure_id' => $this->structure->id,
            'code' => 'PF', 'name' => 'Provident Fund', 'type' => SalaryComponentType::Deduction,
            'calculation' => SalaryCalculation::PercentOfBasic, 'value' => 12, 'is_statutory' => true, 'is_active' => true,
        ]);
    }

    private function staff(string $email = 'grace@example.com'): Staff
    {
        return Staff::create([
            'owner_id' => $this->owner->id, 'name' => 'Grace Hopper',
            'email' => $email, 'role' => 'member', 'status' => 'active',
        ]);
    }

    private function salaryPayload(float $basic, string $from, array $extra = []): array
    {
        return [
            'basic_salary' => $basic,
            'country' => 'IN',
            'effective_from' => $from,
            'salary_structure_id' => $this->structure->id,
            ...$extra,
        ];
    }

    // --- Salary assignments ---------------------------------------------

    public function test_a_salary_can_be_assigned_and_read_back(): void
    {
        $staff = $this->staff();
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/auth/staff/{$staff->id}/salary", $this->salaryPayload(50000, '2026-01-01'))
            ->assertCreated()
            ->assertJsonPath('data.basic_salary', '50000.00')
            // Currency follows the country when not stated.
            ->assertJsonPath('data.currency_code', 'INR')
            ->assertJsonPath('data.status', 'active');

        $this->getJson("/api/auth/staff/{$staff->id}/salary")
            ->assertOk()
            ->assertJsonPath('data.basic_salary', '50000.00');
    }

    public function test_a_revision_closes_the_previous_salary_without_gap_or_overlap(): void
    {
        $staff = $this->staff();
        Sanctum::actingAs($this->owner);

        $first = $this->postJson("/api/auth/staff/{$staff->id}/salary", $this->salaryPayload(50000, '2026-01-01'))->json('data.id');
        $this->postJson("/api/auth/staff/{$staff->id}/salary", $this->salaryPayload(60000, '2026-07-01', ['revision_reason' => 'Annual review']))
            ->assertCreated()
            ->assertJsonPath('data.supersedes_id', $first);

        $previous = EmployeeSalaryAssignment::find($first);

        // Closed the day before the revision starts: no gap, no overlap.
        $this->assertSame('2026-06-30', $previous->effective_to->toDateString());
        $this->assertSame(SalaryAssignmentStatus::Superseded, $previous->status);

        // Both revisions remain as history.
        $this->getJson("/api/auth/staff/{$staff->id}/salary/history")->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_a_revision_cannot_start_before_the_current_salary(): void
    {
        $staff = $this->staff();
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/auth/staff/{$staff->id}/salary", $this->salaryPayload(50000, '2026-06-01'))->assertCreated();

        // Backdating would leave two rows claiming the same days.
        $this->postJson("/api/auth/staff/{$staff->id}/salary", $this->salaryPayload(60000, '2026-03-01'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('effective_from');
    }

    public function test_employees_cannot_read_a_colleagues_salary(): void
    {
        $staff = $this->staff();
        $colleague = $this->staff('colleague@example.com');
        app(\App\Services\StaffInvitationService::class)->invite($staff);

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/auth/staff/{$colleague->id}/salary", $this->salaryPayload(50000, '2026-01-01'))->assertCreated();

        Sanctum::actingAs($staff->refresh()->user);
        $this->getJson("/api/auth/staff/{$colleague->id}/salary")->assertForbidden();
        // Their own is fine.
        $this->getJson("/api/auth/staff/{$staff->id}/salary")->assertOk();
    }

    // --- Run generation --------------------------------------------------

    public function test_generating_a_run_produces_slips_with_a_frozen_breakdown(): void
    {
        $staff = $this->staff();
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/auth/staff/{$staff->id}/salary", $this->salaryPayload(50000, '2026-01-01'))->assertCreated();

        $run = $this->postJson('/api/auth/payroll-runs', [
            'title' => 'July 2026', 'country' => 'IN',
            'period_start' => '2026-07-01', 'period_end' => '2026-07-31',
        ])->assertCreated();

        $run->assertJsonPath('data.slip_count', 1)
            ->assertJsonPath('data.status', 'draft')
            // Basic 50,000 + HRA 20,000 = 70,000 gross; PF 6,000; net 64,000.
            ->assertJsonPath('data.total_earnings', '20000.00')
            ->assertJsonPath('data.total_deductions', '6000.00')
            ->assertJsonPath('data.total_net', '64000.00');

        $slip = SalarySlip::firstOrFail();
        $this->assertSame('70000.00', $slip->gross_salary);
        $this->assertSame('64000.00', $slip->net_salary);
        // The breakdown is stored, not recomputed on read.
        $this->assertCount(2, $slip->lines);
    }

    public function test_a_slip_keeps_its_figures_when_the_structure_changes_later(): void
    {
        $staff = $this->staff();
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/auth/staff/{$staff->id}/salary", $this->salaryPayload(50000, '2026-01-01'))->assertCreated();
        $this->postJson('/api/auth/payroll-runs', ['title' => 'July', 'country' => 'IN', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31'])->assertCreated();

        // Change HRA after the slip was issued.
        SalaryComponent::where('code', 'HRA')->update(['value' => 10]);

        $slip = SalarySlip::firstOrFail()->fresh(['lines']);

        // Last month's payslip must not change retrospectively.
        $this->assertSame('70000.00', $slip->gross_salary);
        $this->assertSame(20000.0, (float) $slip->lines->firstWhere('code', 'HRA')->amount);
    }

    public function test_the_salary_in_force_at_period_end_is_the_one_used(): void
    {
        $staff = $this->staff();
        Sanctum::actingAs($this->owner);

        $this->postJson("/api/auth/staff/{$staff->id}/salary", $this->salaryPayload(40000, '2026-01-01'))->assertCreated();
        $this->postJson("/api/auth/staff/{$staff->id}/salary", $this->salaryPayload(80000, '2026-07-15'))->assertCreated();

        $this->postJson('/api/auth/payroll-runs', ['title' => 'July', 'country' => 'IN', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31'])
            ->assertCreated()
            // The mid-month rise applies to the whole period; pay is not prorated.
            ->assertJsonPath('data.total_net', '102400.00');
    }

    public function test_manual_amounts_are_applied_per_employee(): void
    {
        $staff = $this->staff();
        SalaryComponent::create([
            'owner_id' => $this->owner->id, 'salary_structure_id' => $this->structure->id,
            'code' => 'TDS', 'name' => 'Income Tax', 'type' => SalaryComponentType::Deduction,
            'calculation' => SalaryCalculation::Manual, 'value' => 0, 'is_statutory' => true, 'is_active' => true,
        ]);

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/auth/staff/{$staff->id}/salary", $this->salaryPayload(50000, '2026-01-01'))->assertCreated();

        $this->postJson('/api/auth/payroll-runs', [
            'title' => 'July', 'country' => 'IN',
            'period_start' => '2026-07-01', 'period_end' => '2026-07-31',
            'manual_amounts' => [$staff->id => ['TDS' => 4500]],
        ])->assertCreated()
            // PF 6,000 + TDS 4,500
            ->assertJsonPath('data.total_deductions', '10500.00');
    }

    public function test_employees_without_a_salary_are_skipped(): void
    {
        $this->staff();
        $this->staff('nosalary@example.com');
        Sanctum::actingAs($this->owner);

        $withSalary = Staff::first();
        $this->postJson("/api/auth/staff/{$withSalary->id}/salary", $this->salaryPayload(30000, '2026-01-01'))->assertCreated();

        $this->postJson('/api/auth/payroll-runs', ['title' => 'July', 'country' => 'IN', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31'])
            ->assertCreated()
            ->assertJsonPath('data.slip_count', 1);
    }

    public function test_a_draft_run_can_be_regenerated_but_an_approved_one_cannot(): void
    {
        $staff = $this->staff();
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/auth/staff/{$staff->id}/salary", $this->salaryPayload(50000, '2026-01-01'))->assertCreated();

        $runId = $this->postJson('/api/auth/payroll-runs', ['title' => 'July', 'country' => 'IN', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31'])->json('data.id');

        $this->postJson("/api/auth/payroll-runs/{$runId}/regenerate", ['title' => 'July', 'country' => 'IN', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31'])
            ->assertOk()
            // Regenerating replaces slips rather than adding to them.
            ->assertJsonPath('data.slip_count', 1);

        PayrollRun::find($runId)->update(['status' => 'approved']);

        $this->postJson("/api/auth/payroll-runs/{$runId}/regenerate", ['title' => 'July', 'country' => 'IN', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31'])
            ->assertStatus(422);

        $this->deleteJson("/api/auth/payroll-runs/{$runId}")->assertStatus(422);
    }

    public function test_runs_are_scoped_to_the_workspace(): void
    {
        $staff = $this->staff();
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/auth/staff/{$staff->id}/salary", $this->salaryPayload(50000, '2026-01-01'))->assertCreated();
        $runId = $this->postJson('/api/auth/payroll-runs', ['title' => 'July', 'country' => 'IN', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31'])->json('data.id');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/auth/payroll-runs')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/auth/payroll-runs/{$runId}")->assertForbidden();
    }
}
