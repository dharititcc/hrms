<?php

namespace App\Http\Controllers\API;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $service) {}

    /** Board for one project. Tasks relate to projects polymorphically. */
    public function indexForProject(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return TaskResource::collection($this->service->listForRelated($project))->response();
    }

    public function storeForProject(StoreTaskRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
        $this->authorize('create', Task::class);

        $task = $this->service->create($request->user(), $request->validated(), $project);

        return (new TaskResource($task))->response()->setStatusCode(201);
    }

    public function show(Request $request, Task $task): TaskResource
    {
        $this->authorize('view', $task);

        return new TaskResource($task->load(['assignees', 'tags', 'related']));
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        return new TaskResource($this->service->update($task, $request->user(), $request->validated()));
    }

    public function updateStatus(Request $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ]);

        return new TaskResource($this->service->move($task, TaskStatus::from($validated['status']), $request->user()));
    }

    public function archive(Request $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        return new TaskResource($this->service->setArchived($task, true));
    }

    public function restore(Request $request, Task $task): TaskResource
    {
        $this->authorize('update', $task);

        return new TaskResource($this->service->setArchived($task, false));
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        $this->authorize('delete', $task);
        $this->service->delete($task);

        return response()->json(['message' => 'Task deleted.']);
    }
}
