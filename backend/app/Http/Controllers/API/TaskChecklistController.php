<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskChecklistItemResource;
use App\Models\Task;
use App\Models\TaskChecklistItem;
use App\Services\TaskChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskChecklistController extends Controller
{
    public function __construct(private readonly TaskChecklistService $service) {}

    public function index(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return TaskChecklistItemResource::collection($this->service->list($task))->response();
    }

    public function store(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate(['title' => ['required', 'string', 'max:255']]);

        return (new TaskChecklistItemResource($this->service->create($task, $validated)))
            ->response()
            ->setStatusCode(201);
    }

    public function update(Request $request, TaskChecklistItem $item): TaskChecklistItemResource
    {
        // Checklist items carry no owner_id; authorize through the parent task.
        $this->authorize('update', $item->task);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'is_completed' => ['sometimes', 'boolean'],
        ]);

        return new TaskChecklistItemResource($this->service->update($item, $validated, $request->user()));
    }

    public function reorder(Request $request, Task $task): JsonResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'ordered_ids' => ['required', 'array'],
            'ordered_ids.*' => ['integer'],
        ]);

        return TaskChecklistItemResource::collection($this->service->reorder($task, $validated['ordered_ids']))->response();
    }

    public function destroy(Request $request, TaskChecklistItem $item): JsonResponse
    {
        $this->authorize('update', $item->task);
        $this->service->delete($item);

        return response()->json(['message' => 'Checklist item deleted.']);
    }
}
