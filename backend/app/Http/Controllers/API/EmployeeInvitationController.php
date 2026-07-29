<?php

namespace App\Http\Controllers\API;

use App\Enums\Action;
use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Services\EmployeeInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeInvitationController extends Controller
{
    public function __construct(private readonly EmployeeInvitationService $service) {}

    public function store(Request $request, Employee $employee): JsonResponse
    {
        // Inviting grants workspace access, so it needs the assign ability
        // on top of the usual update check.
        $this->authorize('update', $employee);
        abort_unless($request->user()->hasPermission(Module::Employees, Action::Assign), 403);

        $this->service->invite($employee);

        return response()->json([
            'message' => 'Invitation sent.',
            'data' => new EmployeeResource($employee->refresh()),
        ], 201);
    }

    public function destroy(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('update', $employee);
        abort_unless($request->user()->hasPermission(Module::Employees, Action::Assign), 403);

        $this->service->revoke($employee);

        return response()->json(['message' => 'Account access revoked.']);
    }
}
