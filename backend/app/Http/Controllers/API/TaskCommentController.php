<?php

namespace App\Http\Controllers\API;

use App\Enums\Action;
use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Http\Requests\Task\StoreTaskCommentRequest;
use App\Http\Resources\TaskCommentResource;
use App\Models\Task;
use App\Models\TaskComment;
use App\Services\TaskCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskCommentController extends Controller
{
    public function __construct(private readonly TaskCommentService $service) {}

    public function index(Request $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);

        return TaskCommentResource::collection($this->service->list($task))->response();
    }

    public function store(StoreTaskCommentRequest $request, Task $task): JsonResponse
    {
        $this->authorize('view', $task);
        // Commenting is its own ability, so read-only roles such as Client can
        // still take part without being able to edit the task.
        abort_unless($request->user()->hasPermission(Module::Tasks, Action::Comment), 403);

        $comment = $this->service->create($task, $request->user(), $request->validated());

        return (new TaskCommentResource($comment))->response()->setStatusCode(201);
    }

    public function update(Request $request, TaskComment $comment): TaskCommentResource
    {
        $this->authorize('update', $comment);

        $validated = $request->validate(['body' => ['required', 'string', 'max:20000']]);

        return new TaskCommentResource($this->service->update($comment, $validated));
    }

    public function destroy(Request $request, TaskComment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);
        $this->service->delete($comment);

        return response()->json(['message' => 'Comment deleted.']);
    }
}
