<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Notifications\TaskAssignedNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Generates the next occurrences of recurring tasks.
 *
 * Only templates recur — a task with a repeat rule and no recurrence_parent_id.
 * Generated instances carry recurrence_parent_id and no rule of their own, so
 * they can never themselves spawn further tasks.
 */
class RecurringTaskService
{
    /**
     * Safety valve. A template whose anchor is years in the past would
     * otherwise generate one task per interval in a single run; instead it
     * catches up gradually and the caller can see the cap was hit.
     */
    private const MAX_PER_TEMPLATE = 50;

    public function generateDue(?CarbonInterface $asOf = null): int
    {
        $today = Carbon::parse($asOf ?? now())->startOfDay();
        $created = 0;

        Task::query()
            ->whereNotNull('repeat_frequency')
            ->whereNull('recurrence_parent_id')
            ->whereNull('archived_at')
            // repeat_until is deliberately not filtered here. A series that
            // ended last week may still have occurrences that were never
            // generated; the loop below enforces the boundary exactly.
            ->with(['assignees', 'tags', 'checklistItems'])
            ->chunkById(100, function ($templates) use (&$created, $today): void {
                foreach ($templates as $template) {
                    $created += $this->generateForTemplate($template, $today);
                }
            });

        return $created;
    }

    private function generateForTemplate(Task $template, Carbon $today): int
    {
        $frequency = $template->repeat_frequency;
        $interval = max(1, (int) $template->repeat_interval);

        // Where the series is measured from: the last generated occurrence, or
        // failing that the template's own dates.
        $anchor = Carbon::parse($template->last_recurred_at ?? $template->due_date ?? $template->start_date ?? $template->created_at)->startOfDay();

        $created = 0;
        $next = $frequency->advance($anchor, $interval);
        $lastGenerated = null;

        while ($next->lessThanOrEqualTo($today) && $created < self::MAX_PER_TEMPLATE) {
            if ($template->repeat_until !== null && $next->greaterThan($template->repeat_until)) {
                break;
            }

            $this->createInstance($template, $next);
            $lastGenerated = $next->copy();
            $created++;

            $next = $frequency->advance($next, $interval);
        }

        if ($lastGenerated !== null) {
            $template->forceFill(['last_recurred_at' => $lastGenerated])->saveQuietly();
        }

        return $created;
    }

    private function createInstance(Task $template, Carbon $dueDate): void
    {
        // Preserve the template's lead time between start and due dates.
        // abs() because Carbon 3 returns a signed difference.
        $leadDays = $template->start_date !== null && $template->due_date !== null
            ? (int) abs($template->due_date->diffInDays($template->start_date))
            : null;

        $instance = DB::transaction(function () use ($template, $dueDate, $leadDays): Task {
            $instance = Task::create([
                'owner_id' => $template->owner_id,
                'subject' => $template->subject,
                'description' => $template->description,
                'status' => TaskStatus::Pending,
                'priority' => $template->priority,
                'is_public' => $template->is_public,
                'is_billable' => $template->is_billable,
                'hourly_rate' => $template->hourly_rate,
                'estimated_hours' => $template->estimated_hours,
                'start_date' => $leadDays === null ? null : $dueDate->copy()->subDays($leadDays),
                'due_date' => $dueDate,
                'related_type' => $template->related_type,
                'related_id' => $template->related_id,
                'recurrence_parent_id' => $template->id,
                'created_by' => $template->created_by,
                'updated_by' => $template->created_by,
                // The rule stays on the template only.
                'repeat_interval' => 1,
            ]);

            $instance->assignees()->sync($template->assignees->pluck('id'));
            $instance->tags()->sync($template->tags->pluck('id'));

            // A recurring checklist is usually the point, so copy it fresh.
            foreach ($template->checklistItems as $item) {
                TaskChecklistItem::create([
                    'task_id' => $instance->id,
                    'title' => $item->title,
                    'is_completed' => false,
                    'position' => $item->position,
                ]);
            }

            return $instance;
        });

        $this->notifyAssignees($template, $instance);
    }

    /**
     * Notifies the copied assignees. The template's creator is named as the
     * actor because they set the recurrence up; if that account is gone the
     * notification is skipped rather than attributed to nobody.
     */
    private function notifyAssignees(Task $template, Task $instance): void
    {
        $actor = $template->creator;

        if ($actor === null) {
            return;
        }

        $recipients = $template->assignees->reject(fn ($user) => $user->id === $actor->id);

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TaskAssignedNotification($instance, $actor));
        }
    }
}
