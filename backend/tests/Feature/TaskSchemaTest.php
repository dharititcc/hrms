<?php

namespace Tests\Feature;

use App\Enums\RepeatFrequency;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\TaskComment;
use App\Models\TaskTimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function task(User $owner, array $overrides = []): Task
    {
        return Task::create([
            'owner_id' => $owner->id,
            'subject' => 'Draft the sitemap',
            'status' => TaskStatus::Pending,
            'priority' => TaskPriority::High,
            'created_by' => $owner->id,
            ...$overrides,
        ]);
    }

    public function test_task_casts_and_defaults_are_correct(): void
    {
        $owner = User::factory()->create();
        $task = $this->task($owner, ['is_billable' => true, 'hourly_rate' => 85.5, 'estimated_hours' => 12.25]);

        $task->refresh();

        $this->assertSame(TaskStatus::Pending, $task->status);
        $this->assertSame(TaskPriority::High, $task->priority);
        $this->assertTrue($task->is_billable);
        $this->assertFalse($task->is_public);
        $this->assertSame('85.50', $task->hourly_rate);
        $this->assertFalse($task->isArchived());
    }

    public function test_assignees_followers_and_favorites_are_linked_to_users(): void
    {
        $owner = User::factory()->create();
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $task = $this->task($owner);

        $task->assignees()->sync([$alice->id, $bob->id]);
        $task->followers()->sync([$bob->id]);
        $task->favoritedBy()->sync([$alice->id]);

        $this->assertCount(2, $task->assignees);
        $this->assertCount(1, $task->followers);
        $this->assertCount(1, $task->favoritedBy);

        // Pivots are unique, so re-attaching must not duplicate.
        $task->assignees()->syncWithoutDetaching([$alice->id]);
        $this->assertCount(2, $task->fresh()->assignees);
    }

    public function test_subtasks_cascade_and_recurrence_links_back_to_the_template(): void
    {
        $owner = User::factory()->create();
        $parent = $this->task($owner, ['subject' => 'Parent']);
        $child = $this->task($owner, ['subject' => 'Child', 'parent_task_id' => $parent->id]);

        $this->assertSame($parent->id, $child->parent->id);
        $this->assertCount(1, $parent->subtasks);

        $template = $this->task($owner, [
            'subject' => 'Weekly report',
            'repeat_frequency' => RepeatFrequency::Weekly,
            'repeat_interval' => 2,
            'repeat_until' => now()->addMonths(3)->toDateString(),
        ]);
        $instance = $this->task($owner, ['subject' => 'Weekly report', 'recurrence_parent_id' => $template->id]);

        $this->assertTrue($template->repeats());
        $this->assertSame($template->id, $instance->recurrenceParent->id);

        // Deleting the parent removes subtasks (force delete bypasses soft deletes).
        $parent->forceDelete();
        $this->assertDatabaseMissing('tasks', ['id' => $child->id]);
    }

    public function test_expired_and_absent_recurrence_rules_do_not_repeat(): void
    {
        $owner = User::factory()->create();

        $this->assertFalse($this->task($owner)->repeats());
        $this->assertFalse($this->task($owner, [
            'repeat_frequency' => RepeatFrequency::Daily,
            'repeat_until' => now()->subDay()->toDateString(),
        ])->repeats());
    }

    public function test_repeat_frequency_advances_dates_without_month_overflow(): void
    {
        $jan31 = now()->setDate(2026, 1, 31)->startOfDay();

        $this->assertSame('2026-02-28', RepeatFrequency::Monthly->advance($jan31)->toDateString());
        $this->assertSame('2026-02-14', RepeatFrequency::Weekly->advance($jan31->copy()->setDate(2026, 1, 31), 2)->toDateString());
        $this->assertSame('2026-02-01', RepeatFrequency::Daily->advance($jan31)->toDateString());
    }

    public function test_checklist_comments_replies_and_time_entries_hang_off_a_task(): void
    {
        $owner = User::factory()->create();
        $task = $this->task($owner);

        TaskChecklistItem::create(['task_id' => $task->id, 'title' => 'Outline', 'position' => 1]);
        TaskChecklistItem::create(['task_id' => $task->id, 'title' => 'Review', 'position' => 2]);

        $comment = TaskComment::create(['task_id' => $task->id, 'user_id' => $owner->id, 'body' => 'Looks good']);
        TaskComment::create(['task_id' => $task->id, 'user_id' => $owner->id, 'parent_id' => $comment->id, 'body' => 'Agreed']);
        $comment->mentions()->sync([$owner->id]);

        TaskTimeEntry::create(['task_id' => $task->id, 'user_id' => $owner->id, 'started_at' => now()->subHour(), 'ended_at' => now(), 'duration_minutes' => 60]);
        $running = TaskTimeEntry::create(['task_id' => $task->id, 'user_id' => $owner->id, 'started_at' => now()]);

        $this->assertCount(2, $task->checklistItems);
        // comments() returns top-level only; the reply nests under it.
        $this->assertCount(1, $task->comments);
        $this->assertCount(1, $comment->replies);
        $this->assertCount(1, $comment->mentions);
        $this->assertCount(2, $task->timeEntries);
        $this->assertTrue($running->isRunning());
        $this->assertCount(1, TaskTimeEntry::running()->get());
    }

    public function test_tasks_can_relate_to_a_workspace_record_and_carry_tags(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['owner_id' => $owner->id, 'name' => 'Website', 'status' => 'active']);
        $task = $this->task($owner, ['related_type' => 'project', 'related_id' => $project->id]);

        // Stored as the morph alias, never a class name.
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'related_type' => 'project']);
        $this->assertTrue($task->related->is($project));

        $tag = Tag::create(['owner_id' => $owner->id, 'name' => 'urgent', 'color' => 'red']);
        $task->tags()->sync([$tag->id]);

        $this->assertCount(1, $task->fresh()->tags);
        $this->assertDatabaseHas('taggables', ['tag_id' => $tag->id, 'taggable_type' => 'task', 'taggable_id' => $task->id]);
    }

    public function test_archive_and_overdue_scopes(): void
    {
        $owner = User::factory()->create();

        $this->task($owner, ['subject' => 'Live']);
        $this->task($owner, ['subject' => 'Archived', 'archived_at' => now()]);
        $this->task($owner, ['subject' => 'Late', 'due_date' => now()->subWeek()->toDateString()]);
        $this->task($owner, ['subject' => 'Late but done', 'due_date' => now()->subWeek()->toDateString(), 'status' => TaskStatus::Completed]);

        $this->assertSame(3, Task::active()->count());
        $this->assertSame(1, Task::archived()->count());
        // Completed tasks are never overdue.
        $this->assertSame(1, Task::overdue()->count());
    }

    public function test_soft_deleting_a_task_keeps_it_recoverable(): void
    {
        $owner = User::factory()->create();
        $task = $this->task($owner);

        $task->delete();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        $this->assertSame(0, Task::count());
        $this->assertSame(1, Task::withTrashed()->count());
    }
}
