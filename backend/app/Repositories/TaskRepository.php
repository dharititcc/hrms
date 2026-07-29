<?php

namespace App\Repositories;

use App\Enums\Action;
use App\Enums\Module;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class TaskRepository
{
    private const WITH = ['assignees', 'tags'];

    /**
     * Columns a client may sort by. Anything outside this list is ignored, so
     * the sort parameter can never reach the query as raw SQL.
     */
    private const SORTABLE = ['subject', 'due_date', 'start_date', 'created_at', 'updated_at', 'priority', 'status'];

    /**
     * Priority and status are stored as strings, so alphabetical ordering would
     * be meaningless. These CASE expressions impose the real ranking and work
     * on both MySQL and SQLite (MySQL's FIELD() does not).
     */
    private const PRIORITY_ORDER = "CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END";

    private const STATUS_ORDER = "CASE status WHEN 'pending' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'review' THEN 3 WHEN 'on_hold' THEN 4 WHEN 'completed' THEN 5 ELSE 6 END";

    public function paginateForOwner(int $ownerId, array $filters = [], ?User $viewer = null): LengthAwarePaginator
    {
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return Task::query()
            ->where('owner_id', $ownerId)
            // Without tasks.view-all — a Client — only tasks they are attached
            // to, or that were explicitly marked public.
            ->when(
                $viewer !== null && ! $viewer->hasPermission(Module::Tasks, Action::ViewAll),
                fn ($query) => $query->where(function ($query) use ($viewer): void {
                    $query->where('is_public', true)
                        ->orWhere('created_by', $viewer->id)
                        ->orWhereHas('assignees', fn ($q) => $q->where('users.id', $viewer->id))
                        ->orWhereHas('followers', fn ($q) => $q->where('users.id', $viewer->id));
                }),
            )
            // The list shows what each task hangs off, which the board does not need.
            ->with([...self::WITH, 'related'])
            // Archived tasks are hidden unless explicitly asked for.
            ->when($filters['archived'] ?? false, fn ($query) => $query->archived(), fn ($query) => $query->active())
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('subject', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, array $status) => $query->whereIn('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, array $priority) => $query->whereIn('priority', $priority))
            ->when(
                $filters['assignee_id'] ?? null,
                fn ($query, int $userId) => $query->whereHas('assignees', fn ($assignees) => $assignees->where('users.id', $userId)),
            )
            ->when($filters['unassigned'] ?? false, fn ($query) => $query->whereDoesntHave('assignees'))
            ->when($filters['related_type'] ?? null, fn ($query, string $type) => $query->where('related_type', $type))
            ->when($filters['related_id'] ?? null, fn ($query, int $id) => $query->where('related_id', $id))
            ->when($filters['due'] ?? null, fn ($query, string $due) => $this->applyDueFilter($query, $due))
            ->when(
                in_array($filters['sort'] ?? null, self::SORTABLE, strict: true),
                fn ($query) => $this->applySort($query, $filters['sort'], $direction),
                // Default view: soonest deadline first, undated last.
                fn ($query) => $query->orderByRaw('due_date is null')->orderBy('due_date')->orderByRaw(self::PRIORITY_ORDER),
            )
            ->paginate((int) ($filters['per_page'] ?? 25))
            ->withQueryString();
    }

    private function applySort(Builder $query, string $sort, string $direction): Builder
    {
        return match ($sort) {
            'priority' => $query->orderByRaw(self::PRIORITY_ORDER.' '.$direction),
            'status' => $query->orderByRaw(self::STATUS_ORDER.' '.$direction),
            // Undated tasks sort last regardless of direction.
            'due_date', 'start_date' => $query->orderByRaw("{$sort} is null")->orderBy($sort, $direction),
            default => $query->orderBy($sort, $direction),
        };
    }

    private function applyDueFilter(Builder $query, string $due): Builder
    {
        $today = now()->toDateString();

        return match ($due) {
            'overdue' => $query->overdue(),
            'today' => $query->whereDate('due_date', $today),
            'week' => $query->whereBetween('due_date', [$today, now()->addWeek()->toDateString()]),
            'none' => $query->whereNull('due_date'),
            default => $query,
        };
    }

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
