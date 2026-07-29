<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TaskChecklistService
{
    public function list(Task $task): Collection
    {
        return $task->checklistItems()->with('completer')->get();
    }

    public function create(Task $task, array $attributes): TaskChecklistItem
    {
        // is_completed is set explicitly rather than relying on the column
        // default, which would leave the returned model's attribute null.
        return DB::transaction(fn (): TaskChecklistItem => TaskChecklistItem::create([
            'task_id' => $task->id,
            'title' => $attributes['title'],
            'is_completed' => false,
            'position' => $this->nextPosition($task),
        ]));
    }

    public function update(TaskChecklistItem $item, array $attributes, User $editor): TaskChecklistItem
    {
        return DB::transaction(function () use ($item, $attributes, $editor): TaskChecklistItem {
            $payload = [];

            if (array_key_exists('title', $attributes)) {
                $payload['title'] = $attributes['title'];
            }

            if (array_key_exists('is_completed', $attributes)) {
                $completed = (bool) $attributes['is_completed'];

                // Record who ticked it and when; clear both when unticked.
                $payload['is_completed'] = $completed;
                $payload['completed_at'] = $completed ? ($item->completed_at ?? now()) : null;
                $payload['completed_by'] = $completed ? ($item->completed_by ?? $editor->id) : null;
            }

            $item->update($payload);

            return $item->refresh()->load('completer');
        });
    }

    /** Applies a caller-supplied order, ignoring ids from other tasks. */
    public function reorder(Task $task, array $orderedIds): Collection
    {
        DB::transaction(function () use ($task, $orderedIds): void {
            $owned = $task->checklistItems()->pluck('id')->all();

            foreach (array_values(array_intersect($orderedIds, $owned)) as $index => $id) {
                TaskChecklistItem::query()->where('id', $id)->update(['position' => $index + 1]);
            }
        });

        return $this->list($task);
    }

    public function delete(TaskChecklistItem $item): void
    {
        DB::transaction(fn () => $item->delete());
    }

    private function nextPosition(Task $task): int
    {
        return (int) $task->checklistItems()->max('position') + 1;
    }
}
