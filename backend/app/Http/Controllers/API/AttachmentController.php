<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attachment\StoreAttachmentRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Services\AttachmentService;
use App\Support\WorkspaceRecords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    public function __construct(private readonly AttachmentService $service) {}

    /** Lists attachments for one record, e.g. ?attachable_type=project&attachable_id=3 */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'attachable_type' => ['required', 'string', Rule::in(WorkspaceRecords::aliases())],
            'attachable_id' => ['required', 'integer', 'min:1'],
        ]);

        // Throws 404 if the parent is outside the caller's workspace.
        $attachable = $this->service->resolveAttachable($validated['attachable_type'], $validated['attachable_id'], $request->user());

        $attachments = Attachment::query()
            ->where('attachable_type', $attachable->getMorphClass())
            ->where('attachable_id', $attachable->getKey())
            ->with('uploader')
            ->latest()
            ->get();

        return AttachmentResource::collection($attachments)->response();
    }

    public function store(StoreAttachmentRequest $request): JsonResponse
    {
        $this->authorize('create', Attachment::class);

        $validated = $request->validated();
        $attachable = $this->service->resolveAttachable($validated['attachable_type'], $validated['attachable_id'], $request->user());

        $attachment = $this->service->store($request->file('file'), $attachable, $request->user());

        return (new AttachmentResource($attachment->load('uploader')))
            ->response()
            ->setStatusCode(201);
    }

    public function download(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    public function destroy(Request $request, Attachment $attachment): JsonResponse
    {
        $this->authorize('delete', $attachment);
        $this->service->delete($attachment);

        return response()->json(['message' => 'Attachment deleted.']);
    }
}
