<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\WorkMode;
use App\Models\Attendance;
use App\Models\AttendanceLocation;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\EmployeeInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceCaptureTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->employee = Employee::create([
            'owner_id' => $this->owner->id, 'name' => 'Grace Hopper',
            'email' => 'grace@example.com', 'role' => 'employee', 'status' => 'active',
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
            ->postJson('/api/auth/attendance/check-in', ['employee_id' => $this->employee->id]);

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

        $this->postJson('/api/auth/attendance/check-in', ['employee_id' => $this->employee->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'late')
            // Grace forgives lateness; it does not move the start of the day.
            ->assertJsonPath('data.late_minutes', 30);
    }

    public function test_arriving_within_grace_is_not_late(): void
    {
        $this->travelTo(now()->setTime(9, 14));
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/auth/attendance/check-in', ['employee_id' => $this->employee->id])
            ->assertOk()
            ->assertJsonPath('data.status', 'present')
            ->assertJsonPath('data.late_minutes', 0);
    }

    public function test_check_out_derives_worked_hours_minus_the_break(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        Sanctum::actingAs($this->owner);
        $id = $this->postJson('/api/auth/attendance/check-in', ['employee_id' => $this->employee->id])->json('data.id');

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
        $id = $this->postJson('/api/auth/attendance/check-in', ['employee_id' => $this->employee->id])->json('data.id');

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
        $id = $this->postJson('/api/auth/attendance/check-in', ['employee_id' => $this->employee->id])->json('data.id');

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
            'employee_id' => $this->employee->id, 'latitude' => 51.5075, 'longitude' => -0.1279,
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
            'employee_id' => $this->employee->id, 'latitude' => 51.5500, 'longitude' => -0.1278,
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
            'employee_id' => $this->employee->id, 'latitude' => 51.5500, 'longitude' => -0.1278,
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
            'employee_id' => $this->employee->id, 'work_mode' => WorkMode::Remote->value,
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
            'employee_id' => $this->employee->id, 'latitude' => 12.9716, 'longitude' => 77.5946,
        ])->assertOk()->assertJsonPath('data.requires_approval', false);
    }

    public function test_double_check_in_and_double_check_out_are_refused(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        Sanctum::actingAs($this->owner);
        $id = $this->postJson('/api/auth/attendance/check-in', ['employee_id' => $this->employee->id])->json('data.id');

        $this->postJson('/api/auth/attendance/check-in', ['employee_id' => $this->employee->id])
            ->assertStatus(422)->assertJsonValidationErrors('check_in');

        $this->travelTo(now()->setTime(17, 0));
        $this->postJson("/api/auth/attendance/{$id}/check-out")->assertOk();
        $this->postJson("/api/auth/attendance/{$id}/check-out")
            ->assertStatus(422)->assertJsonValidationErrors('check_out');
    }

    public function test_an_employee_cannot_check_in_for_a_colleague(): void
    {
        $colleague = Employee::create([
            'owner_id' => $this->owner->id, 'name' => 'Ada', 'email' => 'ada@example.com',
            'role' => 'employee', 'status' => 'active',
        ]);
        app(EmployeeInvitationService::class)->invite($this->employee);

        Sanctum::actingAs($this->employee->refresh()->user);

        $this->postJson('/api/auth/attendance/check-in', ['employee_id' => $colleague->id])->assertForbidden();
        $this->postJson('/api/auth/attendance/check-in', ['employee_id' => $this->employee->id])->assertOk();
    }

    public function test_today_returns_the_callers_own_record(): void
    {
        app(EmployeeInvitationService::class)->invite($this->employee);
        $this->travelTo(now()->setTime(9, 0));

        Sanctum::actingAs($this->employee->refresh()->user);

        $this->getJson('/api/auth/attendance/today')->assertOk()->assertJsonPath('data', null);

        $this->postJson('/api/auth/attendance/check-in', ['employee_id' => $this->employee->id])->assertOk();
        $this->getJson('/api/auth/attendance/today')->assertOk()->assertJsonPath('data.status', 'present');
    }

    public function test_the_employees_own_office_wins_when_two_overlap(): void
    {
        // Both cover the same spot; only the assignment tells them apart.
        $other = $this->office(51.5074, -0.1278, 500);
        $mine = $this->office(51.5074, -0.1278, 500);
        $mine->update(['name' => 'Southwark']);

        $this->employee->update(['attendance_location_id' => $mine->id]);
        $this->assertTrue($other->id < $mine->id, 'the other office is returned first by the query');

        Sanctum::actingAs($this->owner);

        $this->postJson('/api/auth/attendance/check-in', [
            'employee_id' => $this->employee->id, 'latitude' => 51.5074, 'longitude' => -0.1278,
        ])->assertOk()->assertJsonPath('data.check_in_location.office', 'Southwark');
    }

    public function test_checking_in_at_another_office_is_still_recorded_there(): void
    {
        $home = $this->office(51.5074, -0.1278, 200);
        $visiting = $this->office(48.8566, 2.3522, 200);
        $visiting->update(['name' => 'Paris']);

        $this->employee->update(['attendance_location_id' => $home->id]);

        Sanctum::actingAs($this->owner);

        // Their own office is a preference, not a fence: visiting a different
        // site has to work.
        $this->postJson('/api/auth/attendance/check-in', [
            'employee_id' => $this->employee->id, 'latitude' => 48.8566, 'longitude' => 2.3522,
        ])->assertOk()->assertJsonPath('data.check_in_location.office', 'Paris');
    }

    public function test_the_day_is_recorded_in_the_browsers_timezone(): void
    {
        // 23:30 UTC on the 1st is 05:00 on the 2nd in Kolkata: a different
        // clock time and a different working day.
        $this->travelTo(Carbon::parse('2026-03-01 23:30:00', 'UTC'));
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/auth/attendance/check-in', [
            'employee_id' => $this->employee->id, 'timezone' => 'Asia/Kolkata',
        ])->assertOk()
            ->assertJsonPath('data.check_in', '05:00:00')
            ->assertJsonPath('data.work_date', '2026-03-02')
            ->assertJsonPath('data.timezone', 'Asia/Kolkata')
            // Early, so not late against an 09:00 shift where they are.
            ->assertJsonPath('data.status', 'present');

        // The instant is absolute, whatever zone it was recorded from.
        $this->assertSame('2026-03-01T23:30:00.000000Z', Attendance::first()->check_in_at->utc()->toISOString());
    }

    public function test_the_same_instant_is_a_different_day_in_a_different_zone(): void
    {
        $this->travelTo(Carbon::parse('2026-03-01 23:30:00', 'UTC'));
        Sanctum::actingAs($this->owner);

        // New York is still on the 1st, and 18:30 is nowhere near a 09:00 start.
        $this->postJson('/api/auth/attendance/check-in', [
            'employee_id' => $this->employee->id, 'timezone' => 'America/New_York',
        ])->assertOk()
            ->assertJsonPath('data.check_in', '18:30:00')
            ->assertJsonPath('data.work_date', '2026-03-01');
    }

    public function test_an_unknown_timezone_is_refused(): void
    {
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/auth/attendance/check-in', [
            'employee_id' => $this->employee->id, 'timezone' => 'Mars/Olympus_Mons',
        ])->assertStatus(422)->assertJsonValidationErrors('timezone');
    }

    public function test_a_legacy_timezone_alias_is_accepted(): void
    {
        $this->travelTo(Carbon::parse('2026-03-01 23:30:00', 'UTC'));
        Sanctum::actingAs($this->owner);

        /*
        | Chrome on Windows reports Asia/Calcutta rather than Asia/Kolkata.
        | It is missing from timezone_identifiers_list(), so Laravel's own
        | timezone rule rejects it and check-in failed on an ordinary machine.
        */
        $this->postJson('/api/auth/attendance/check-in', [
            'employee_id' => $this->employee->id, 'timezone' => 'Asia/Calcutta',
        ])->assertOk()
            ->assertJsonPath('data.check_in', '05:00:00')
            ->assertJsonPath('data.work_date', '2026-03-02')
            ->assertJsonPath('data.timezone', 'Asia/Calcutta');
    }

    public function test_a_zone_that_passes_validation_is_never_swapped_for_the_default(): void
    {
        $this->travelTo(Carbon::parse('2026-03-01 23:30:00', 'UTC'));
        Sanctum::actingAs($this->owner);

        // The service used to re-check against a narrower list than the
        // request did, and silently fall back when the two disagreed.
        $this->postJson('/api/auth/attendance/check-in', [
            'employee_id' => $this->employee->id, 'timezone' => 'US/Eastern',
        ])->assertOk()->assertJsonPath('data.check_in', '18:30:00');
    }

    public function test_check_out_closes_the_day_in_the_zone_it_was_opened_in(): void
    {
        $this->travelTo(Carbon::parse('2026-03-02 03:30:00', 'UTC'));
        Sanctum::actingAs($this->owner);

        $id = $this->postJson('/api/auth/attendance/check-in', [
            'employee_id' => $this->employee->id, 'timezone' => 'Asia/Kolkata',
        ])->assertOk()->json('data.id');

        $this->travelTo(Carbon::parse('2026-03-02 12:30:00', 'UTC'));

        // Checking out from a browser in another zone must not restate the
        // day in that zone: 18:00 in Kolkata, not 07:30 in New York.
        $this->postJson("/api/auth/attendance/{$id}/check-out", ['timezone' => 'America/New_York'])
            ->assertOk()
            ->assertJsonPath('data.check_out', '18:00:00')
            ->assertJsonPath('data.worked_minutes', 480);
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
