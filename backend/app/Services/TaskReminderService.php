<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Notifications\TaskDueReminderNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class TaskReminderService
{
    /**
     * Notifies assignees of tasks due today or already overdue.
     *
     * last_reminded_on makes this idempotent: running twice in a day sends one
     * reminder, while an overdue task is chased again each new day.
     *
     * @return int number of tasks that triggered a reminder
     */
    public function sendDueReminders(?CarbonInterface $asOf = null): int
    {
        $today = Carbon::parse($asOf ?? now())->startOfDay();
        $reminded = 0;

        Task::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<=', $today)
            ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
            ->whereNull('archived_at')
            ->where(fn ($query) => $query->whereNull('last_reminded_on')->orWhereDate('last_reminded_on', '<', $today))
            ->with('assignees')
            ->chunkById(100, function ($tasks) use (&$reminded, $today): void {
                foreach ($tasks as $task) {
                    // Stamp regardless of recipients so an unassigned task is
                    // not re-examined every run.
                    $task->forceFill(['last_reminded_on' => $today])->saveQuietly();

                    if ($task->assignees->isEmpty()) {
                        continue;
                    }

                    Notification::send(
                        $task->assignees,
                        new TaskDueReminderNotification($task, overdue: $task->due_date->lessThan($today)),
                    );

                    $reminded++;
                }
            });

        return $reminded;
    }
}
