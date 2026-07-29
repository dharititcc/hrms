<?php

namespace Tests\Feature;

use App\Models\AttendanceLocation;
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

    public function test_an_employee_can_be_assigned_an_office(): void
    {
        $owner = User::factory()->create();
        $office = AttendanceLocation::create([
            'owner_id' => $owner->id, 'name' => 'London HQ',
            'latitude' => 51.5074, 'longitude' => -0.1278, 'radius_metres' => 200, 'is_active' => true,
        ]);

        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/auth/employees', [
            'name' => 'Grace', 'email' => 'g@example.com', 'role' => 'employee', 'status' => 'active',
            'attendance_location_id' => $office->id,
        ])->assertCreated()->assertJsonPath('data.attendance_location_id', $office->id)->json('data.id');

        $this->getJson('/api/auth/employees')->assertOk()->assertJsonPath('data.0.office_name', 'London HQ');

        // Remote and field workers have none, so it has to be clearable.
        $this->putJson("/api/auth/employees/{$id}", [
            'name' => 'Grace', 'email' => 'g@example.com', 'role' => 'employee', 'status' => 'active',
            'attendance_location_id' => null,
        ])->assertOk()->assertJsonPath('data.attendance_location_id', null);
    }

    public function test_an_office_from_another_workspace_cannot_be_assigned(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $theirOffice = AttendanceLocation::create([
            'owner_id' => $intruder->id, 'name' => 'Their HQ',
            'latitude' => 1, 'longitude' => 1, 'radius_metres' => 200, 'is_active' => true,
        ]);

        Sanctum::actingAs($owner);

        $this->postJson('/api/auth/employees', [
            'name' => 'Grace', 'email' => 'g@example.com', 'role' => 'employee', 'status' => 'active',
            'attendance_location_id' => $theirOffice->id,
        ])->assertStatus(422)->assertJsonValidationErrors('attendance_location_id');
    }

    public function test_deleting_an_office_leaves_the_employee_in_place(): void
    {
        $owner = User::factory()->create();
        $office = AttendanceLocation::create([
            'owner_id' => $owner->id, 'name' => 'London HQ',
            'latitude' => 51.5074, 'longitude' => -0.1278, 'radius_metres' => 200, 'is_active' => true,
        ]);
        $employee = Employee::create([
            'owner_id' => $owner->id, 'name' => 'Grace', 'email' => 'g@example.com',
            'role' => 'employee', 'status' => 'active', 'attendance_location_id' => $office->id,
        ]);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/auth/attendance-locations/{$office->id}")->assertOk();

        // Closing an office does not remove the people who worked there.
        $this->assertDatabaseHas('staff', ['id' => $employee->id]);
        $this->assertNull($employee->fresh()->attendance_location_id);
    }
}
