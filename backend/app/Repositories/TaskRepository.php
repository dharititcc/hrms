<?php

namespace App\Repositories;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class TaskRepository
{
    private const WITH = ['assignees', 'tags'];

    /** Tasks attached to one record, ordered for a kanban board. */
    public function listForRelated(Model $related): Collection
    {
        return Task::query()
            ->where('related_type', $related->getMorphClass())
            ->where('related_id', $related->getKey())
            ->active()
            ->with(self::WITH)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function create(int $ownerId, array $attributes, ?Model $related = null): Task
    {
        $status = $attributes['status'] ?? TaskStatus::Pending->value;

        return Task::create([
            ...$attributes,
            'owner_id' => $ownerId,
            'related_type' => $related?->getMorphClass(),
            'related_id' => $related?->getKey(),
            'position' => $this->nextPosition($ownerId, $status),
            'completed_at' => $this->completionTimestamp($status),
        ]);
    }

    public function update(Task $task, array $attributes): Task
    {
        if (array_key_exists('status', $attributes)) {
            $attributes['completed_at'] = $this->completionTimestamp($attributes['status'], $task);
        }

        $task->update($attributes);

        return $task->refresh();
    }

    /** Moves a task to another column, appending it to the end of that column. */
    public function move(Task $task, TaskStatus $status): Task
    {
        if ($task->status !== $status) {
            $task->update([
                'status' => $status,
                'position' => $this->nextPosition($task->owner_id, $status->value),
                'completed_at' => $this->completionTimestamp($status, $task),
            ]);
        }

        return $task->refresh();
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    public function setArchived(Task $task, bool $archived): Task
    {
        $task->update(['archived_at' => $archived ? now() : null]);

        return $task->refresh();
    }

    private function nextPosition(int $ownerId, string $status): int
    {
        return (int) Task::query()
            ->where('owner_id', $ownerId)
            ->where('status', $status)
            ->max('position') + 1;
    }

    /**
     * Stamps completed_at when a task first reaches Completed, and clears it if
     * the task is reopened. Cancelled is closed but not completed, so it does
     * not carry a completion timestamp.
     */
    private function completionTimestamp(TaskStatus|string $status, ?Task $existing = null): mixed
    {
        $status = $status instanceof TaskStatus ? $status : TaskStatus::from($status);

        if ($status !== TaskStatus::Completed) {
            return null;
        }

        return $existing?->completed_at ?? now();
    }
}
