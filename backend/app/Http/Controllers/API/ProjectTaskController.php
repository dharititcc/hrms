<?php

namespace App\Http\Controllers\API;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectTaskRequest;
use App\Http\Requests\Project\UpdateProjectTaskRequest;
use App\Http\Resources\ProjectTaskResource;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Services\ProjectTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectTaskController extends Controller
{
    public function __construct(private readonly ProjectTaskService $service) {}

    public function index(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return ProjectTaskResource::collection($this->service->list($project))->response();
    }

    public function store(StoreProjectTaskRequest $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        return (new ProjectTaskResource($this->service->create($request->user()->workspaceOwnerId(), $project, $request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProjectTaskRequest $request, ProjectTask $task): ProjectTaskResource
    {
        $this->authorize('update', $task);

        return new ProjectTaskResource($this->service->update($task, $request->validated()));
    }

    public function updateStatus(Request $request, ProjectTask $task): ProjectTaskResource
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ]);

        return new ProjectTaskResource($this->service->move($task, TaskStatus::from($validated['status'])));
    }

    public function destroy(Request $request, ProjectTask $task): JsonResponse
    {
        $this->authorize('delete', $task);
        $this->service->delete($task);

        return response()->json(['message' => 'Task deleted.']);
    }
}
