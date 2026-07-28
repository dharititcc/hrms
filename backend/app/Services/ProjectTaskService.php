<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Repositories\ProjectTaskRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProjectTaskService
{
    public function __construct(private readonly ProjectTaskRepository $repository) {}

    public function list(Project $project): Collection
    {
        return $this->repository->listForProject($project);
    }

    public function create(int $ownerId, Project $project, array $attributes): ProjectTask
    {
        return DB::transaction(function () use ($ownerId, $project, $attributes): ProjectTask {
            return $this->repository->create($ownerId, $project, $attributes)->load('assignee');
        });
    }

    public function update(ProjectTask $task, array $attributes): ProjectTask
    {
        return DB::transaction(function () use ($task, $attributes): ProjectTask {
            return $this->repository->update($task, $attributes)->load('assignee');
        });
    }

    public function move(ProjectTask $task, TaskStatus $status): ProjectTask
    {
        return DB::transaction(function () use ($task, $status): ProjectTask {
            return $this->repository->move($task, $status)->load('assignee');
        });
    }

    public function delete(ProjectTask $task): void
    {
        DB::transaction(fn () => $this->repository->delete($task));
    }
}
