<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Http\Resources\StaffResource;
use App\Models\Staff;
use App\Services\StaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function __construct(private readonly StaffService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Staff::class);
        $staff = $this->service->list($request->user()->workspaceOwnerId(), $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
            'role' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]));

        return StaffResource::collection($staff)->response();
    }

    public function store(StoreStaffRequest $request): JsonResponse
    {
        $this->authorize('create', Staff::class);

        return (new StaffResource($this->service->create($request->user()->workspaceOwnerId(), $request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Staff $staff): StaffResource
    {
        $this->authorize('view', $staff);

        return new StaffResource($staff);
    }

    public function update(UpdateStaffRequest $request, Staff $staff): StaffResource
    {
        $this->authorize('update', $staff);

        return new StaffResource($this->service->update($staff, $request->validated()));
    }

    public function destroy(Request $request, Staff $staff): JsonResponse
    {
        $this->authorize('delete', $staff);
        $this->service->delete($staff);

        return response()->json(['message' => 'Staff member deleted.']);
    }
}
