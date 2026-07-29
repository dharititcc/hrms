<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreWorkShiftRequest;
use App\Http\Resources\WorkShiftResource;
use App\Models\WorkShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Office hours: what "late" and "a full day" are measured against.
 *
 * Attendance already read these, falling back to config/attendance.php when a
 * workspace had defined none. There was no way to define one, so every
 * workspace was silently judged against the same 09:00 to 18:00.
 */
class WorkShiftController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $shifts = WorkShift::query()
            ->where('owner_id', $request->user()->workspaceOwnerId())
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return WorkShiftResource::collection($shifts)
            ->additional(['meta' => [
                // What a workspace with no shift of its own is judged against,
                // so the screen can say so rather than looking empty.
                'fallback' => config('attendance.default_shift'),
                'half_day_threshold_minutes' => (int) config('attendance.half_day_threshold_minutes'),
                'overtime_after_minutes' => (int) config('attendance.overtime_after_minutes'),
                'break_after_minutes' => (int) config('attendance.break_after_minutes'),
            ]])
            ->response();
    }

    public function store(StoreWorkShiftRequest $request): JsonResponse
    {
        $shift = DB::transaction(function () use ($request): WorkShift {
            $shift = WorkShift::create([
                ...$request->validated(),
                'owner_id' => $request->user()->workspaceOwnerId(),
                'is_active' => $request->boolean('is_active', true),
            ]);

            $this->keepOneDefault($shift, $request->boolean('is_default'));

            return $shift;
        });

        return (new WorkShiftResource($shift->refresh()))->response()->setStatusCode(201);
    }

    public function update(StoreWorkShiftRequest $request, WorkShift $shift): WorkShiftResource
    {
        $this->authorizeShift($request, $shift);

        DB::transaction(function () use ($request, $shift): void {
            $shift->update([
                ...$request->validated(),
                'is_active' => $request->boolean('is_active', $shift->is_active),
            ]);

            $this->keepOneDefault($shift, $request->boolean('is_default'));
        });

        return new WorkShiftResource($shift->refresh());
    }

    public function destroy(Request $request, WorkShift $shift): JsonResponse
    {
        $this->authorizeShift($request, $shift);

        /*
        | Attendance rows point at the shift they were judged against, so
        | removing one would leave those days unexplainable. The foreign key
        | nulls rather than cascading, but the figures on the day stay as they
        | were recorded.
        */
        $shift->delete();

        return response()->json(['message' => 'Shift deleted. Attendance already recorded keeps its hours.']);
    }

    /**
     * Exactly one shift is the default, or none is.
     *
     * Two defaults would make which one a check-in is judged against depend on
     * row order, so setting one clears the rest.
     */
    private function keepOneDefault(WorkShift $shift, bool $wantsDefault): void
    {
        if (! $wantsDefault) {
            $shift->update(['is_default' => false]);

            return;
        }

        WorkShift::query()
            ->where('owner_id', $shift->owner_id)
            ->whereKeyNot($shift->id)
            ->update(['is_default' => false]);

        $shift->update(['is_default' => true]);
    }

    private function authorizeShift(Request $request, WorkShift $shift): void
    {
        abort_unless($shift->owner_id === $request->user()->workspaceOwnerId(), 403);
    }
}
