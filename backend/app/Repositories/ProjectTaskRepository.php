<?php

namespace App\Repositories;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Database\Eloquent\Collection;

class ProjectTaskRepository
{
    public function listForProject(Project $project): Collection
    {
        return ProjectTask::query()
            ->where('project_id', $project->id)
            ->with('assignee')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    public function create(int $ownerId, Project $project, array $attributes): ProjectTask
    {
        $status = $attributes['status'] ?? TaskStatus::Todo->value;

        return ProjectTask::create([
            ...$attributes,
            'owner_id' => $ownerId,
            'project_id' => $project->id,
            'position' => $this->nextPosition($project->id, $status),
        ]);
    }

    public function update(ProjectTask $task, array $attributes): ProjectTask
    {
        $task->update($attributes);

        return $task->refresh();
    }

    /** Moves a task to another column, appending it to the end of that column. */
    public function move(ProjectTask $task, TaskStatus $status): ProjectTask
    {
        if ($task->status !== $status) {
            $task->update([
                'status' => $status,
                'position' => $this->nextPosition($task->project_id, $status->value),
            ]);
        }

        return $task->refresh();
    }

    public function delete(ProjectTask $task): void
    {
        $task->delete();
    }

    private function nextPosition(int $projectId, string $status): int
    {
        return (int) ProjectTask::query()
            ->where('project_id', $projectId)
            ->where('status', $status)
            ->max('position') + 1;
    }
}
