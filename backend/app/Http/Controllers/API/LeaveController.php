<?php

namespace App\Http\Controllers\API;

use App\Enums\LeaveRequestStatus;
use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveRequestResource;
use App\Http\Resources\LeaveTypeResource;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Support\RecordScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveController extends Controller
{
    /** Leave types are workspace-wide settings, not personal records. */
    public function types(Request $request)
    {
        $ownerId = $request->user()->workspaceOwnerId();

        foreach ([['Annual leave', 20], ['Sick leave', 12], ['Personal leave', 5]] as [$name, $days]) {
            LeaveType::firstOrCreate(
                ['owner_id' => $ownerId, 'name' => $name],
                ['days_per_year' => $days, 'is_active' => true],
            );
        }

        $types = LeaveType::query()
            ->where('owner_id', $ownerId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return LeaveTypeResource::collection($types);
    }

    public function index(Request $request): JsonResponse
    {
        $query = LeaveRequest::query()
            ->with(['staff', 'leaveType'])
            ->where('owner_id', $request->user()->workspaceOwnerId());

        // Without leave.view-all, only the caller's own requests.
        RecordScope::apply($query, $request->user(), Module::Leave);

        return LeaveRequestResource::collection($query->latest()->paginate(20))->response();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'exists:staff,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $ownerId = $request->user()->workspaceOwnerId();
        $request->user()->workspaceStaff()->findOrFail($validated['staff_id']);
        LeaveType::query()->where('owner_id', $ownerId)->findOrFail($validated['leave_type_id']);

        // Requesting leave on a colleague's behalf needs oversight of the module.
        abort_unless(
            RecordScope::allows($request->user(), Module::Leave, (int) $validated['staff_id']),
            403,
            'You can only request leave for yourself.',
        );

        $leaveRequest = LeaveRequest::create([
            ...$validated,
            'owner_id' => $ownerId,
            'status' => LeaveRequestStatus::Pending->value,
        ]);

        return response()->json(new LeaveRequestResource($leaveRequest->load(['staff', 'leaveType'])), 201);
    }

    public function updateStatus(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        abort_unless($leaveRequest->owner_id === $request->user()->workspaceOwnerId(), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(LeaveRequestStatus::class)],
        ]);

        // reviewed_by is the actual reviewer, not the workspace owner.
        $leaveRequest->update([...$validated, 'reviewed_by' => $request->user()->id]);

        return response()->json(new LeaveRequestResource($leaveRequest->load(['staff', 'leaveType'])));
    }
}
