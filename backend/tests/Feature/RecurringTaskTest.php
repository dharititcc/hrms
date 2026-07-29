<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Notifications\TaskDueReminderNotification;
use App\Services\EmployeeInvitationService;
use App\Services\RecurringTaskService;
use App\Services\TaskReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RecurringTaskTest extends TestCase
{
    use RefreshDatabase;

    private function template(User $owner, array $overrides = []): Task
    {
        return Task::create([
            'owner_id' => $owner->id,
            'subject' => 'Weekly report',
            'status' => 'pending',
            'priority' => 'medium',
            'repeat_frequency' => 'weekly',
            'repeat_interval' => 1,
            'due_date' => now()->subWeeks(3)->toDateString(),
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

    public function test_missed_occurrences_are_caught_up_and_dated_correctly(): void
    {
        $owner = User::factory()->create();
        $template = $this->template($owner);

        // Anchor is three weeks back, so three weekly occurrences are due.
        $created = app(RecurringTaskService::class)->generateDue();

        $this->assertSame(3, $created);
        $instances = Task::where('recurrence_parent_id', $template->id)->orderBy('due_date')->get();
        $this->assertCount(3, $instances);
        $this->assertSame(now()->subWeeks(2)->toDateString(), $instances[0]->due_date->toDateString());
        $this->assertSame(now()->toDateString(), $instances[2]->due_date->toDateString());
    }

    public function test_generation_is_idempotent_across_runs(): void
    {
        $owner = User::factory()->create();
        $this->template($owner);
        $service = app(RecurringTaskService::class);

        $this->assertSame(3, $service->generateDue());
        // Nothing further is due until the next interval elapses.
        $this->assertSame(0, $service->generateDue());
        $this->assertSame(3, Task::whereNotNull('recurrence_parent_id')->count());
    }

    public function test_generated_instances_do_not_themselves_recur(): void
    {
        $owner = User::factory()->create();
        $this->template($owner);
        app(RecurringTaskService::class)->generateDue();

        $instance = Task::whereNotNull('recurrence_parent_id')->first();

        // Instances carry no rule, so a later run cannot cascade from them.
        $this->assertNull($instance->repeat_frequency);
        $this->assertFalse($instance->repeats());
    }

    public function test_recurrence_stops_at_repeat_until(): void
    {
        $owner = User::factory()->create();
        $this->template($owner, ['repeat_until' => now()->subWeeks(2)->toDateString()]);

        // Only the occurrence on or before repeat_until is generated.
        $this->assertSame(1, app(RecurringTaskService::class)->generateDue());
    }

    public function test_expired_and_archived_templates_are_skipped(): void
    {
        $owner = User::factory()->create();
        $this->template($owner, ['repeat_until' => now()->subMonths(2)->toDateString()]);
        $this->template($owner, ['subject' => 'Archived', 'archived_at' => now()]);

        $this->assertSame(0, app(RecurringTaskService::class)->generateDue());
    }

    public function test_interval_is_respected(): void
    {
        $owner = User::factory()->create();
        $this->template($owner, ['repeat_interval' => 2, 'due_date' => now()->subWeeks(4)->toDateString()]);

        // Every two weeks over four weeks yields two occurrences.
        $this->assertSame(2, app(RecurringTaskService::class)->generateDue());
    }

    public function test_instances_copy_assignees_checklist_and_lead_time(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        $template = $this->template($owner, [
            'start_date' => now()->subWeeks(3)->subDays(2)->toDateString(),
            'repeat_until' => now()->subWeeks(2)->toDateString(),
        ]);
        $template->assignees()->sync([$mate->id]);
        TaskChecklistItem::create(['task_id' => $template->id, 'title' => 'Gather figures', 'is_completed' => true, 'position' => 1]);

        app(RecurringTaskService::class)->generateDue();
        $instance = Task::where('recurrence_parent_id', $template->id)->firstOrFail();

        $this->assertCount(1, $instance->assignees);
        // The two-day lead between start and due is preserved.
        $this->assertSame(2, (int) abs($instance->due_date->diffInDays($instance->start_date)));
        // Checklist copies across unticked, which is the point of a recurring list.
        $this->assertCount(1, $instance->checklistItems);
        $this->assertFalse($instance->checklistItems->first()->is_completed);

        Notification::assertSentTo($mate, TaskAssignedNotification::class);
    }

    public function test_due_reminders_fire_once_per_day_and_skip_closed_tasks(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        $service = app(TaskReminderService::class);

        $due = Task::create(['owner_id' => $owner->id, 'subject' => 'Due today', 'status' => 'pending', 'priority' => 'high', 'due_date' => now()->toDateString(), 'created_by' => $owner->id]);
        $due->assignees()->sync([$mate->id]);

        $overdue = Task::create(['owner_id' => $owner->id, 'subject' => 'Overdue', 'status' => 'in_progress', 'priority' => 'high', 'due_date' => now()->subWeek()->toDateString(), 'created_by' => $owner->id]);
        $overdue->assignees()->sync([$mate->id]);

        // Completed tasks are never chased, however late.
        $done = Task::create(['owner_id' => $owner->id, 'subject' => 'Done', 'status' => 'completed', 'priority' => 'high', 'due_date' => now()->subWeek()->toDateString(), 'created_by' => $owner->id]);
        $done->assignees()->sync([$mate->id]);

        $this->assertSame(2, $service->sendDueReminders());
        Notification::assertSentToTimes($mate, TaskDueReminderNotification::class, 2);

        // A second run the same day must not re-notify.
        $this->assertSame(0, $service->sendDueReminders());
        Notification::assertSentToTimes($mate, TaskDueReminderNotification::class, 2);

        // The next day, the still-open tasks are chased again.
        $this->travel(1)->day();
        $this->assertSame(2, $service->sendDueReminders());
    }

    public function test_commands_run_and_report(): void
    {
        $owner = User::factory()->create();
        $this->template($owner);

        $this->artisan('tasks:generate-recurrences')->expectsOutputToContain('Generated 3')->assertSuccessful();
        $this->artisan('tasks:send-due-reminders')->assertSuccessful();
    }
}
