<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\EmployeeInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskListTest extends TestCase
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
        $employee = Employee::create(['owner_id' => $owner->id, 'name' => 'Grace', 'email' => 'g@example.com', 'role' => 'employee', 'status' => 'active']);
        app(EmployeeInvitationService::class)->invite($employee);

        return $employee->refresh()->user;
    }

    public function test_search_matches_subject_and_description(): void
    {
        $user = User::factory()->create();
        $this->task($user, ['subject' => 'Draft the sitemap']);
        $this->task($user, ['subject' => 'Unrelated', 'description' => 'Mentions the sitemap here']);
        $this->task($user, ['subject' => 'Something else']);
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/tasks?search=sitemap')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_status_and_priority_accept_single_values_and_lists(): void
    {
        $user = User::factory()->create();
        $this->task($user, ['status' => 'pending', 'priority' => 'urgent']);
        $this->task($user, ['status' => 'review', 'priority' => 'low']);
        $this->task($user, ['status' => 'completed', 'priority' => 'high']);
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/tasks?status=pending')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/auth/tasks?status=pending,review')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/auth/tasks?priority[]=urgent&priority[]=high')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_assignee_and_unassigned_filters(): void
    {
        $owner = User::factory()->create();
        $mate = $this->teammate($owner);

        $assigned = $this->task($owner, ['subject' => 'Assigned']);
        $assigned->assignees()->sync([$mate->id]);
        $this->task($owner, ['subject' => 'Nobody']);

        Sanctum::actingAs($owner);

        $this->getJson("/api/auth/tasks?assignee_id={$mate->id}")->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Assigned');

        $this->getJson('/api/auth/tasks?unassigned=1')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Nobody');
    }

    public function test_mine_shorthand_filters_to_the_caller(): void
    {
        $owner = User::factory()->create();
        $mate = $this->teammate($owner);

        $mine = $this->task($owner, ['subject' => 'Mine']);
        $mine->assignees()->sync([$owner->id]);
        $theirs = $this->task($owner, ['subject' => 'Theirs']);
        $theirs->assignees()->sync([$mate->id]);

        Sanctum::actingAs($owner);

        $this->getJson('/api/auth/tasks?mine=1')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject', 'Mine');
    }

    public function test_due_filters(): void
    {
        $user = User::factory()->create();
        $this->task($user, ['subject' => 'Late', 'due_date' => now()->subWeek()->toDateString()]);
        $this->task($user, ['subject' => 'Today', 'due_date' => now()->toDateString()]);
        $this->task($user, ['subject' => 'Soon', 'due_date' => now()->addDays(3)->toDateString()]);
        $this->task($user, ['subject' => 'Undated']);
        // Completed tasks are never overdue, even with a past due date.
        $this->task($user, ['subject' => 'Done late', 'due_date' => now()->subWeek()->toDateString(), 'status' => 'completed']);
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/tasks?due=overdue')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.subject', 'Late');
        $this->getJson('/api/auth/tasks?due=today')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/auth/tasks?due=week')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/auth/tasks?due=none')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.subject', 'Undated');
    }

    public function test_priority_sorts_by_rank_not_alphabetically(): void
    {
        $user = User::factory()->create();
        foreach (['low', 'urgent', 'medium', 'high'] as $priority) {
            $this->task($user, ['subject' => $priority, 'priority' => $priority]);
        }
        Sanctum::actingAs($user);

        // Alphabetically this would be high, low, medium, urgent.
        $this->getJson('/api/auth/tasks?sort=priority&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.priority', 'urgent')
            ->assertJsonPath('data.1.priority', 'high')
            ->assertJsonPath('data.2.priority', 'medium')
            ->assertJsonPath('data.3.priority', 'low');
    }

    public function test_undated_tasks_sort_last_in_both_directions(): void
    {
        $user = User::factory()->create();
        $this->task($user, ['subject' => 'Dated', 'due_date' => now()->addDay()->toDateString()]);
        $this->task($user, ['subject' => 'Undated']);
        Sanctum::actingAs($user);

        foreach (['asc', 'desc'] as $direction) {
            $this->getJson("/api/auth/tasks?sort=due_date&direction={$direction}")
                ->assertOk()
                ->assertJsonPath('data.1.subject', 'Undated');
        }
    }

    public function test_unknown_sort_columns_are_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/tasks?sort=owner_id')->assertStatus(422)->assertJsonValidationErrors('sort');
        $this->getJson('/api/auth/tasks?sort=id;drop table tasks')->assertStatus(422);
    }

    public function test_archived_tasks_are_excluded_unless_requested(): void
    {
        $user = User::factory()->create();
        $this->task($user, ['subject' => 'Live']);
        $this->task($user, ['subject' => 'Archived', 'archived_at' => now()]);
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/tasks')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.subject', 'Live');
        $this->getJson('/api/auth/tasks?archived=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.subject', 'Archived');
    }

    public function test_related_filter_and_pagination(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['owner_id' => $user->id, 'name' => 'Website', 'status' => 'active']);

        foreach (range(1, 30) as $index) {
            $this->task($user, ['subject' => "Task {$index}", 'related_type' => 'project', 'related_id' => $project->id]);
        }
        $this->task($user, ['subject' => 'Standalone']);

        Sanctum::actingAs($user);

        $this->getJson("/api/auth/tasks?related_type=project&related_id={$project->id}&per_page=10")
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 30)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_list_is_scoped_to_the_workspace(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $this->task($owner, ['subject' => 'Private']);

        Sanctum::actingAs($intruder);

        $this->getJson('/api/auth/tasks')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_task_can_be_created_without_a_project(): void
    {
        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        Sanctum::actingAs($owner);

        // The list could show work but not add any: tasks were creatable only
        // from inside a project.
        $this->postJson('/api/auth/tasks', [
            'subject' => 'Renew the domain',
            'status' => 'pending',
            'priority' => 'high',
            'assignee_ids' => [$mate->id],
        ])->assertCreated()
            ->assertJsonPath('data.subject', 'Renew the domain')
            ->assertJsonPath('data.related', null);

        $this->getJson('/api/auth/tasks')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_a_standalone_task_still_validates(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/auth/tasks', ['subject' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['subject', 'status', 'priority']);
    }

    public function test_a_client_cannot_create_a_task(): void
    {
        $owner = User::factory()->create();
        $employee = Employee::create([
            'owner_id' => $owner->id, 'name' => 'Ada', 'email' => 'client@example.com',
            'role' => 'client', 'status' => 'active',
        ]);
        app(EmployeeInvitationService::class)->invite($employee);

        Sanctum::actingAs($employee->refresh()->user);

        // A client may read and comment on the work they are part of, nothing more.
        $this->postJson('/api/auth/tasks', [
            'subject' => 'Not mine to make', 'status' => 'pending', 'priority' => 'medium',
        ])->assertForbidden();
    }
}
