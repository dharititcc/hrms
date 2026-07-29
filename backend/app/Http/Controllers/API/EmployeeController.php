<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Services\EmployeePermissionService;
use App\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $service,
        private readonly EmployeePermissionService $permissions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);
        $employee = $this->service->list($request->user()->workspaceOwnerId(), $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string'],
            'role' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]));

        return EmployeeResource::collection($employee)->response();
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $this->authorize('create', Employee::class);

        $employee = $this->service->create($request->user()->workspaceOwnerId(), $request->safe()->except('permissions'));
        $this->syncPermissions($request, $employee);

        return (new EmployeeResource($employee->refresh()))->response()->setStatusCode(201);
    }

    public function show(Request $request, Employee $employee): EmployeeResource
    {
        $this->authorize('view', $employee);

        return new EmployeeResource($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $this->authorize('update', $employee);

        $updated = $this->service->update($employee, $request->safe()->except('permissions'));
        $this->syncPermissions($request, $updated);

        return new EmployeeResource($updated->refresh());
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('delete', $employee);
        $this->service->delete($employee);

        return response()->json(['message' => 'Employee deleted.']);
    }

    /**
     * Only touched when the caller sends a list.
     *
     * Omitting the key leaves access as it is, so a client that knows nothing
     * about permissions cannot wipe somebody's overrides by saving a name.
     */
    private function syncPermissions(Request $request, Employee $employee): void
    {
        if (! $request->has('permissions')) {
            return;
        }

        $this->permissions->sync($employee, $request->user(), (array) $request->input('permissions', []));
    }
}
