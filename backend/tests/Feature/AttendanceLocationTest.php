<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceLocation;
use App\Models\Employee;
use App\Models\User;
use App\Services\EmployeeInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceLocationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'London HQ',
            'address' => '1 Example Street',
            'latitude' => 51.5074,
            'longitude' => -0.1278,
            'radius_metres' => 200,
            ...$overrides,
        ];
    }

    public function test_an_office_can_be_created_listed_updated_and_deleted(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/auth/attendance-locations', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'London HQ')
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        $this->getJson('/api/auth/attendance-locations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            // The UI needs to know whether a fence blocks or merely flags.
            ->assertJsonPath('meta.enforcement_enabled', false);

        $this->putJson("/api/auth/attendance-locations/{$id}", $this->payload(['radius_metres' => 500]))
            ->assertOk()
            ->assertJsonPath('data.radius_metres', 500);

        $this->deleteJson("/api/auth/attendance-locations/{$id}")->assertOk();
        $this->assertDatabaseCount('attendance_locations', 0);
    }

    public function test_coordinates_and_radius_are_validated(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/auth/attendance-locations', $this->payload(['latitude' => 120]))
            ->assertStatus(422)->assertJsonValidationErrors('latitude');

        $this->postJson('/api/auth/attendance-locations', $this->payload(['longitude' => -200]))
            ->assertStatus(422)->assertJsonValidationErrors('longitude');

        // Below the floor a fence would reject people standing in the doorway.
        $this->postJson('/api/auth/attendance-locations', $this->payload(['radius_metres' => 5]))
            ->assertStatus(422)->assertJsonValidationErrors('radius_metres');
    }

    public function test_deleting_an_office_keeps_the_attendance_recorded_there(): void
    {
        $owner = User::factory()->create();
        $employee = Employee::create(['owner_id' => $owner->id, 'name' => 'Grace', 'email' => 'g@example.com', 'role' => 'member', 'status' => 'active']);
        $office = AttendanceLocation::create([...$this->payload(), 'owner_id' => $owner->id, 'is_active' => true]);

        $attendance = Attendance::create([
            'owner_id' => $owner->id, 'staff_id' => $employee->id, 'work_date' => now()->toDateString(),
            'status' => 'present', 'check_in_location_id' => $office->id,
        ]);

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/auth/attendance-locations/{$office->id}")->assertOk();

        // History survives; only the link to the office is cleared.
        $this->assertDatabaseHas('attendances', ['id' => $attendance->id]);
        $this->assertNull($attendance->fresh()->check_in_location_id);
    }

    public function test_employees_may_read_offices_but_not_change_them(): void
    {
        $owner = User::factory()->create();
        $employee = Employee::create(['owner_id' => $owner->id, 'name' => 'Grace', 'email' => 'g@example.com', 'role' => 'member', 'status' => 'active']);
        app(EmployeeInvitationService::class)->invite($employee);
        AttendanceLocation::create([...$this->payload(), 'owner_id' => $owner->id, 'is_active' => true]);

        Sanctum::actingAs($employee->refresh()->user);

        // They need to see where they are expected to be.
        $this->getJson('/api/auth/attendance-locations')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/auth/attendance-locations', $this->payload(['name' => 'Mine']))->assertForbidden();
    }

    public function test_offices_are_scoped_to_the_workspace(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $office = AttendanceLocation::create([...$this->payload(), 'owner_id' => $owner->id, 'is_active' => true]);

        Sanctum::actingAs($intruder);

        $this->getJson('/api/auth/attendance-locations')->assertOk()->assertJsonCount(0, 'data');
        $this->putJson("/api/auth/attendance-locations/{$office->id}", $this->payload())->assertForbidden();
        $this->deleteJson("/api/auth/attendance-locations/{$office->id}")->assertForbidden();
    }
}
