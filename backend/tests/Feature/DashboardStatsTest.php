<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\MeetingParticipant;
use App\Models\Project;
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
}
