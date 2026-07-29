<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkShift;
use App\Services\EmployeeInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
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

    private function openDay(string $workDate, string $checkIn = '09:00:00'): Attendance
    {
        return Attendance::create([
            'owner_id' => $this->owner->id, 'staff_id' => $this->employee->id,
            'work_date' => $workDate, 'check_in' => $checkIn, 'check_in_at' => "{$workDate} {$checkIn}",
            'timezone' => 'Asia/Kolkata', 'status' => 'present',
            'break_minutes' => 60, 'break_after_minutes' => 360, 'overtime_after_minutes' => 480,
        ]);
    }

    public function test_correcting_the_times_restates_everything_derived_from_them(): void
    {
        $attendance = $this->openDay(now()->subDay()->toDateString());
        Sanctum::actingAs($this->owner);

        $this->patchJson("/api/auth/attendance/{$attendance->id}", ['check_out' => '18:00'])
            ->assertOk()
            // Nine hours less the hour's break.
            ->assertJsonPath('data.worked_minutes', 480)
            ->assertJsonPath('data.overtime_minutes', 0)
            ->assertJsonPath('data.is_open', false)
            // A figure somebody typed does not carry the weight of a captured
            // one, so it goes back for approval.
            ->assertJsonPath('data.is_manual', true)
            ->assertJsonPath('data.requires_approval', true);
    }

    public function test_a_correction_is_judged_under_the_rules_the_day_was_recorded_with(): void
    {
        $attendance = $this->openDay(now()->subDay()->toDateString());
        // The workspace changes its policy after the fact.
        config(['attendance.break_after_minutes' => 60, 'attendance.overtime_after_minutes' => 60]);

        Sanctum::actingAs($this->owner);

        // Still judged at 480 overtime and a 360 break threshold, because
        // those are what the row carries. Re-judging a day somebody may have
        // been paid for is exactly what storing them prevents.
        $this->patchJson("/api/auth/attendance/{$attendance->id}", ['check_out' => '18:00'])
            ->assertOk()
            ->assertJsonPath('data.worked_minutes', 480)
            ->assertJsonPath('data.overtime_minutes', 0);
    }

    public function test_a_check_out_before_the_check_in_is_refused(): void
    {
        $attendance = $this->openDay(now()->subDay()->toDateString(), '14:00:00');
        Sanctum::actingAs($this->owner);

        $this->patchJson("/api/auth/attendance/{$attendance->id}", ['check_out' => '09:00'])
            ->assertStatus(422)->assertJsonValidationErrors('check_out');
    }

    public function test_an_open_day_reports_what_has_elapsed_rather_than_zero(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        Sanctum::actingAs($this->owner);

        $this->postJson('/api/auth/attendance/check-in', ['employee_id' => $this->employee->id])->assertOk();
        $this->travelTo(now()->setTime(11, 30));

        // Zero worked reads as somebody who turned up and did nothing.
        $this->getJson('/api/auth/attendance')
            ->assertOk()
            ->assertJsonPath('data.0.is_open', true)
            ->assertJsonPath('data.0.elapsed_minutes', 150)
            ->assertJsonPath('data.0.worked_minutes', 0);
    }

    public function test_employees_cannot_correct_their_own_hours(): void
    {
        $attendance = $this->openDay(now()->subDay()->toDateString());
        app(EmployeeInvitationService::class)->invite($this->employee);

        Sanctum::actingAs($this->employee->refresh()->user);

        // Editing your own hours is not self-service.
        $this->patchJson("/api/auth/attendance/{$attendance->id}", ['check_out' => '23:00'])->assertForbidden();
    }

    public function test_corrections_are_scoped_to_the_workspace(): void
    {
        $attendance = $this->openDay(now()->subDay()->toDateString());

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson("/api/auth/attendance/{$attendance->id}", ['check_out' => '18:00'])->assertForbidden();
    }

    public function test_the_command_closes_a_forgotten_check_out_for_approval(): void
    {
        $attendance = $this->openDay(now()->subDays(2)->toDateString());

        $this->artisan('attendance:close-abandoned')->assertSuccessful();

        $attendance->refresh();

        // Closed at the end of the shift it was opened against, not at an
        // invented leaving time.
        $this->assertSame('18:00:00', $attendance->check_out);
        $this->assertSame(480, $attendance->worked_minutes);
        $this->assertTrue($attendance->is_manual);
        $this->assertTrue($attendance->requires_approval);
        $this->assertStringContainsString('Closed automatically', $attendance->notes);
    }

    public function test_the_command_leaves_a_shift_still_being_worked_alone(): void
    {
        $today = $this->openDay(now()->toDateString());

        $this->artisan('attendance:close-abandoned')->assertSuccessful();

        // Closing somebody's day while they are still in it would be worse
        // than leaving it open.
        $this->assertNull($today->refresh()->check_out);
    }

    public function test_the_command_reports_without_touching_anything_when_asked(): void
    {
        $attendance = $this->openDay(now()->subDays(2)->toDateString());

        $this->artisan('attendance:close-abandoned', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull($attendance->refresh()->check_out);
    }
}
