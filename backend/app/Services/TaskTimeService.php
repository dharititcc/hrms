<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskTimeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskTimeService
{
    public function list(Task $task): Collection
    {
        return $task->timeEntries()->with('user')->get();
    }

    /**
     * Starts a timer for this user on this task.
     *
     * A user can only have one timer running at a time, so any timer already
     * running is stopped first — switching tasks should not silently accrue
     * time against both.
     */
    public function start(Task $task, User $user): TaskTimeEntry
    {
        return DB::transaction(function () use ($task, $user): TaskTimeEntry {
            $this->stopRunningFor($user);

            return TaskTimeEntry::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'started_at' => now(),
                'is_manual' => false,
            ]);
        });
    }

    public function stop(Task $task, User $user): TaskTimeEntry
    {
        $entry = TaskTimeEntry::query()
            ->where('task_id', $task->id)
            ->where('user_id', $user->id)
            ->running()
            ->latest('started_at')
            ->first();

        if ($entry === null) {
            throw ValidationException::withMessages(['timer' => 'No timer is running for you on this task.']);
        }

        return DB::transaction(fn (): TaskTimeEntry => $this->close($entry));
    }

    /** The user's currently running entry, if any, across all tasks. */
    public function runningFor(User $user): ?TaskTimeEntry
    {
        return TaskTimeEntry::query()->where('user_id', $user->id)->running()->latest('started_at')->first();
    }

    public function logManual(Task $task, User $user, array $attributes): TaskTimeEntry
    {
        $startedAt = Carbon::parse($attributes['started_at']);
        $endedAt = Carbon::parse($attributes['ended_at']);

        return DB::transaction(fn (): TaskTimeEntry => TaskTimeEntry::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_minutes' => $this->minutesBetween($startedAt, $endedAt),
            'description' => $attributes['description'] ?? null,
            'is_manual' => true,
        ]));
    }

    public function delete(TaskTimeEntry $entry): void
    {
        DB::transaction(fn () => $entry->delete());
    }

    /** Total minutes logged against a task by everyone. */
    public function totalMinutes(Task $task): int
    {
        return (int) $task->timeEntries()->sum('duration_minutes');
    }

    private function stopRunningFor(User $user): void
    {
        TaskTimeEntry::query()
            ->where('user_id', $user->id)
            ->running()
            ->get()
            ->each(fn (TaskTimeEntry $entry) => $this->close($entry));
    }

    private function close(TaskTimeEntry $entry): TaskTimeEntry
    {
        $endedAt = now();

        $entry->update([
            'ended_at' => $endedAt,
            'duration_minutes' => $this->minutesBetween($entry->started_at, $endedAt),
        ]);

        return $entry->refresh();
    }

    private function minutesBetween(Carbon $start, Carbon $end): int
    {
        return max(0, (int) round($start->diffInSeconds($end) / 60));
    }
}
