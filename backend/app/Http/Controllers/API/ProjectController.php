<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(private readonly ProjectService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Project::class);
        $projects = $this->service->list($request->user()->workspaceOwnerId(), $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]));

        return ProjectResource::collection($projects)->response();
    }

    public function store(StoreProjectRequest $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        return (new ProjectResource($this->service->create($request->user()->workspaceOwnerId(), $request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Project $project): ProjectResource
    {
        $this->authorize('view', $project);

        return new ProjectResource($this->service->find($project));
    }

    public function update(UpdateProjectRequest $request, Project $project): ProjectResource
    {
        $this->authorize('update', $project);

        return new ProjectResource($this->service->update($project, $request->validated()));
    }

    public function destroy(Request $request, Project $project): JsonResponse
    {
        $this->authorize('delete', $project);
        $this->service->delete($project);

        return response()->json(['message' => 'Project deleted.']);
    }
}
