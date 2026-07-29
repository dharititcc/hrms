<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTimeEntryRequest;
use App\Http\Resources\TaskTimeEntryResource;
use App\Models\Task;
use App\Models\TaskTimeEntry;
use App\Services\TaskTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskTimeEntryController extends Controller
{
    public function __construct(private readonly TaskTimeService $service) {}

    public function index(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return TaskTimeEntryResource::collection($this->service->list($task))
            ->additional(['meta' => ['total_minutes' => $this->service->totalMinutes($task)]])
            ->response();
    }

    /** The caller's running timer, if any, across all tasks. */
    public function running(Request $request): JsonResponse
    {
        $entry = $this->service->runningFor($request->user());

        return response()->json(['data' => $entry === null ? null : new TaskTimeEntryResource($entry->load('user'))]);
    }

    public function start(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return (new TaskTimeEntryResource($this->service->start($task, $request->user())->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function stop(Request $request, Task $task): TaskTimeEntryResource
    {
        $this->authorize('view', $task);

        return new TaskTimeEntryResource($this->service->stop($task, $request->user())->load('user'));
    }

    public function store(StoreTimeEntryRequest $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return (new TaskTimeEntryResource($this->service->logManual($task, $request->user(), $request->validated())->load('user')))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, TaskTimeEntry $entry): JsonResponse
    {
        $this->authorize('view', $entry->task);

        // Own entries only, unless the caller can edit the task outright.
        abort_unless(
            $entry->user_id === $request->user()->id || $request->user()->can('update', $entry->task),
            403,
        );

        $this->service->delete($entry);

        return response()->json(['message' => 'Time entry deleted.']);
    }
}
