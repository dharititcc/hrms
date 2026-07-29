<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_update_and_delete_owned_staff(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/auth/employees', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'phone' => '+1 555 0100',
            'role' => 'manager',
            'status' => 'active',
        ]);

        $create->assertCreated()->assertJsonPath('data.email', 'ada@example.com');
        $employee = Employee::query()->where('owner_id', $user->id)->firstOrFail();

        $this->putJson("/api/auth/employees/{$employee->id}", [
            'name' => 'Ada Byron Lovelace',
            'email' => 'ada@example.com',
            'phone' => null,
            'role' => 'admin',
            'status' => 'inactive',
        ])->assertOk()->assertJsonPath('data.status', 'inactive');

        $this->deleteJson("/api/auth/employees/{$employee->id}")->assertOk();
        $this->assertDatabaseMissing('staff', ['id' => $employee->id]);
    }

    public function test_user_cannot_view_another_users_staff(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $employee = Employee::create([
            'owner_id' => $owner->id,
            'name' => 'Private teammate',
            'email' => 'private@example.com',
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/auth/employees')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/auth/employees/{$employee->id}")->assertForbidden();
    }
}
