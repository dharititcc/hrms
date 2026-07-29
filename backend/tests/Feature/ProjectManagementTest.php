<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeEmployee(User $owner, string $email = 'member@example.com'): Employee
    {
        return Employee::create([
            'owner_id' => $owner->id,
            'name' => 'Grace Hopper',
            'email' => $email,
            'role' => 'employee',
            'status' => 'active',
        ]);
    }

    public function test_user_can_create_a_project_with_members_and_manage_its_tasks(): void
    {
        $user = User::factory()->create();
        $employee = $this->makeEmployee($user);
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/auth/projects', [
            'name' => 'Website relaunch',
            'client' => 'Acme Corp',
            'status' => 'active',
            'start_date' => '2026-08-01',
            'end_date' => '2026-09-30',
            'budget' => 15000,
            'member_ids' => [$employee->id],
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Website relaunch')
            ->assertJsonPath('data.members.0.id', $employee->id);

        $project = Project::query()->where('owner_id', $user->id)->firstOrFail();

        $task = $this->postJson("/api/auth/projects/{$project->id}/tasks", [
            'subject' => 'Draft the sitemap',
            'status' => 'pending',
            'priority' => 'high',
            'assignee_ids' => [$user->id],
            'due_date' => '2026-08-15',
        ]);

        $task->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.related_type', 'project')
            ->assertJsonPath('data.assignees.0.id', $user->id);

        $taskId = $task->json('data.id');

        // Moving across kanban columns.
        $this->patchJson("/api/auth/tasks/{$taskId}/status", ['status' => 'in_progress'])
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        // Progress counts roll up on the project.
        $this->patchJson("/api/auth/tasks/{$taskId}/status", ['status' => 'completed'])->assertOk();
        $this->getJson("/api/auth/projects/{$project->id}")
            ->assertOk()
            ->assertJsonPath('data.tasks_total', 1)
            ->assertJsonPath('data.tasks_done', 1);

        $this->deleteJson("/api/auth/tasks/{$taskId}")->assertOk();
        $this->deleteJson("/api/auth/projects/{$project->id}")->assertOk();
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_user_cannot_access_another_users_project(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $project = Project::create([
            'owner_id' => $owner->id,
            'name' => 'Confidential rollout',
            'status' => 'planning',
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/auth/projects')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/auth/projects/{$project->id}")->assertForbidden();
        $this->getJson("/api/auth/projects/{$project->id}/tasks")->assertForbidden();
    }

    public function test_project_cannot_be_assigned_staff_owned_by_someone_else(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignStaff = $this->makeEmployee($otherUser, 'foreign@example.com');

        Sanctum::actingAs($user);

        $this->postJson('/api/auth/projects', [
            'name' => 'Website relaunch',
            'status' => 'active',
            'member_ids' => [$foreignStaff->id],
        ])->assertStatus(422)->assertJsonValidationErrors('member_ids.0');
    }

    public function test_end_date_must_not_precede_start_date(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/projects', [
            'name' => 'Bad dates',
            'status' => 'planning',
            'start_date' => '2026-09-01',
            'end_date' => '2026-08-01',
        ])->assertStatus(422)->assertJsonValidationErrors('end_date');
    }
}
