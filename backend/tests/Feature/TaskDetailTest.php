<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Staff;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskTimeEntry;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskMentionNotification;
use App\Services\StaffInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskDetailTest extends TestCase
{
    use RefreshDatabase;

    private function task(User $owner): Task
    {
        $project = Project::create(['owner_id' => $owner->id, 'name' => 'Website', 'status' => 'active']);

        return Task::create([
            'owner_id' => $owner->id,
            'subject' => 'Draft the sitemap',
            'status' => 'pending',
            'priority' => 'high',
            'related_type' => 'project',
            'related_id' => $project->id,
            'created_by' => $owner->id,
        ]);
    }

    /** Invites a staff member and returns their account. */
    private function teammate(User $owner, string $email = 'grace@example.com', string $role = 'member'): User
    {
        $staff = Staff::create(['owner_id' => $owner->id, 'name' => 'Grace Hopper', 'email' => $email, 'role' => $role, 'status' => 'active']);
        app(StaffInvitationService::class)->invite($staff);

        return $staff->refresh()->user;
    }

    // --- Comments -------------------------------------------------------

    public function test_comments_support_replies_and_are_listed_as_a_thread(): void
    {
        $owner = User::factory()->create();
        $task = $this->task($owner);
        Sanctum::actingAs($owner);

        $parentId = $this->postJson("/api/auth/tasks/{$task->id}/comments", ['body' => 'Looks good'])
            ->assertCreated()
            ->json('data.id');

        $this->postJson("/api/auth/tasks/{$task->id}/comments", ['body' => 'Agreed', 'parent_id' => $parentId])
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $parentId);

        // Only top-level comments are listed; the reply nests inside.
        $this->getJson("/api/auth/tasks/{$task->id}/comments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(1, 'data.0.replies')
            ->assertJsonPath('data.0.replies.0.body', 'Agreed');
    }

    public function test_replies_cannot_nest_more_than_one_level(): void
    {
        $owner = User::factory()->create();
        $task = $this->task($owner);
        Sanctum::actingAs($owner);

        $parentId = $this->postJson("/api/auth/tasks/{$task->id}/comments", ['body' => 'Top'])->json('data.id');
        $replyId = $this->postJson("/api/auth/tasks/{$task->id}/comments", ['body' => 'Reply', 'parent_id' => $parentId])->json('data.id');

        $this->postJson("/api/auth/tasks/{$task->id}/comments", ['body' => 'Nested', 'parent_id' => $replyId])
            ->assertStatus(422)
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_mentions_are_resolved_from_the_body_and_notify_the_mentioned_user(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        $task = $this->task($owner);
        Sanctum::actingAs($owner);

        $this->postJson("/api/auth/tasks/{$task->id}/comments", [
            'body' => "Can you take this @[Grace Hopper](user:{$mate->id})?",
        ])->assertCreated()->assertJsonPath('data.mentions.0.id', $mate->id);

        Notification::assertSentTo($mate, TaskMentionNotification::class);
    }

    public function test_mentions_outside_the_workspace_are_ignored(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $task = $this->task($owner);
        Sanctum::actingAs($owner);

        // The id is well-formed but the user is not in this workspace, so it
        // must not become a mention or a notification.
        $this->postJson("/api/auth/tasks/{$task->id}/comments", [
            'body' => "Hi @[Outsider](user:{$outsider->id})",
        ])->assertCreated()->assertJsonCount(0, 'data.mentions');

        Notification::assertNotSentTo($outsider, TaskMentionNotification::class);
    }

    public function test_editing_a_comment_re_resolves_its_mentions(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        $task = $this->task($owner);
        Sanctum::actingAs($owner);

        $id = $this->postJson("/api/auth/tasks/{$task->id}/comments", [
            'body' => "Ping @[Grace Hopper](user:{$mate->id})",
        ])->json('data.id');

        $this->putJson("/api/auth/comments/{$id}", ['body' => 'Never mind'])
            ->assertOk()
            ->assertJsonCount(0, 'data.mentions');

        $this->assertDatabaseCount('task_comment_mentions', 0);
    }

    public function test_only_the_author_can_edit_a_comment(): void
    {
        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        $task = $this->task($owner);

        Sanctum::actingAs($owner);
        $id = $this->postJson("/api/auth/tasks/{$task->id}/comments", ['body' => 'Mine'])->json('data.id');

        Sanctum::actingAs($mate);
        $this->putJson("/api/auth/comments/{$id}", ['body' => 'Hijacked'])->assertForbidden();

        // The workspace owner holds the delete ability, so moderation still works.
        Sanctum::actingAs($owner);
        $this->deleteJson("/api/auth/comments/{$id}")->assertOk();
        $this->assertSoftDeleted('task_comments', ['id' => $id]);
    }

    public function test_clients_can_comment_but_not_edit_the_task(): void
    {
        $owner = User::factory()->create();
        $client = $this->teammate($owner, 'client@example.com', 'client');
        $task = $this->task($owner);

        Sanctum::actingAs($client);

        $this->postJson("/api/auth/tasks/{$task->id}/comments", ['body' => 'Question from the client'])->assertCreated();
        $this->postJson("/api/auth/tasks/{$task->id}/checklist", ['title' => 'Nope'])->assertForbidden();
    }

    // --- Checklist ------------------------------------------------------

    public function test_checklist_items_track_who_completed_them(): void
    {
        $owner = User::factory()->create();
        $task = $this->task($owner);
        Sanctum::actingAs($owner);

        $id = $this->postJson("/api/auth/tasks/{$task->id}/checklist", ['title' => 'Outline'])
            ->assertCreated()
            ->assertJsonPath('data.is_completed', false)
            ->json('data.id');

        $this->patchJson("/api/auth/checklist-items/{$id}", ['is_completed' => true])
            ->assertOk()
            ->assertJsonPath('data.is_completed', true)
            ->assertJsonPath('data.completed_by', $owner->id);

        // Unticking clears the audit fields.
        $this->patchJson("/api/auth/checklist-items/{$id}", ['is_completed' => false])
            ->assertOk()
            ->assertJsonPath('data.completed_at', null)
            ->assertJsonPath('data.completed_by', null);
    }

    public function test_checklist_reorder_ignores_items_from_other_tasks(): void
    {
        $owner = User::factory()->create();
        $task = $this->task($owner);
        $other = $this->task($owner);
        Sanctum::actingAs($owner);

        $first = $this->postJson("/api/auth/tasks/{$task->id}/checklist", ['title' => 'First'])->json('data.id');
        $second = $this->postJson("/api/auth/tasks/{$task->id}/checklist", ['title' => 'Second'])->json('data.id');
        $foreign = $this->postJson("/api/auth/tasks/{$other->id}/checklist", ['title' => 'Foreign'])->json('data.id');

        $this->patchJson("/api/auth/tasks/{$task->id}/checklist/reorder", ['ordered_ids' => [$second, $first, $foreign]])
            ->assertOk()
            ->assertJsonPath('data.0.id', $second)
            ->assertJsonPath('data.1.id', $first)
            ->assertJsonCount(2, 'data');

        // The foreign item keeps its own ordering.
        $this->assertDatabaseHas('task_checklist_items', ['id' => $foreign, 'position' => 1]);
    }

    // --- Time tracking --------------------------------------------------

    public function test_timer_start_and_stop_records_duration(): void
    {
        $owner = User::factory()->create();
        $task = $this->task($owner);
        Sanctum::actingAs($owner);

        $this->postJson("/api/auth/tasks/{$task->id}/timer/start")
            ->assertCreated()
            ->assertJsonPath('data.is_running', true);

        // Wind the clock forward so the entry accrues measurable time.
        $this->travel(90)->minutes();

        $this->postJson("/api/auth/tasks/{$task->id}/timer/stop")
            ->assertOk()
            ->assertJsonPath('data.is_running', false)
            ->assertJsonPath('data.duration_minutes', 90);

        $this->getJson("/api/auth/tasks/{$task->id}/time-entries")
            ->assertOk()
            ->assertJsonPath('meta.total_minutes', 90);
    }

    public function test_starting_a_second_timer_stops_the_first(): void
    {
        $owner = User::factory()->create();
        $taskA = $this->task($owner);
        $taskB = $this->task($owner);
        Sanctum::actingAs($owner);

        $this->postJson("/api/auth/tasks/{$taskA->id}/timer/start")->assertCreated();
        $this->travel(30)->minutes();
        $this->postJson("/api/auth/tasks/{$taskB->id}/timer/start")->assertCreated();

        // Time must not accrue against both tasks at once.
        $this->assertSame(1, TaskTimeEntry::running()->count());
        $this->assertSame(30, TaskTimeEntry::where('task_id', $taskA->id)->first()->duration_minutes);

        $this->getJson('/api/auth/time-entries/running')->assertOk()->assertJsonPath('data.task_id', $taskB->id);
    }

    public function test_stopping_without_a_running_timer_fails(): void
    {
        $owner = User::factory()->create();
        $task = $this->task($owner);
        Sanctum::actingAs($owner);

        $this->postJson("/api/auth/tasks/{$task->id}/timer/stop")
            ->assertStatus(422)
            ->assertJsonValidationErrors('timer');
    }

    public function test_manual_time_cannot_be_logged_backwards_or_in_the_future(): void
    {
        $owner = User::factory()->create();
        $task = $this->task($owner);
        Sanctum::actingAs($owner);

        $this->postJson("/api/auth/tasks/{$task->id}/time-entries", [
            'started_at' => now()->subHours(2)->toDateTimeString(),
            'ended_at' => now()->subHour()->toDateTimeString(),
            'description' => 'Sitemap work',
        ])->assertCreated()->assertJsonPath('data.duration_minutes', 60);

        $this->postJson("/api/auth/tasks/{$task->id}/time-entries", [
            'started_at' => now()->toDateTimeString(),
            'ended_at' => now()->subHour()->toDateTimeString(),
        ])->assertStatus(422)->assertJsonValidationErrors('ended_at');

        $this->postJson("/api/auth/tasks/{$task->id}/time-entries", [
            'started_at' => now()->addHour()->toDateTimeString(),
            'ended_at' => now()->addHours(2)->toDateTimeString(),
        ])->assertStatus(422)->assertJsonValidationErrors('ended_at');
    }

    public function test_a_user_cannot_delete_someone_elses_time_entry(): void
    {
        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        $task = $this->task($owner);

        Sanctum::actingAs($mate);
        $entryId = $this->postJson("/api/auth/tasks/{$task->id}/timer/start")->assertCreated()->json('data.id');

        $stranger = User::factory()->create();
        Sanctum::actingAs($stranger);
        $this->deleteJson("/api/auth/time-entries/{$entryId}")->assertForbidden();

        // Its own author can.
        Sanctum::actingAs($mate);
        $this->deleteJson("/api/auth/time-entries/{$entryId}")->assertOk();
    }

    // --- Cross-cutting --------------------------------------------------

    public function test_detail_endpoints_are_scoped_to_the_workspace(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $task = $this->task($owner);

        Sanctum::actingAs($intruder);

        $this->getJson("/api/auth/tasks/{$task->id}/comments")->assertForbidden();
        $this->postJson("/api/auth/tasks/{$task->id}/comments", ['body' => 'Nope'])->assertForbidden();
        $this->getJson("/api/auth/tasks/{$task->id}/checklist")->assertForbidden();
        $this->postJson("/api/auth/tasks/{$task->id}/timer/start")->assertForbidden();
    }

    public function test_new_assignees_are_notified_but_existing_ones_are_not_renotified(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        $project = Project::create(['owner_id' => $owner->id, 'name' => 'Website', 'status' => 'active']);
        Sanctum::actingAs($owner);

        $id = $this->postJson("/api/auth/projects/{$project->id}/tasks", [
            'subject' => 'Draft', 'status' => 'pending', 'priority' => 'high', 'assignee_ids' => [$mate->id],
        ])->assertCreated()->json('data.id');

        Notification::assertSentToTimes($mate, TaskAssignedNotification::class, 1);

        // Editing another field with the same assignee must not notify again.
        $this->putJson("/api/auth/tasks/{$id}", [
            'subject' => 'Draft v2', 'status' => 'pending', 'priority' => 'high', 'assignee_ids' => [$mate->id],
        ])->assertOk();

        Notification::assertSentToTimes($mate, TaskAssignedNotification::class, 1);
    }
}
