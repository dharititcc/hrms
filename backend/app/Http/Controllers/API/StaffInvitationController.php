<?php

namespace App\Http\Controllers\API;

use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Resources\StaffResource;
use App\Models\Staff;
use App\Services\StaffInvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffInvitationController extends Controller
{
    public function __construct(private readonly StaffInvitationService $service) {}

    public function store(Request $request, Staff $staff): JsonResponse
    {
        // Inviting grants workspace access, so it needs the assign ability
        // on top of the usual update check.
        $this->authorize('update', $staff);
        abort_unless($request->user()->hasAbility(Ability::Assign), 403);

        $this->service->invite($staff);

        return response()->json([
            'message' => 'Invitation sent.',
            'data' => new StaffResource($staff->refresh()),
        ], 201);
    }

    public function destroy(Request $request, Staff $staff): JsonResponse
    {
        $this->authorize('update', $staff);
        abort_unless($request->user()->hasAbility(Ability::Assign), 403);

        $this->service->revoke($staff);

        return response()->json(['message' => 'Account access revoked.']);
    }
}
