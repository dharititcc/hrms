<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeePermissionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
    }

    private function employee(string $role = 'employee', string $email = 'g@example.com'): Employee
    {
        return Employee::create([
            'owner_id' => $this->owner->id, 'name' => 'Grace', 'email' => $email,
            'role' => $role, 'status' => 'active',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return ['name' => 'Grace', 'email' => 'g@example.com', 'role' => 'employee', 'status' => 'active', ...$overrides];
    }

    public function test_an_employee_can_be_granted_a_permission_their_role_does_not_include(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson('/api/auth/employees', $this->payload([
            'permissions' => ['payroll.view', 'payroll.download', 'payroll.approve'],
        ]))->assertCreated();

        // Approving payroll is a manager's job; this one has been given it.
        $this->assertContains('payroll.approve', $response->json('data.permissions'));
        $this->assertDatabaseHas('employee_permission_overrides', [
            'permission' => 'payroll.approve', 'granted' => true,
        ]);
    }

    public function test_a_permission_the_role_grants_can_be_taken_away(): void
    {
        $employee = $this->employee();
        Sanctum::actingAs($this->owner);

        // An employee normally reads their own payslip; not this one.
        $this->putJson("/api/auth/employees/{$employee->id}", $this->payload(['permissions' => ['payroll.download']]))
            ->assertOk();

        $this->assertDatabaseHas('employee_permission_overrides', [
            'permission' => 'payroll.view', 'granted' => false,
        ]);
        $this->assertNotContains('payroll.view', $this->employeePermissions($employee));
    }

    public function test_only_departures_from_the_role_are_stored(): void
    {
        $employee = $this->employee();
        Sanctum::actingAs($this->owner);

        // Exactly what the role already grants, so there is nothing to record.
        $this->putJson("/api/auth/employees/{$employee->id}", $this->payload([
            'permissions' => $this->employeePermissions($employee),
        ]))->assertOk();

        $this->assertDatabaseCount('employee_permission_overrides', 0);
    }

    public function test_an_override_actually_changes_what_the_request_can_reach(): void
    {
        $employee = $this->employee();
        app(EmployeeInvitationService::class)->invite($employee);

        Sanctum::actingAs($this->owner);
        $this->putJson("/api/auth/employees/{$employee->id}", $this->payload([
            // payroll.view-all is a manager's; granting it opens the whole run.
            'permissions' => [...$this->employeePermissions($employee), 'payroll.view-all'],
        ]))->assertOk();

        Sanctum::actingAs($employee->refresh()->user);

        // Gates read the effective set, not the role, or the grant would be
        // recorded and then ignored.
        $this->getJson('/api/auth/salary-structures')->assertOk();
    }

    public function test_removing_a_permission_closes_the_route_it_opened(): void
    {
        $employee = $this->employee();
        app(EmployeeInvitationService::class)->invite($employee);

        Sanctum::actingAs($this->owner);
        $this->putJson("/api/auth/employees/{$employee->id}", $this->payload([
            'permissions' => array_values(array_diff($this->employeePermissions($employee), ['attendance.view'])),
        ]))->assertOk();

        Sanctum::actingAs($employee->refresh()->user);
        $this->getJson('/api/auth/attendance')->assertForbidden();
    }

    public function test_nobody_can_hand_out_access_they_do_not_hold(): void
    {
        $manager = $this->employee('manager', 'manager@example.com');
        app(EmployeeInvitationService::class)->invite($manager);
        $colleague = $this->employee('employee', 'colleague@example.com');

        Sanctum::actingAs($manager->refresh()->user);

        /*
        | payroll.delete belongs to the admin alone. Without this guard anyone
        | able to edit employees could route it to themselves through a
        | colleague's record.
        */
        $this->putJson("/api/auth/employees/{$colleague->id}", [
            'name' => 'Colleague', 'email' => 'colleague@example.com', 'role' => 'employee', 'status' => 'active',
            'permissions' => [...$this->employeePermissions($colleague), 'payroll.delete'],
        ])->assertStatus(422)->assertJsonValidationErrors('permissions');
    }

    public function test_a_permission_that_does_not_exist_is_ignored(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/auth/employees', $this->payload([
            // A typo should not become a row nothing will ever check.
            'permissions' => ['payroll.view', 'payroll.teleport', 'announcements.pay'],
        ]))->assertCreated();

        $this->assertDatabaseMissing('employee_permission_overrides', ['permission' => 'payroll.teleport']);
        $this->assertDatabaseMissing('employee_permission_overrides', ['permission' => 'announcements.pay']);
    }

    public function test_omitting_the_list_leaves_existing_access_alone(): void
    {
        $employee = $this->employee();
        Sanctum::actingAs($this->owner);

        $this->putJson("/api/auth/employees/{$employee->id}", $this->payload(['permissions' => ['payroll.approve']]))->assertOk();
        $before = $this->employeePermissions($employee);

        // A client that knows nothing about permissions saves a phone number.
        $this->putJson("/api/auth/employees/{$employee->id}", $this->payload(['phone' => '0400000000']))->assertOk();

        $this->assertSame($before, $this->employeePermissions($employee));
    }

    public function test_the_grid_offers_only_actions_that_mean_something(): void
    {
        Sanctum::actingAs($this->owner);

        $grid = collect($this->getJson('/api/auth/permissions')->assertOk()->json('data.grid'));

        $payroll = $grid->firstWhere('module', 'payroll')['actions'];
        $announcements = $grid->firstWhere('module', 'announcements')['actions'];

        $this->assertContains('approve', $payroll);
        $this->assertContains('pay', $payroll);
        // Nobody pays an announcement.
        $this->assertNotContains('pay', $announcements);
    }

    /** @return list<string> */
    private function employeePermissions(Employee $employee): array
    {
        return app(\App\Services\EmployeePermissionService::class)->effectiveFor($employee->refresh());
    }
}
