<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\WorkMode;
use App\Models\Attendance;
use App\Models\AttendanceLocation;
use App\Models\Staff;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\StaffInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceCaptureTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Staff $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->staff = Staff::create([
            'owner_id' => $this->owner->id, 'name' => 'Grace Hopper',
            'email' => 'grace@example.com', 'role' => 'member', 'status' => 'active',
        ]);

        WorkShift::create([
            'owner_id' => $this->owner->id, 'name' => 'Day', 'starts_at' => '09:00', 'ends_at' => '18:00',
            'grace_minutes' => 15, 'break_minutes' => 60, 'is_default' => true, 'is_active' => true,
        ]);
    }

    private function office(float $lat = 51.5074, float $lng = -0.1278, int $radius = 200): AttendanceLocation
    {
        return AttendanceLocation::create([
            'owner_id' => $this->owner->id, 'name' => 'London HQ',
            'latitude' => $lat, 'longitude' => $lng, 'radius_metres' => $radius, 'is_active' => true,
        ]);
    }

    public function test_check_in_on_time_is_present_and_captures_the_device(): void
    {
        $this->travelTo(now()->setTime(9, 5));
        Sanctum::actingAs($this->owner);

        $response = $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0')
            ->postJson('/api/auth/attendance/check-in', ['staff_id' => $this->staff->id]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'present')
            ->assertJsonPath('data.late_minutes', 0)
            ->assertJsonPath('data.device.os', 'Windows')
            ->assertJsonPath('data.device.browser', 'Chrome')
            ->assertJsonPath('data.device.type', 'desktop');
    }

    public function test_lateness_is_measured_from_the_shift_start_not_the_end_of_grace(): void
    {
        // 09:30 against a 09:00 shift with 15 minutes grace.
        $this->travelTo(now()->setTime(9, 30));
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/auth/attendance/check-in', ['staff_id' => $this->staff->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'late')
            // Grace forgives lateness; it does not move the start of the day.
            ->assertJsonPath('data.late_minutes', 30);
    }

    public function test_arriving_within_grace_is_not_late(): void
    {
        $this->travelTo(now()->setTime(9, 14));
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/auth/attendance/check-in', ['staff_id' => $this->staff->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'present')
            ->assertJsonPath('data.late_minutes', 0);
    }

    public function test_check_out_derives_worked_hours_minus_the_break(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        Sanctum::actingAs($this->owner);
        $id = $this->postJson('/api/auth/attendance/check-in', ['staff_id' => $this->staff->id])->json('data.id');

        $this->travelTo(now()->setTime(18, 0));
        $this->postJson("/api/auth/attendance/{$id}/check-out")
            ->assertOk()
            // Nine hours elapsed, less a 60 minute break.
            ->assertJsonPath('data.worked_minutes', 480)
            ->assertJsonPath('data.worked_hours', '8h')
            ->assertJsonPath('data.overtime_minutes', 0);
    }

    public function test_overtime_is_counted_beyond_the_standard_day(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        Sanctum::actingAs($this->owner);
        $id = $this->postJson('/api/auth/attendance/check-in', ['staff_id' => $this->staff->id])->json('data.id');

        $this->travelTo(now()->setTime(20, 0));
        $this->postJson("/api/auth/attendance/{$id}/check-out")
            ->assertOk()
            // 11 hours less the break is 600 minutes, 120 over the 480 standard.
            ->assertJsonPath('data.worked_minutes', 600)
            ->assertJsonPath('data.overtime_minutes', 120);
    }

    public function test_a_short_day_becomes_a_half_day(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        Sanctum::actingAs($this->owner);
        $id = $this->postJson('/api/auth/attendance/check-in', ['staff_id' => $this->staff->id])->json('data.id');

        $this->travelTo(now()->setTime(12, 0));
        $this->postJson("/api/auth/attendance/{$id}/check-out")
            ->assertOk()
            ->assertJsonPath('data.status', 'half_day');
    }

    public function test_checking_in_inside_the_geofence_records_the_office(): void
    {
        $office = $this->office();
        $this->travelTo(now()->setTime(9, 0));
        Sanctum::actingAs($this->owner);

        // A few metres away, comfortably inside a 200m radius.
        $this->postJson('/api/auth/attendance/check-in', [
            'staff_id' => $this->staff->id, 'latitude' => 51.5075, 'longitude' => -0.1279,
        ])->assertOk()->assertJsonPath('data.requires_approval', false);

        $this->assertSame($office->id, Attendance::first()->check_in_location_id);
    }

    public function test_checking_in_outside_the_geofence_is_flagged_for_approval(): void
    {
        $this->office();
        $this->travelTo(now()->setTime(9, 0));
        Sanctum::actingAs($this->owner);

        // Roughly 5km away.
        $this->postJson('/api/auth/attendance/check-in', [
            'staff_id' => $this->staff->id, 'latitude' => 51.5500, 'longitude' => -0.1278,
        ])->assertOk()
            // Flagged rather than refused, because enforcement is off.
            ->assertJsonPath('data.requires_approval', true);

        $attendance = Attendance::first();
        $this->assertNull($attendance->check_in_location_id);

        $this->patchJson("/api/auth/attendance/{$attendance->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.requires_approval', false);
    }

    public function test_enforcement_refuses_a_check_in_outside_the_radius(): void
    {
        config(['attendance.geofence.enforce' => true]);
        $this->office();
        $this->travelTo(now()->setTime(9, 0));
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/auth/attendance/check-in', [
            'staff_id' => $this->staff->id, 'latitude' => 51.5500, 'longitude' => -0.1278,
        ])->assertStatus(422)->assertJsonValidationErrors('location');
    }

    public function test_remote_work_is_never_geofenced(): void
    {
        config(['attendance.geofence.enforce' => true]);
        $this->office();
        $this->travelTo(now()->setTime(9, 0));
        Sanctum::actingAs($this->owner);

        // Being elsewhere is the point of working from home.
        $this->postJson('/api/auth/attendance/check-in', [
            'staff_id' => $this->staff->id, 'work_mode' => WorkMode::Remote->value,
            'latitude' => 40.7128, 'longitude' => -74.0060,
        ])->assertOk()
            ->assertJsonPath('data.work_mode', 'remote')
            ->assertJsonPath('data.requires_approval', false);
    }

    public function test_a_workspace_with_no_offices_is_not_fenced(): void
    {
        config(['attendance.geofence.enforce' => true]);
        $this->travelTo(now()->setTime(9, 0));
        Sanctum::actingAs($this->owner);

        // Otherwise enabling enforcement before defining an office would lock
        // everybody out.
        $this->postJson('/api/auth/attendance/check-in', [
            'staff_id' => $this->staff->id, 'latitude' => 12.9716, 'longitude' => 77.5946,
        ])->assertOk()->assertJsonPath('data.requires_approval', false);
    }

    public function test_double_check_in_and_double_check_out_are_refused(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        Sanctum::actingAs($this->owner);
        $id = $this->postJson('/api/auth/attendance/check-in', ['staff_id' => $this->staff->id])->json('data.id');

        $this->postJson('/api/auth/attendance/check-in', ['staff_id' => $this->staff->id])
            ->assertStatus(422)->assertJsonValidationErrors('check_in');

        $this->travelTo(now()->setTime(17, 0));
        $this->postJson("/api/auth/attendance/{$id}/check-out")->assertOk();
        $this->postJson("/api/auth/attendance/{$id}/check-out")
            ->assertStatus(422)->assertJsonValidationErrors('check_out');
    }

    public function test_an_employee_cannot_check_in_for_a_colleague(): void
    {
        $colleague = Staff::create([
            'owner_id' => $this->owner->id, 'name' => 'Ada', 'email' => 'ada@example.com',
            'role' => 'member', 'status' => 'active',
        ]);
        app(StaffInvitationService::class)->invite($this->staff);

        Sanctum::actingAs($this->staff->refresh()->user);

        $this->postJson('/api/auth/attendance/check-in', ['staff_id' => $colleague->id])->assertForbidden();
        $this->postJson('/api/auth/attendance/check-in', ['staff_id' => $this->staff->id])->assertOk();
    }

    public function test_today_returns_the_callers_own_record(): void
    {
        app(StaffInvitationService::class)->invite($this->staff);
        $this->travelTo(now()->setTime(9, 0));

        Sanctum::actingAs($this->staff->refresh()->user);

        $this->getJson('/api/auth/attendance/today')->assertOk()->assertJsonPath('data', null);

        $this->postJson('/api/auth/attendance/check-in', ['staff_id' => $this->staff->id])->assertOk();
        $this->getJson('/api/auth/attendance/today')->assertOk()->assertJsonPath('data.status', 'present');
    }

    public function test_distance_uses_a_great_circle_not_a_flat_approximation(): void
    {
        $office = $this->office(51.5074, -0.1278, 200);

        // London to Paris is about 344km.
        $distance = $office->distanceTo(48.8566, 2.3522);

        $this->assertGreaterThan(330_000, $distance);
        $this->assertLessThan(350_000, $distance);
        $this->assertFalse($office->covers(48.8566, 2.3522));
        $this->assertTrue($office->covers(51.5074, -0.1278));
    }

    public function test_attendance_status_helper(): void
    {
        $this->assertTrue(AttendanceStatus::Present->countsAsWorked());
        $this->assertTrue(AttendanceStatus::HalfDay->countsAsWorked());
        $this->assertFalse(AttendanceStatus::Absent->countsAsWorked());
        $this->assertFalse(AttendanceStatus::OnLeave->countsAsWorked());
    }
}
