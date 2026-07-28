<?php

namespace App\Http\Controllers\API;

use App\Enums\Action;
use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Support\WorkspaceUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceUserController extends Controller
{
    /**
     * Users who can be assigned work or mentioned. Staff without an account are
     * excluded — they could not see the task or receive its notifications.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission(Module::Staff, Action::View), 403);

        $users = WorkspaceUsers::query($request->user()->workspaceOwnerId())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $users]);
    }
}
