<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Models\SalarySlip;
use App\Models\Staff;
use App\Models\Task;
use App\Models\User;
use App\Services\StaffInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private function task(User $owner, array $overrides = []): Task
    {
        return Task::create([
            'owner_id' => $owner->id,
            'subject' => 'A task',
            'status' => 'pending',
            'priority' => 'medium',
            'created_by' => $owner->id,
            ...$overrides,
        ]);
    }

    private function teammate(User $owner): User
    {
        $staff = Staff::create(['owner_id' => $owner->id, 'name' => 'Grace', 'email' => 'g@example.com', 'role' => 'member', 'status' => 'active']);
        app(StaffInvitationService::class)->invite($staff);

        return $staff->refresh()->user;
    }

    public function test_task_counts_reflect_status_dates_and_assignment(): void
    {
        $owner = User::factory()->create();
        $mate = $this->teammate($owner);

        $mine = $this->task($owner, ['subject' => 'Mine']);
        $mine->assignees()->sync([$owner->id]);

        $theirs = $this->task($owner, ['subject' => 'Theirs', 'status' => 'in_progress']);
        $theirs->assignees()->sync([$mate->id]);

        $this->task($owner, ['subject' => 'Late', 'due_date' => now()->subWeek()->toDateString()]);
        $this->task($owner, ['subject' => 'Today', 'due_date' => now()->toDateString()]);
        $this->task($owner, ['subject' => 'Done', 'status' => 'completed']);
        $this->task($owner, ['subject' => 'Archived', 'archived_at' => now()]);

        Sanctum::actingAs($owner);
        $stats = $this->getJson('/api/auth/dashboard/stats')->assertOk()->json('data');

        // Archived tasks are excluded from every count.
        $this->assertSame(5, $stats['tasks']['total']);
        $this->assertSame(1, $stats['tasks']['overdue']);
        $this->assertSame(1, $stats['tasks']['due_today']);
        $this->assertSame(1, $stats['tasks']['completed']);
        $this->assertSame(1, $stats['tasks']['in_progress']);
        $this->assertSame(1, $stats['tasks']['mine']);
        $this->assertSame(3, $stats['tasks']['unassigned']);
    }

    public function test_mine_is_relative_to_the_caller_not_the_workspace_owner(): void
    {
        $owner = User::factory()->create();
        $mate = $this->teammate($owner);

        $ownersTask = $this->task($owner, ['subject' => 'Owner task']);
        $ownersTask->assignees()->sync([$owner->id]);
        $matesTask = $this->task($owner, ['subject' => 'Mate task']);
        $matesTask->assignees()->sync([$mate->id]);

        // The invited staff member sees the same workspace but their own workload.
        Sanctum::actingAs($mate);
        $stats = $this->getJson('/api/auth/dashboard/stats')->assertOk()->json('data');

        $this->assertSame(2, $stats['tasks']['total']);
        $this->assertSame(1, $stats['tasks']['mine']);
    }

    public function test_meeting_and_people_counts(): void
    {
        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        Staff::create(['owner_id' => $owner->id, 'name' => 'Uninvited', 'email' => 'u@example.com', 'role' => 'member', 'status' => 'active']);

        $meeting = Meeting::create([
            'owner_id' => $owner->id, 'title' => 'Standup', 'type' => 'google_meet', 'status' => 'scheduled',
            'host_id' => $owner->id, 'organizer_id' => $owner->id,
            'starts_at' => now()->addHours(2), 'ends_at' => now()->addHours(3), 'timezone' => 'UTC',
        ]);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'user_id' => $mate->id]);

        Sanctum::actingAs($mate);
        $stats = $this->getJson('/api/auth/dashboard/stats')->assertOk()->json('data');

        $this->assertSame(1, $stats['meetings']['today']);
        $this->assertSame(1, $stats['meetings']['upcoming']);
        // The invitation is still unanswered, so it needs their attention.
        $this->assertSame(1, $stats['meetings']['awaiting_my_reply']);

        $this->assertSame(2, $stats['people']['staff']);
        $this->assertSame(1, $stats['people']['with_accounts']);
    }

    public function test_stats_are_scoped_to_the_workspace(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $this->task($owner);
        Project::create(['owner_id' => $owner->id, 'name' => 'Website', 'status' => 'active']);

        Sanctum::actingAs($intruder);
        $stats = $this->getJson('/api/auth/dashboard/stats')->assertOk()->json('data');

        $this->assertSame(0, $stats['tasks']['total']);
        $this->assertSame(0, $stats['projects']['total']);
        $this->assertSame([], $stats['recent_activity']);
    }

    public function test_recent_activity_is_included(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/auth/projects', ['name' => 'Website', 'status' => 'planning'])->assertCreated();

        $stats = $this->getJson('/api/auth/dashboard/stats')->assertOk()->json('data');

        $this->assertNotEmpty($stats['recent_activity']);
        $this->assertSame('created', $stats['recent_activity'][0]['action']);
        $this->assertSame('project', $stats['recent_activity'][0]['entity']);
    }

    public function test_attendance_reflects_the_callers_own_day_and_the_teams(): void
    {
        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        $absent = Staff::create(['owner_id' => $owner->id, 'name' => 'Nobody', 'email' => 'n@example.com', 'role' => 'member', 'status' => 'active']);

        Attendance::create([
            'owner_id' => $owner->id, 'staff_id' => $mate->staffId(), 'work_date' => now()->toDateString(),
            'check_in' => '09:45', 'status' => 'late', 'requires_approval' => true,
        ]);

        Sanctum::actingAs($mate);
        $stats = $this->getJson('/api/auth/dashboard/stats')->assertOk()->json('data');

        $this->assertTrue($stats['attendance']['checked_in']);
        $this->assertFalse($stats['attendance']['checked_out']);
        $this->assertSame('late', $stats['attendance']['my_status']);

        // An employee has no view-all, so team figures are withheld entirely
        // rather than shown as zero.
        $this->assertArrayNotHasKey('present_today', $stats['attendance']);

        Sanctum::actingAs($owner);
        $stats = $this->getJson('/api/auth/dashboard/stats')->assertOk()->json('data');

        // The owner has no staff record, so there is nothing of their own.
        $this->assertFalse($stats['attendance']['checked_in']);
        $this->assertSame(2, $stats['attendance']['active_staff']);
        $this->assertSame(1, $stats['attendance']['present_today']);
        $this->assertSame(1, $stats['attendance']['late_today']);
        // Nobody recorded anything for them, which is not the same as absent.
        $this->assertSame(1, $stats['attendance']['not_recorded']);
        $this->assertSame(1, $stats['attendance']['awaiting_approval']);
        $this->assertSame($absent->owner_id, $owner->id);
    }

    public function test_leave_balance_counts_approved_days_against_the_entitlement(): void
    {
        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        $type = LeaveType::create(['owner_id' => $owner->id, 'name' => 'Annual', 'days_per_year' => 20, 'is_active' => true]);

        // Three inclusive days: the 10th, 11th and 12th.
        LeaveRequest::create([
            'owner_id' => $owner->id, 'staff_id' => $mate->staffId(), 'leave_type_id' => $type->id,
            'start_date' => now()->startOfYear()->addDays(9)->toDateString(),
            'end_date' => now()->startOfYear()->addDays(11)->toDateString(),
            'status' => 'approved',
        ]);

        // Pending is not granted, so it must not reduce the balance.
        LeaveRequest::create([
            'owner_id' => $owner->id, 'staff_id' => $mate->staffId(), 'leave_type_id' => $type->id,
            'start_date' => now()->addWeek()->toDateString(), 'end_date' => now()->addWeek()->toDateString(),
            'status' => 'pending',
        ]);

        Sanctum::actingAs($mate);
        $stats = $this->getJson('/api/auth/dashboard/stats')->assertOk()->json('data');

        $this->assertSame(1, $stats['leave']['my_pending']);
        $this->assertSame(3, $stats['leave']['balances'][0]['taken']);
        $this->assertSame(17, $stats['leave']['balances'][0]['remaining']);

        Sanctum::actingAs($owner);
        $stats = $this->getJson('/api/auth/dashboard/stats')->assertOk()->json('data');
        $this->assertSame(1, $stats['leave']['awaiting_approval']);
    }

    public function test_payroll_shows_the_latest_issued_slip_and_the_queue(): void
    {
        $owner = User::factory()->create();
        $mate = $this->teammate($owner);

        $draft = PayrollRun::create([
            'owner_id' => $owner->id, 'title' => 'March', 'country' => 'IN', 'currency_code' => 'INR',
            'period_start' => now()->startOfMonth()->toDateString(), 'period_end' => now()->endOfMonth()->toDateString(),
            'status' => 'draft',
        ]);
        $approved = PayrollRun::create([
            'owner_id' => $owner->id, 'title' => 'February', 'country' => 'IN', 'currency_code' => 'INR',
            'period_start' => now()->subMonth()->startOfMonth()->toDateString(),
            'period_end' => now()->subMonth()->endOfMonth()->toDateString(),
            'status' => 'approved',
        ]);

        foreach ([[$draft, 'PS-D'], [$approved, 'PS-A']] as [$run, $number]) {
            SalarySlip::create([
                'owner_id' => $owner->id, 'payroll_run_id' => $run->id, 'staff_id' => $mate->staffId(),
                'slip_number' => $number, 'country' => 'IN', 'currency_code' => 'INR',
                'net_salary' => 45000, 'status' => 'approved',
            ]);
        }

        Sanctum::actingAs($mate);
        $stats = $this->getJson('/api/auth/dashboard/stats')->assertOk()->json('data');

        // A slip in an unapproved run is not something the employee should be
        // shown as their pay: the figures can still change.
        $this->assertSame('PS-A', $stats['payroll']['latest_slip']['slip_number']);
        $this->assertArrayNotHasKey('draft_runs', $stats['payroll']);

        Sanctum::actingAs($owner);
        $stats = $this->getJson('/api/auth/dashboard/stats')->assertOk()->json('data');

        $this->assertNull($stats['payroll']['latest_slip']);
        $this->assertSame(1, $stats['payroll']['draft_runs']);
        $this->assertSame(1, $stats['payroll']['awaiting_payment']);
    }
}
