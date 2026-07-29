<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSalaryAssignment;
use App\Models\PayrollRun;
use App\Models\SalaryComponent;
use App\Models\SalarySlip;
use App\Models\User;
use App\Services\EmployeeInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PayrollWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
    }

    private function staff(string $name = 'Grace', string $email = 'g@example.com'): Employee
    {
        return Employee::create([
            'owner_id' => $this->owner->id, 'name' => $name, 'email' => $email,
            'role' => 'member', 'status' => 'active',
        ]);
    }

    private function withSalary(Employee $employee, float $basic = 50000): Employee
    {
        EmployeeSalaryAssignment::create([
            'owner_id' => $this->owner->id, 'staff_id' => $employee->id, 'basic_salary' => $basic,
            'country' => 'IN', 'currency_code' => 'INR',
            'effective_from' => now()->startOfMonth()->subMonth()->toDateString(), 'status' => 'active',
        ]);

        return $employee;
    }

    /** Generates a run through the API so it is built exactly as in production. */
    private function generateRun(): array
    {
        $start = now()->startOfMonth()->subMonth();

        return $this->postJson('/api/auth/payroll-runs', [
            'title' => 'Last month',
            'country' => 'IN',
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfMonth()->toDateString(),
        ])->assertCreated()->json('data');
    }

    public function test_a_run_moves_from_draft_through_approval_to_paid(): void
    {
        $employee = $this->withSalary($this->staff());
        Sanctum::actingAs($this->owner);

        $run = $this->generateRun();
        $this->assertSame('draft', $run['status']);

        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/submit")
            ->assertOk()->assertJsonPath('data.status', 'pending_approval');

        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.is_locked', true);

        // Approval stamps who committed the figures.
        $this->assertDatabaseHas('payroll_runs', ['id' => $run['id'], 'approved_by' => $this->owner->id]);
        $slip = SalarySlip::where('staff_id', $employee->id)->firstOrFail();
        $this->assertSame('approved', $slip->status->value);

        $this->postJson("/api/auth/salary-slips/{$slip->id}/payments", ['amount' => $slip->net_salary])
            ->assertCreated();

        // Nothing outstanding anywhere, so the run itself is settled.
        $this->assertSame('paid', PayrollRun::find($run['id'])->status->value);
        $this->assertSame('paid', $slip->refresh()->status->value);
    }

    public function test_an_approved_run_cannot_be_recalculated_or_deleted(): void
    {
        $this->withSalary($this->staff());
        Sanctum::actingAs($this->owner);

        $run = $this->generateRun();
        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/approve")->assertOk();

        $payload = [
            'title' => $run['title'], 'country' => 'IN',
            'period_start' => $run['period_start'], 'period_end' => $run['period_end'],
        ];

        // People may already have been told what they are being paid.
        $this->postJson("/api/auth/payroll-runs/{$run['id']}/regenerate", $payload)->assertStatus(422);
        $this->deleteJson("/api/auth/payroll-runs/{$run['id']}")->assertStatus(422);
    }

    public function test_a_cancelled_run_cannot_be_recalculated_back_into_life(): void
    {
        $this->withSalary($this->staff());
        Sanctum::actingAs($this->owner);

        $run = $this->generateRun();
        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/cancel")
            ->assertOk()->assertJsonPath('data.status', 'cancelled');

        // Cancelled is neither editable nor locked, so this once slipped
        // through and revived the figures while the run still read cancelled.
        $this->postJson("/api/auth/payroll-runs/{$run['id']}/regenerate", [
            'title' => $run['title'], 'country' => 'IN',
            'period_start' => $run['period_start'], 'period_end' => $run['period_end'],
        ])->assertStatus(422);

        $this->assertSame('cancelled', PayrollRun::find($run['id'])->status->value);
    }

    public function test_payment_is_refused_until_the_run_is_approved(): void
    {
        $employee = $this->withSalary($this->staff());
        Sanctum::actingAs($this->owner);

        $this->generateRun();
        $slip = SalarySlip::where('staff_id', $employee->id)->firstOrFail();

        // Approval is what makes a figure safe to pay.
        $this->postJson("/api/auth/salary-slips/{$slip->id}/payments", ['amount' => 100])
            ->assertStatus(422)->assertJsonValidationErrors('amount');
    }

    public function test_a_payment_cannot_exceed_what_is_outstanding(): void
    {
        $employee = $this->withSalary($this->staff());
        Sanctum::actingAs($this->owner);

        $run = $this->generateRun();
        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/approve")->assertOk();

        $slip = SalarySlip::where('staff_id', $employee->id)->firstOrFail();
        $net = (float) $slip->net_salary;

        $this->postJson("/api/auth/salary-slips/{$slip->id}/payments", ['amount' => $net + 1])
            ->assertStatus(422)->assertJsonValidationErrors('amount');

        // A part payment leaves the rest outstanding and the run unsettled.
        $this->postJson("/api/auth/salary-slips/{$slip->id}/payments", ['amount' => round($net / 2, 2)])
            ->assertCreated();

        $this->assertSame('partially_paid', $slip->refresh()->status->value);
        $this->assertSame('approved', PayrollRun::find($run['id'])->status->value);

        // A second instalment must not exceed the remainder either.
        $this->postJson("/api/auth/salary-slips/{$slip->id}/payments", ['amount' => $net])
            ->assertStatus(422);
    }

    public function test_reversing_a_payment_restates_the_slip_and_the_run(): void
    {
        $employee = $this->withSalary($this->staff());
        Sanctum::actingAs($this->owner);

        $run = $this->generateRun();
        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/approve")->assertOk();

        $slip = SalarySlip::where('staff_id', $employee->id)->firstOrFail();
        $paymentId = $this->postJson("/api/auth/salary-slips/{$slip->id}/payments", ['amount' => $slip->net_salary])
            ->assertCreated()->json('data.id');

        $this->assertSame('paid', PayrollRun::find($run['id'])->status->value);

        $this->deleteJson("/api/auth/salary-payments/{$paymentId}")->assertOk();

        // paid_amount is recomputed from the payments, never decremented.
        $this->assertSame('0.00', $slip->refresh()->paid_amount);
        $this->assertSame('approved', $slip->status->value);
        $this->assertSame('approved', PayrollRun::find($run['id'])->status->value);
    }

    public function test_a_run_with_money_against_it_cannot_be_cancelled(): void
    {
        $employee = $this->withSalary($this->staff());
        Sanctum::actingAs($this->owner);

        $run = $this->generateRun();
        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/approve")->assertOk();

        $slip = SalarySlip::where('staff_id', $employee->id)->firstOrFail();
        $this->postJson("/api/auth/salary-slips/{$slip->id}/payments", ['amount' => 100])->assertCreated();

        // That money moved; a status change cannot unhappen it.
        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/cancel")->assertStatus(422);
    }

    public function test_an_empty_run_cannot_be_approved(): void
    {
        // Nobody has a salary, so the run generates with no payslips.
        $this->staff();
        Sanctum::actingAs($this->owner);

        $run = $this->generateRun();
        $this->assertSame(0, $run['slip_count']);

        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/submit")->assertStatus(422);
        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/approve")->assertStatus(422);
    }

    public function test_approving_twice_is_refused(): void
    {
        $this->withSalary($this->staff());
        Sanctum::actingAs($this->owner);

        $run = $this->generateRun();
        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/approve")->assertOk();
        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/approve")->assertStatus(422);
    }

    public function test_a_manual_line_left_at_zero_does_not_block_approval(): void
    {
        $employee = $this->withSalary($this->staff());
        SalaryComponent::create([
            'owner_id' => $this->owner->id, 'code' => 'TDS', 'name' => 'Income Tax',
            'type' => 'deduction', 'calculation' => 'manual', 'value' => 0, 'is_active' => true,
        ]);

        Sanctum::actingAs($this->owner);
        $run = $this->generateRun();

        // Zero tax is legitimate below the threshold, so the decision belongs
        // to whoever approves rather than to a hard rule.
        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/approve")->assertOk();

        $slip = SalarySlip::where('staff_id', $employee->id)->firstOrFail();
        $this->assertSame('0.00', $slip->lines()->where('code', 'TDS')->value('amount'));
    }

    public function test_employees_cannot_approve_or_record_payments(): void
    {
        $employee = $this->withSalary($this->staff());
        app(EmployeeInvitationService::class)->invite($employee);

        Sanctum::actingAs($this->owner);
        $run = $this->generateRun();
        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/approve")->assertOk();
        $slip = SalarySlip::where('staff_id', $employee->id)->firstOrFail();

        Sanctum::actingAs($employee->refresh()->user);

        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/cancel")->assertForbidden();
        $this->postJson("/api/auth/salary-slips/{$slip->id}/payments", ['amount' => 1])->assertForbidden();
    }

    public function test_payments_are_scoped_to_the_workspace(): void
    {
        $employee = $this->withSalary($this->staff());
        Sanctum::actingAs($this->owner);
        $run = $this->generateRun();
        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/approve")->assertOk();
        $slip = SalarySlip::where('staff_id', $employee->id)->firstOrFail();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/auth/salary-slips/{$slip->id}/payments")->assertForbidden();
        $this->postJson("/api/auth/salary-slips/{$slip->id}/payments", ['amount' => 1])->assertForbidden();
        $this->patchJson("/api/auth/payroll-runs/{$run['id']}/approve")->assertForbidden();
    }
}
