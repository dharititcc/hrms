<?php

namespace App\Http\Controllers\API;

use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceUserController extends Controller
{
    /**
     * Users who can be assigned work: the workspace owner plus any staff member
     * who has accepted an invitation. Staff without an account are excluded —
     * they could not see the task or receive its notifications.
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAbility(Ability::View), 403);

        $ownerId = $request->user()->workspaceOwnerId();

        $staffUserIds = Staff::query()
            ->where('owner_id', $ownerId)
            ->whereNotNull('user_id')
            ->pluck('user_id');

        $users = User::query()
            ->whereIn('id', $staffUserIds->push($ownerId)->unique())
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $users]);
    }
}
