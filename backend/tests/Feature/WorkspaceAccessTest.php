<?php

namespace Tests\Feature;

use App\Enums\Action;
use App\Enums\Module;
use App\Enums\PayrollCountry;
use App\Enums\WorkspaceRole;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\PayrollRun;
use App\Models\SalarySlip;
use App\Models\User;
use App\Notifications\EmployeeInvitationNotification;
use App\Services\EmployeeInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    private int $otherSlipId = 0;

    private function staffFor(User $owner, string $role = 'member', string $email = 'member@example.com'): Employee
    {
        return Employee::create([
            'owner_id' => $owner->id,
            'name' => 'Grace Hopper',
            'email' => $email,
            'role' => $role,
            'status' => 'active',
        ]);
    }

    private function payslipFor(User $owner, ?int $employeeId): SalarySlip
    {
        $run = PayrollRun::create([
            'owner_id' => $owner->id,
            'title' => 'August',
            'country' => PayrollCountry::India,
            'currency_code' => 'INR',
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ]);

        return SalarySlip::create([
            'owner_id' => $owner->id,
            'payroll_run_id' => $run->id,
            'staff_id' => $employeeId,
            'slip_number' => 'SLIP-'.uniqid(),
            'country' => PayrollCountry::India,
            'currency_code' => 'INR',
            'basic_salary' => 3000,
            'gross_salary' => 3000,
            'net_salary' => 3000,
        ]);
    }

    /** Invites the staff member and returns their new login account. */
    private function invite(Employee $employee): User
    {
        app(EmployeeInvitationService::class)->invite($employee);

        return $employee->refresh()->user;
    }

    public function test_inviting_staff_creates_a_linked_account_and_notifies_them(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $employee = $this->staffFor($owner);
        Sanctum::actingAs($owner);

        $this->postJson("/api/auth/employees/{$employee->id}/invite")->assertCreated();

        $employee->refresh();
        $this->assertNotNull($employee->user_id);
        $this->assertSame('member@example.com', $employee->user->email);

        Notification::assertSentTo($employee->user, EmployeeInvitationNotification::class);
    }

    public function test_invited_staff_see_the_owners_workspace_not_their_own(): void
    {
        $owner = User::factory()->create();
        $employee = $this->staffFor($owner);
        $this->staffFor($owner, 'manager', 'colleague@example.com');
        $staffUser = $this->invite($employee);

        // The staff-user's own id is not the workspace id; scoping must follow
        // their staff record's owner, or they would see an empty workspace.
        $this->assertNotSame($owner->id, $staffUser->id);
        $this->assertSame($owner->id, $staffUser->workspaceOwnerId());

        Sanctum::actingAs($staffUser);
        $this->getJson('/api/auth/employees')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_workspace_owner_is_admin_and_staff_roles_map_to_workspace_roles(): void
    {
        $owner = User::factory()->create();
        $this->assertSame(WorkspaceRole::Admin, $owner->workspaceRole());
        $this->assertTrue($owner->isWorkspaceOwner());

        $employee = $this->invite($this->staffFor($owner, 'member', 'employee@example.com'));
        $manager = $this->invite($this->staffFor($owner, 'manager', 'manager@example.com'));
        $client = $this->invite($this->staffFor($owner, 'client', 'client@example.com'));

        $this->assertSame(WorkspaceRole::Employee, $employee->workspaceRole());
        $this->assertSame(WorkspaceRole::Manager, $manager->workspaceRole());
        $this->assertSame(WorkspaceRole::Client, $client->workspaceRole());
        $this->assertFalse($employee->isWorkspaceOwner());
    }

    public function test_permissions_are_scoped_per_module_not_global(): void
    {
        $owner = User::factory()->create();
        $employee = $this->invite($this->staffFor($owner, 'member', 'employee@example.com'));
        $client = $this->invite($this->staffFor($owner, 'client', 'client@example.com'));
        $target = $this->staffFor($owner, 'member', 'target@example.com');

        // The whole point of resource scoping: an employee may edit a task but
        // that grant says nothing about staff records or payroll.
        $this->assertTrue($employee->hasPermission(Module::Tasks, Action::Edit));
        $this->assertFalse($employee->hasPermission(Module::Employees, Action::Edit));
        // They may read their own payslip but not the workspace's payroll.
        $this->assertTrue($employee->hasPermission(Module::Payroll, Action::View));
        $this->assertFalse($employee->hasPermission(Module::Payroll, Action::ViewAll));

        Sanctum::actingAs($employee);
        $this->deleteJson("/api/auth/employees/{$target->id}")->assertForbidden();

        // Clients are read-only apart from commenting, and see no staff at all.
        Sanctum::actingAs($client);
        $this->getJson('/api/auth/employees')->assertForbidden();
        $this->postJson('/api/auth/employees', [
            'name' => 'New person',
            'email' => 'new@example.com',
            'role' => 'member',
            'status' => 'active',
        ])->assertForbidden();
    }

    public function test_payroll_and_approvals_are_closed_to_lower_roles(): void
    {
        $owner = User::factory()->create();
        $employee = $this->invite($this->staffFor($owner, 'member', 'employee@example.com'));
        $manager = $this->invite($this->staffFor($owner, 'manager', 'manager@example.com'));

        // A real record, so the assertion exercises the permission rather than
        // tripping over route-model binding on a missing id.
        $expense = Expense::create([
            'owner_id' => $owner->id,
            'staff_id' => $this->staffFor($owner, 'member', 'claimant@example.com')->id,
            'title' => 'Taxi',
            'category' => 'Travel',
            'amount' => 20,
            'expense_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        // Two payslips: the employee's own, and a colleague's.
        $ownPayslip = $this->payslipFor($owner, $employee->employeeId());
        $this->otherSlipId = $this->payslipFor($owner, $this->staffFor($owner, 'member', 'other@example.com')->id)->id;

        Sanctum::actingAs($employee);

        // Row scoping: their own payslip only, never the colleague's.
        $payroll = $this->getJson('/api/auth/payroll')->assertOk();
        $payroll->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $ownPayslip->id);
        // A colleague's slip stays out of reach even by id.
        $this->getJson('/api/auth/payroll/'.$this->otherSlipId)->assertForbidden();

        // The claim they can see is their own; approving is not theirs to do.
        $this->getJson('/api/auth/expenses')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson("/api/auth/expenses/{$expense->id}/status", ['status' => 'approved'])->assertForbidden();
        $this->assertFalse($employee->hasPermission(Module::Leave, Action::Approve));

        // Managers hold view-all, so they see both payslips.
        Sanctum::actingAs($manager);
        $this->getJson('/api/auth/payroll')->assertOk()->assertJsonCount(2, 'data');
        $this->patchJson("/api/auth/expenses/{$expense->id}/status", ['status' => 'approved'])->assertOk();
    }

    public function test_permissions_endpoint_reports_the_callers_grants(): void
    {
        $owner = User::factory()->create();
        $employee = $this->invite($this->staffFor($owner, 'member', 'employee@example.com'));

        Sanctum::actingAs($employee);
        $response = $this->getJson('/api/auth/permissions')->assertOk();

        $this->assertSame('employee', $response->json('data.role'));
        $this->assertFalse($response->json('data.is_workspace_owner'));
        $this->assertContains('tasks.edit', $response->json('data.permissions'));
        // Own payslip yes, everyone's no.
        $this->assertContains('payroll.view', $response->json('data.permissions'));
        $this->assertNotContains('payroll.view-all', $response->json('data.permissions'));
        $this->assertNotContains('staff.delete', $response->json('data.permissions'));
    }

    public function test_manage_all_does_not_cross_workspace_boundaries(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $staffOfB = $this->staffFor($ownerB, 'member', 'b-staff@example.com');

        // Both owners are Admins holding every permission; that must never let one
        // reach the other's records.
        $this->assertTrue($ownerA->hasPermission(Module::Employees, Action::View));

        Sanctum::actingAs($ownerA);
        $this->getJson("/api/auth/employees/{$staffOfB->id}")->assertForbidden();
        $this->getJson('/api/auth/employees')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_an_account_cannot_be_invited_into_two_workspaces(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $this->invite($this->staffFor($ownerA, 'member', 'shared@example.com'));
        $duplicate = $this->staffFor($ownerB, 'member', 'shared@example.com');

        Sanctum::actingAs($ownerB);
        $this->postJson("/api/auth/employees/{$duplicate->id}/invite")->assertStatus(422);
    }
}
