<?php

namespace App\Http\Controllers\API;

use App\Enums\Action;
use App\Enums\Module;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * The caller's role and permissions, so the UI can hide what it cannot do.
     *
     * This is a convenience for rendering, never the enforcement point: the
     * API still checks every request.
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'role' => $request->user()->workspaceRole()->value,
            'is_workspace_owner' => $request->user()->isWorkspaceOwner(),
            // Which employee record the caller is, so the UI can act for them
            // without a second lookup. Null for the workspace owner.
            'employee_id' => $request->user()->employeeId(),
            'permissions' => $request->user()->permissions(),
            'modules' => array_map(fn (Module $module) => $module->value, Module::cases()),
            'actions' => array_map(fn (Action $action) => $action->value, Action::cases()),
        ]]);
    }
}
