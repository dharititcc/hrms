<?php

namespace App\Http\Controllers\API;

use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\AuditLog;
use App\Services\WorkspaceRecordResolver;
use App\Support\WorkspaceRecords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityLogController extends Controller
{
    public function __construct(private readonly WorkspaceRecordResolver $resolver) {}

    /**
     * Timeline for one record, or the workspace feed when no target is given.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAbility(Ability::View), 403);

        $validated = $request->validate([
            'entity' => ['nullable', 'string', Rule::in(WorkspaceRecords::aliases())],
            'entity_id' => ['nullable', 'integer', 'min:1', 'required_with:entity'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = AuditLog::query()
            ->where('owner_id', $request->user()->workspaceOwnerId())
            ->with('user')
            ->latest();

        if (isset($validated['entity'])) {
            // Same workspace-ownership check used by attachments.
            $target = $this->resolver->resolve($validated['entity'], $validated['entity_id'], $request->user());

            $query->where('entity', $target->getMorphClass())->where('entity_id', $target->getKey());
        }

        return ActivityLogResource::collection($query->paginate((int) ($validated['per_page'] ?? 25)))->response();
    }
}
