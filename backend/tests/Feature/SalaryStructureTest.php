<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeSalaryAssignment;
use App\Models\SalaryComponent;
use App\Models\SalaryStructure;
use App\Models\User;
use App\Services\EmployeeInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SalaryStructureTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return ['name' => 'India Standard', 'country' => 'IN', ...$overrides];
    }

    private function structure(User $owner, array $overrides = []): SalaryStructure
    {
        return SalaryStructure::create([
            'owner_id' => $owner->id,
            'name' => 'India Standard',
            'country' => 'IN',
            'currency_code' => 'INR',
            'is_active' => true,
            ...$overrides,
        ]);
    }

    public function test_a_structure_can_be_created_listed_updated_and_deleted(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/auth/salary-structures', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'India Standard')
            // Currency follows the country when it is not given.
            ->assertJsonPath('data.currency_code', 'INR')
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        $this->getJson('/api/auth/salary-structures')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.components_count', 0);

        $this->putJson("/api/auth/salary-structures/{$id}", $this->payload(['name' => 'India Senior']))
            ->assertOk()
            ->assertJsonPath('data.name', 'India Senior');

        $this->deleteJson("/api/auth/salary-structures/{$id}")->assertOk();
        $this->assertSoftDeleted('salary_structures', ['id' => $id]);
    }

    public function test_statutory_components_can_be_seeded_from_the_country(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/auth/salary-structures', $this->payload(['seed_statutory' => true]))
            ->assertCreated();

        // India defines PF, ESIC, PT and TDS in config/payroll.php.
        $this->assertCount(4, $response->json('data.components'));
        $this->assertDatabaseHas('salary_components', [
            'code' => 'PF', 'calculation' => 'percent_of_basic', 'is_statutory' => true,
        ]);

        // TDS is progressive, so it is seeded as manual rather than a flat rate.
        $this->assertDatabaseHas('salary_components', ['code' => 'TDS', 'calculation' => 'manual']);
    }

    public function test_a_structure_in_use_cannot_be_deleted(): void
    {
        $owner = User::factory()->create();
        $employee = Employee::create(['owner_id' => $owner->id, 'name' => 'Grace', 'email' => 'g@example.com', 'role' => 'employee', 'status' => 'active']);
        $structure = $this->structure($owner);

        EmployeeSalaryAssignment::create([
            'owner_id' => $owner->id, 'staff_id' => $employee->id, 'salary_structure_id' => $structure->id,
            'basic_salary' => 50000, 'country' => 'IN', 'currency_code' => 'INR',
            'effective_from' => now()->toDateString(), 'status' => 'active',
        ]);

        Sanctum::actingAs($owner);

        // Historic payslips would otherwise become unexplainable.
        $this->deleteJson("/api/auth/salary-structures/{$structure->id}")->assertStatus(422);
        $this->assertDatabaseHas('salary_structures', ['id' => $structure->id, 'deleted_at' => null]);
    }

    public function test_a_deleted_structures_name_can_be_reused(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/auth/salary-structures', $this->payload())->json('data.id');
        $this->deleteJson("/api/auth/salary-structures/{$id}")->assertOk();

        // The unique index spans deleted_at, so the name is released.
        $this->postJson('/api/auth/salary-structures', $this->payload())->assertCreated();
    }

    public function test_duplicate_live_names_are_rejected(): void
    {
        $owner = User::factory()->create();
        $this->structure($owner);
        Sanctum::actingAs($owner);

        $this->postJson('/api/auth/salary-structures', $this->payload())
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_employees_cannot_read_or_change_structures(): void
    {
        $owner = User::factory()->create();
        $employee = Employee::create(['owner_id' => $owner->id, 'name' => 'Grace', 'email' => 'g@example.com', 'role' => 'employee', 'status' => 'active']);
        app(EmployeeInvitationService::class)->invite($employee);
        $structure = $this->structure($owner);

        Sanctum::actingAs($employee->refresh()->user);

        // Structures reveal the whole workspace's pay design, so they sit
        // behind view-all rather than view.
        $this->getJson('/api/auth/salary-structures')->assertForbidden();
        $this->postJson('/api/auth/salary-structures', $this->payload(['name' => 'Mine']))->assertForbidden();
        $this->deleteJson("/api/auth/salary-structures/{$structure->id}")->assertForbidden();
    }

    public function test_structures_are_scoped_to_the_workspace(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $structure = $this->structure($owner);

        Sanctum::actingAs($intruder);

        $this->getJson('/api/auth/salary-structures')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/auth/salary-structures/{$structure->id}")->assertForbidden();
        $this->putJson("/api/auth/salary-structures/{$structure->id}", $this->payload())->assertForbidden();
    }

    public function test_components_can_be_managed_and_are_filtered_by_structure(): void
    {
        $owner = User::factory()->create();
        $structure = $this->structure($owner);
        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/auth/salary-components', [
            'salary_structure_id' => $structure->id,
            'code' => 'hra', 'name' => 'House Rent Allowance',
            'type' => 'earning', 'calculation' => 'percent_of_basic', 'value' => 40,
        ])->assertCreated()
            // Codes key manual amounts at generation time, so they normalise.
            ->assertJsonPath('data.code', 'HRA')
            ->json('data.id');

        // A workspace-wide component, which has no structure.
        $this->postJson('/api/auth/salary-components', [
            'code' => 'BONUS', 'name' => 'Bonus', 'type' => 'earning', 'calculation' => 'fixed', 'value' => 1000,
        ])->assertCreated();

        $this->getJson('/api/auth/salary-components')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/auth/salary-components?salary_structure_id={$structure->id}")
            ->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/auth/salary-components?global_only=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'BONUS');

        $this->putJson("/api/auth/salary-components/{$id}", [
            'salary_structure_id' => $structure->id,
            'code' => 'HRA', 'name' => 'HRA', 'type' => 'earning', 'calculation' => 'percent_of_basic', 'value' => 50,
        ])->assertOk()->assertJsonPath('data.value', '50.0000');

        $this->deleteJson("/api/auth/salary-components/{$id}")->assertOk();
        $this->assertDatabaseMissing('salary_components', ['id' => $id]);
    }

    public function test_an_earning_cannot_be_a_percentage_of_gross(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        // The calculator throws on these; rejecting at save time means a run
        // can never be generated into that failure.
        $this->postJson('/api/auth/salary-components', [
            'code' => 'HRA', 'name' => 'HRA', 'type' => 'earning', 'calculation' => 'percent_of_gross', 'value' => 40,
        ])->assertStatus(422)->assertJsonValidationErrors('calculation');

        // The same calculation is legitimate for a deduction.
        $this->postJson('/api/auth/salary-components', [
            'code' => 'ESIC', 'name' => 'ESIC', 'type' => 'deduction', 'calculation' => 'percent_of_gross', 'value' => 0.75,
        ])->assertCreated();
    }

    public function test_component_input_is_validated(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/auth/salary-components', [
            'code' => 'PF', 'name' => 'PF', 'type' => 'deduction', 'calculation' => 'percent_of_basic', 'value' => 150,
        ])->assertStatus(422)->assertJsonValidationErrors('value');

        $this->postJson('/api/auth/salary-components', [
            'code' => 'has space', 'name' => 'Bad', 'type' => 'earning', 'calculation' => 'fixed', 'value' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_two_workspace_wide_components_cannot_share_a_code(): void
    {
        $owner = User::factory()->create();
        SalaryComponent::create([
            'owner_id' => $owner->id, 'salary_structure_id' => null, 'code' => 'BONUS', 'name' => 'Bonus',
            'type' => 'earning', 'calculation' => 'fixed', 'value' => 1000, 'is_active' => true,
        ]);

        Sanctum::actingAs($owner);

        // MySQL treats NULL structure ids as distinct, so the unique index does
        // not catch this; both would otherwise apply to every payslip.
        $this->postJson('/api/auth/salary-components', [
            'code' => 'BONUS', 'name' => 'Other Bonus', 'type' => 'earning', 'calculation' => 'fixed', 'value' => 500,
        ])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_components_are_scoped_to_the_workspace(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $component = SalaryComponent::create([
            'owner_id' => $owner->id, 'code' => 'BONUS', 'name' => 'Bonus',
            'type' => 'earning', 'calculation' => 'fixed', 'value' => 1000, 'is_active' => true,
        ]);

        Sanctum::actingAs($intruder);

        $this->getJson('/api/auth/salary-components')->assertOk()->assertJsonCount(0, 'data');
        $this->deleteJson("/api/auth/salary-components/{$component->id}")->assertForbidden();
    }
}
