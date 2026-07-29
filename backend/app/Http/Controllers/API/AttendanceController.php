<?php

namespace App\Http\Controllers\API;

use App\Enums\Module;
use App\Enums\WorkMode;
use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Services\AttendanceService;
use App\Support\RecordScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function __construct(private readonly AttendanceService $service) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'staff_id' => ['nullable', 'integer'],
        ]);

        $query = Attendance::query()
            ->with(['employee', 'checkInLocation'])
            ->where('owner_id', $request->user()->workspaceOwnerId());

        // Without attendance.view-all, only the caller's own days.
        RecordScope::apply($query, $request->user(), Module::Attendance);

        if (isset($validated['month'])) {
            // whereYear/whereMonth rather than strftime, which is SQLite-only.
            [$year, $month] = explode('-', $validated['month']);
            $query->whereYear('work_date', (int) $year)->whereMonth('work_date', (int) $month);
        }

        $query->when($validated['staff_id'] ?? null, fn ($q, $id) => $q->where('staff_id', $id));

        return AttendanceResource::collection($query->latest('work_date')->paginate(31))->response();
    }

    /** Today's record for the caller, for the dashboard's check-in card. */
    public function today(Request $request): JsonResponse
    {
        $employee = $request->user()->employeeProfile;

        abort_if($employee === null, 404, 'This account is not linked to an employee record.');

        $attendance = $this->service->forDate($employee, now()->toDateString());

        return response()->json([
            'data' => $attendance === null ? null : new AttendanceResource($attendance->load('checkInLocation')),
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'work_mode' => ['nullable', Rule::enum(WorkMode::class)],
            'work_shift_id' => ['nullable', 'integer'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $employee = $request->user()->workspaceEmployees()->findOrFail($validated['staff_id']);

        abort_unless(
            RecordScope::allows($request->user(), Module::Attendance, $employee->id),
            403,
            'You can only check in for yourself.',
        );

        $attendance = $this->service->checkIn($employee, $request->user(), $validated, $request);

        return response()->json(['data' => new AttendanceResource($attendance->load(['employee', 'checkInLocation']))]);
    }

    public function checkOut(Request $request, Attendance $attendance): JsonResponse
    {
        abort_unless($attendance->owner_id === $request->user()->workspaceOwnerId(), 403);
        abort_unless(
            RecordScope::allows($request->user(), Module::Attendance, $attendance->staff_id),
            403,
            'You can only check out for yourself.',
        );

        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json([
            'data' => new AttendanceResource($this->service->checkOut($attendance, $validated)->load(['employee', 'checkInLocation'])),
        ]);
    }

    /** Clears the flag on attendance recorded away from a known office. */
    public function approve(Request $request, Attendance $attendance): JsonResponse
    {
        abort_unless($attendance->owner_id === $request->user()->workspaceOwnerId(), 403);

        return response()->json([
            'data' => new AttendanceResource($this->service->approve($attendance, $request->user())->load(['employee', 'checkInLocation'])),
        ]);
    }
}
