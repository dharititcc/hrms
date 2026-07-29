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

class TaskApiTest extends TestCase
{
    use RefreshDatabase;

    private function project(User $owner): Project
    {
        return Project::create(['owner_id' => $owner->id, 'name' => 'Website relaunch', 'status' => 'active']);
    }

    private function payload(array $overrides = []): array
    {
        return ['subject' => 'Draft the sitemap', 'status' => 'pending', 'priority' => 'high', ...$overrides];
    }

    public function test_completed_at_is_stamped_on_completion_and_cleared_when_reopened(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user);
        Sanctum::actingAs($user);

        $id = $this->postJson("/api/auth/projects/{$project->id}/tasks", $this->payload())->assertCreated()->json('data.id');
        $this->assertNull(Task::find($id)->completed_at);

        $this->patchJson("/api/auth/tasks/{$id}/status", ['status' => 'completed'])->assertOk();
        $this->assertNotNull(Task::find($id)->completed_at);

        // Reopening clears the timestamp.
        $this->patchJson("/api/auth/tasks/{$id}/status", ['status' => 'in_progress'])->assertOk();
        $this->assertNull(Task::find($id)->completed_at);

        // Cancelled is closed but not completed, so it carries no timestamp.
        $this->patchJson("/api/auth/tasks/{$id}/status", ['status' => 'cancelled'])->assertOk();
        $this->assertNull(Task::find($id)->completed_at);
    }

    public function test_archived_tasks_drop_off_the_board_and_can_be_restored(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user);
        Sanctum::actingAs($user);

        $id = $this->postJson("/api/auth/projects/{$project->id}/tasks", $this->payload())->assertCreated()->json('data.id');
        $this->getJson("/api/auth/projects/{$project->id}/tasks")->assertOk()->assertJsonCount(1, 'data');

        $this->patchJson("/api/auth/tasks/{$id}/archive")->assertOk();
        $this->getJson("/api/auth/projects/{$project->id}/tasks")->assertOk()->assertJsonCount(0, 'data');

        // Archiving is not deletion; the record survives.
        $this->assertDatabaseHas('tasks', ['id' => $id]);

        $this->patchJson("/api/auth/tasks/{$id}/restore")->assertOk();
        $this->getJson("/api/auth/projects/{$project->id}/tasks")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_assignees_must_belong_to_the_workspace(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $project = $this->project($owner);
        Sanctum::actingAs($owner);

        $this->postJson("/api/auth/projects/{$project->id}/tasks", $this->payload(['assignee_ids' => [$outsider->id]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('assignee_ids.0');

        // An invited staff member is assignable.
        $employee = Employee::create(['owner_id' => $owner->id, 'name' => 'Grace', 'email' => 'g@example.com', 'role' => 'member', 'status' => 'active']);
        app(EmployeeInvitationService::class)->invite($employee);

        $this->postJson("/api/auth/projects/{$project->id}/tasks", $this->payload(['assignee_ids' => [$employee->refresh()->user_id]]))
            ->assertCreated()
            ->assertJsonPath('data.assignees.0.id', $employee->user_id);
    }

    public function test_workspace_users_lists_the_owner_and_invited_staff_only(): void
    {
        $owner = User::factory()->create();
        $invited = Employee::create(['owner_id' => $owner->id, 'name' => 'Grace', 'email' => 'g@example.com', 'role' => 'member', 'status' => 'active']);
        Employee::create(['owner_id' => $owner->id, 'name' => 'Uninvited', 'email' => 'u@example.com', 'role' => 'member', 'status' => 'active']);
        app(EmployeeInvitationService::class)->invite($invited);

        Sanctum::actingAs($owner);

        // Owner + invited staff, but not the staff member without an account.
        $this->getJson('/api/auth/workspace/users')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_tasks_are_scoped_to_the_workspace(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = $this->project($owner);

        Sanctum::actingAs($owner);
        $id = $this->postJson("/api/auth/projects/{$project->id}/tasks", $this->payload())->assertCreated()->json('data.id');

        Sanctum::actingAs($intruder);
        $this->getJson("/api/auth/projects/{$project->id}/tasks")->assertForbidden();
        $this->putJson("/api/auth/tasks/{$id}", $this->payload(['subject' => 'Hijacked']))->assertForbidden();
        $this->patchJson("/api/auth/tasks/{$id}/status", ['status' => 'completed'])->assertForbidden();
        $this->deleteJson("/api/auth/tasks/{$id}")->assertForbidden();
    }

    public function test_a_task_cannot_be_its_own_parent(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user);
        Sanctum::actingAs($user);

        $id = $this->postJson("/api/auth/projects/{$project->id}/tasks", $this->payload())->assertCreated()->json('data.id');

        $this->putJson("/api/auth/tasks/{$id}", $this->payload(['parent_task_id' => $id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_task_id');
    }
}
