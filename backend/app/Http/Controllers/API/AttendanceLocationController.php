<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAttendanceLocationRequest;
use App\Http\Resources\AttendanceLocationResource;
use App\Models\AttendanceLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Offices a geofenced check-in is measured against.
 */
class AttendanceLocationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $locations = AttendanceLocation::query()
            ->where('owner_id', $request->user()->workspaceOwnerId())
            ->orderBy('name')
            ->get();

        return AttendanceLocationResource::collection($locations)
            ->additional(['meta' => [
                // Tells the UI whether these fences block a check-in or merely
                // flag it, which changes what an administrator should expect.
                'enforcement_enabled' => (bool) config('attendance.geofence.enforce'),
                'default_radius_metres' => (int) config('attendance.geofence.default_radius_metres'),
            ]])
            ->response();
    }

    public function store(StoreAttendanceLocationRequest $request): JsonResponse
    {
        $location = AttendanceLocation::create([
            ...$request->validated(),
            'owner_id' => $request->user()->workspaceOwnerId(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return (new AttendanceLocationResource($location))->response()->setStatusCode(201);
    }

    public function update(StoreAttendanceLocationRequest $request, AttendanceLocation $location): AttendanceLocationResource
    {
        $this->authorizeLocation($request, $location);

        $location->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', $location->is_active),
        ]);

        return new AttendanceLocationResource($location->refresh());
    }

    public function destroy(Request $request, AttendanceLocation $location): JsonResponse
    {
        $this->authorizeLocation($request, $location);

        // Attendance rows reference the office they were recorded at; the
        // foreign key nulls on delete, so history survives without it.
        $location->delete();

        return response()->json(['message' => 'Office location deleted.']);
    }

    private function authorizeLocation(Request $request, AttendanceLocation $location): void
    {
        abort_unless($location->owner_id === $request->user()->workspaceOwnerId(), 403);
    }
}
