<?php

namespace App\Http\Controllers\API;

use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Support\RecordScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $query = Attendance::query()
            ->with('staff')
            ->where('owner_id', $request->user()->workspaceOwnerId());

        // Without attendance.view-all, only the caller's own days.
        RecordScope::apply($query, $request->user(), Module::Attendance);

        if (isset($validated['month'])) {
            // whereYear/whereMonth rather than strftime, which is SQLite-only
            // and silently failed on MySQL.
            [$year, $month] = explode('-', $validated['month']);
            $query->whereYear('work_date', (int) $year)->whereMonth('work_date', (int) $month);
        }

        return AttendanceResource::collection($query->latest('work_date')->paginate(20))->response();
    }

    public function clockIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
        ]);

        $staff = $request->user()->workspaceStaff()->findOrFail($validated['staff_id']);

        // Clocking somebody else in is a supervisory act.
        abort_unless(
            RecordScope::allows($request->user(), Module::Attendance, $staff->id),
            403,
            'You can only clock in for yourself.',
        );

        $attendance = Attendance::firstOrCreate(
            [
                'owner_id' => $request->user()->workspaceOwnerId(),
                'staff_id' => $staff->id,
                'work_date' => now()->toDateString(),
            ],
            ['status' => 'present'],
        );

        // Keeps the first clock-in of the day rather than overwriting it.
        $attendance->update(['check_in' => $attendance->check_in ?? now()->format('H:i:s')]);

        return response()->json(new AttendanceResource($attendance->load('staff')));
    }

    public function clockOut(Request $request, Attendance $attendance): JsonResponse
    {
        abort_unless($attendance->owner_id === $request->user()->workspaceOwnerId(), 403);
        abort_unless(
            RecordScope::allows($request->user(), Module::Attendance, $attendance->staff_id),
            403,
            'You can only clock out for yourself.',
        );

        $attendance->update(['check_out' => now()->format('H:i:s')]);

        return response()->json(new AttendanceResource($attendance->load('staff')));
    }
}
