<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Repositories\TaskRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TaskService
{
    public function __construct(private readonly TaskRepository $repository) {}

    public function listForRelated(Model $related): Collection
    {
        return $this->repository->listForRelated($related);
    }

    public function create(User $author, array $attributes, ?Model $related = null): Task
    {
        return DB::transaction(function () use ($author, $attributes, $related): Task {
            $assigneeIds = $attributes['assignee_ids'] ?? [];
            unset($attributes['assignee_ids']);

            $task = $this->repository->create($author->workspaceOwnerId(), [
                ...$attributes,
                'created_by' => $author->id,
                'updated_by' => $author->id,
            ], $related);

            $task->assignees()->sync($assigneeIds);

            return $task->load(['assignees', 'tags']);
        });
    }

    public function update(Task $task, User $editor, array $attributes): Task
    {
        return DB::transaction(function () use ($task, $editor, $attributes): Task {
            $assigneeIds = $attributes['assignee_ids'] ?? null;
            unset($attributes['assignee_ids']);

            $task = $this->repository->update($task, [...$attributes, 'updated_by' => $editor->id]);

            if ($assigneeIds !== null) {
                $task->assignees()->sync($assigneeIds);
            }

            return $task->load(['assignees', 'tags']);
        });
    }

    public function move(Task $task, TaskStatus $status, User $editor): Task
    {
        return DB::transaction(function () use ($task, $status, $editor): Task {
            $task = $this->repository->move($task, $status);
            $task->updateQuietly(['updated_by' => $editor->id]);

            return $task->load(['assignees', 'tags']);
        });
    }

    public function setArchived(Task $task, bool $archived): Task
    {
        return DB::transaction(fn (): Task => $this->repository->setArchived($task, $archived)->load(['assignees', 'tags']));
    }

    public function delete(Task $task): void
    {
        DB::transaction(fn () => $this->repository->delete($task));
    }
}
