<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use App\Repositories\TaskRepository;
use Illuminate\Support\Facades\Notification;
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
        [$task, $added] = DB::transaction(function () use ($author, $attributes, $related): array {
            $assigneeIds = $attributes['assignee_ids'] ?? [];
            unset($attributes['assignee_ids']);

            $task = $this->repository->create($author->workspaceOwnerId(), [
                ...$attributes,
                'created_by' => $author->id,
                'updated_by' => $author->id,
            ], $related);

            $result = $task->assignees()->sync($assigneeIds);

            return [$task, $result['attached']];
        });

        $this->notifyAssigned($task, $added, $author);

        return $task->load(['assignees', 'tags']);
    }

    public function update(Task $task, User $editor, array $attributes): Task
    {
        [$task, $added] = DB::transaction(function () use ($task, $editor, $attributes): array {
            $assigneeIds = $attributes['assignee_ids'] ?? null;
            unset($attributes['assignee_ids']);

            $task = $this->repository->update($task, [...$attributes, 'updated_by' => $editor->id]);

            // Only newly attached assignees are notified, so editing an
            // unrelated field never re-notifies the existing team.
            $added = $assigneeIds === null ? [] : $task->assignees()->sync($assigneeIds)['attached'];

            return [$task, $added];
        });

        $this->notifyAssigned($task, $added, $editor);

        return $task->load(['assignees', 'tags']);
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

    /**
     * @param  list<int>  $userIds  ids newly attached as assignees
     */
    private function notifyAssigned(Task $task, array $userIds, User $actor): void
    {
        // Assigning yourself is not worth an email.
        $recipients = User::query()
            ->whereIn('id', $userIds)
            ->whereKeyNot($actor->id)
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new TaskAssignedNotification($task, $actor));
        }
    }
}
