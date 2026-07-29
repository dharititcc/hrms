<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\WorkMode;
use App\Models\Attendance;
use App\Models\AttendanceLocation;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkShift;
use App\Rules\ValidTimezone;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Check-in and check-out, with shift, geofence and device capture.
 *
 * Working hours, lateness and overtime are derived once at check-out and
 * stored, so a later change to shift settings cannot rewrite a day somebody
 * has already worked.
 */
class AttendanceService
{
    public function checkIn(Employee $employee, User $actor, array $attributes, ?Request $request = null): Attendance
    {
        /*
        | Everything about the day is read in the employee's own zone: which
        | date it is, what the clock says, and therefore whether they are late
        | against a shift that starts at 09:00 where they are. Reading any of
        | that from the server's clock puts somebody in another country on the
        | wrong day at the wrong time.
        */
        $zone = $this->zoneFrom($attributes);
        $now = now()->setTimezone($zone);
        $today = $now->toDateString();
        $existing = $this->forDate($employee, $today);

        if ($existing?->check_in !== null) {
            throw ValidationException::withMessages([
                'check_in' => 'You have already checked in today at '.$existing->check_in.'.',
            ]);
        }

        $mode = WorkMode::from($attributes['work_mode'] ?? WorkMode::Office->value);
        $shift = $this->shiftFor($employee->owner_id, $attributes['work_shift_id'] ?? null);

        // Fetched once so "outside every office" can be told apart from "no
        // offices defined": the first is suspicious, the second is not.
        $offices = AttendanceLocation::query()->where('owner_id', $employee->owner_id)->active()->get();
        $location = $this->resolveLocation($offices, $mode, $attributes, $employee);

        return DB::transaction(function () use ($employee, $attributes, $request, $mode, $shift, $location, $offices, $today, $now, $zone): Attendance {
            $attendance = Attendance::firstOrNew([
                'owner_id' => $employee->owner_id,
                'staff_id' => $employee->id,
                'work_date' => $today,
            ]);

            $lateMinutes = $this->lateMinutes($now, $shift);

            $attendance->fill([
                'work_shift_id' => $shift['id'],
                // The wall clock where they are, plus the instant it maps to.
                'check_in' => $now->format('H:i:s'),
                'check_in_at' => $now,
                'timezone' => $zone,
                'work_mode' => $mode,
                'status' => $lateMinutes > 0 ? AttendanceStatus::Late : AttendanceStatus::Present,
                'late_minutes' => $lateMinutes,
                'break_minutes' => $shift['break_minutes'],
                'check_in_latitude' => $attributes['latitude'] ?? null,
                'check_in_longitude' => $attributes['longitude'] ?? null,
                'check_in_address' => $attributes['address'] ?? null,
                'check_in_location_id' => $location?->id,
                // Anything recorded away from a known office is flagged rather
                // than rejected, unless enforcement is switched on.
                'requires_approval' => $this->needsApproval($mode, $location, $attributes, $offices->isNotEmpty()),
                'is_manual' => false,
                ...$this->deviceFrom($request),
            ]);

            $attendance->save();

            return $attendance->refresh();
        });
    }

    public function checkOut(Attendance $attendance, array $attributes = []): Attendance
    {
        if ($attendance->check_in === null) {
            throw ValidationException::withMessages(['check_out' => 'There is no check-in to close.']);
        }

        if ($attendance->check_out !== null) {
            throw ValidationException::withMessages([
                'check_out' => 'You already checked out today at '.$attendance->check_out.'.',
            ]);
        }

        // Closing the day in the zone it was opened in, so a night shift does
        // not read as a negative day just because the viewer is elsewhere.
        $zone = $attendance->timezone ?? $this->zoneFrom($attributes);
        $now = now()->setTimezone($zone);
        $worked = $this->workedMinutes($attendance, $now);

        return DB::transaction(function () use ($attendance, $attributes, $now, $worked): Attendance {
            $attendance->update([
                'check_out' => $now->format('H:i:s'),
                'check_out_at' => $now,
                'worked_minutes' => $worked,
                'overtime_minutes' => max(0, $worked - config('attendance.overtime_after_minutes')),
                // A short day is a half day, unless they were already late,
                // which is the more specific fact about the day.
                'status' => $this->statusFor($attendance, $worked),
                'check_out_latitude' => $attributes['latitude'] ?? null,
                'check_out_longitude' => $attributes['longitude'] ?? null,
                'check_out_address' => $attributes['address'] ?? null,
            ]);

            return $attendance->refresh();
        });
    }

    public function approve(Attendance $attendance, User $approver): Attendance
    {
        $attendance->update([
            'requires_approval' => false,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $attendance->refresh();
    }

    public function forDate(Employee $employee, string $date): ?Attendance
    {
        return Attendance::query()
            ->where('staff_id', $employee->id)
            ->whereDate('work_date', $date)
            ->first();
    }

    /**
     * The zone the browser reported, falling back to the workspace's own.
     *
     * Validated at the controller, but checked again here because the fallback
     * has to be a real zone: an unknown identifier would throw inside Carbon
     * rather than merely being wrong.
     */
    private function zoneFrom(array $attributes): string
    {
        $zone = $attributes['timezone'] ?? null;

        // The same test the request made, so a zone that passed validation is
        // never quietly swapped for the workspace default here.
        return ValidTimezone::usable($zone) ? $zone : config('app.timezone');
    }

    /** @return array{id: int|null, starts_at: string, ends_at: string, grace_minutes: int, break_minutes: int} */
    private function shiftFor(int $ownerId, ?int $shiftId): array
    {
        $shift = $shiftId !== null
            ? WorkShift::query()->where('owner_id', $ownerId)->active()->find($shiftId)
            : WorkShift::defaultFor($ownerId);

        if ($shift !== null) {
            return [
                'id' => $shift->id,
                'starts_at' => $shift->starts_at,
                'ends_at' => $shift->ends_at,
                'grace_minutes' => $shift->grace_minutes,
                'break_minutes' => $shift->break_minutes,
            ];
        }

        // No shift configured: fall back to the workspace default in config so
        // attendance still works out of the box.
        return ['id' => null, ...config('attendance.default_shift')];
    }

    /**
     * The office the check-in falls inside, if any.
     *
     * Throws only when enforcement is on and the person claims to be in the
     * office but is not near one.
     */
    private function resolveLocation(Collection $locations, WorkMode $mode, array $attributes, ?Employee $employee = null): ?AttendanceLocation
    {
        $latitude = $attributes['latitude'] ?? null;
        $longitude = $attributes['longitude'] ?? null;

        // A workspace with no offices defined cannot be fenced; enabling
        // enforcement before defining one must not lock everybody out.
        if (! $mode->requiresOfficePresence() || $latitude === null || $longitude === null || $locations->isEmpty()) {
            return null;
        }

        /*
        | Their own office is tried first. Two sites within each other's radius
        | would otherwise be resolved by whichever the query happened to return
        | first, and record somebody at a building they do not work in.
        |
        | Only a preference: being at a different office is still a valid
        | check-in, which is what makes visiting another site work.
        */
        $covers = fn (AttendanceLocation $location) => $location->covers((float) $latitude, (float) $longitude);
        $assigned = $employee?->attendance_location_id;

        $match = $locations->first(fn (AttendanceLocation $location) => $location->id === $assigned && $covers($location))
            ?? $locations->first($covers);

        if ($match === null && config('attendance.geofence.enforce')) {
            $nearest = $locations->sortBy(fn ($l) => $l->distanceTo((float) $latitude, (float) $longitude))->first();

            throw ValidationException::withMessages([
                'location' => sprintf(
                    'You are about %dm from %s, which is outside the allowed area.',
                    (int) round($nearest->distanceTo((float) $latitude, (float) $longitude)),
                    $nearest->name,
                ),
            ]);
        }

        return $match;
    }

    /**
     * Office attendance recorded away from a known location is held for
     * approval; remote and field work is not, since being elsewhere is the
     * point.
     */
    private function needsApproval(WorkMode $mode, ?AttendanceLocation $location, array $attributes, bool $hasOffices): bool
    {
        // Nothing to be outside of if no offices are defined.
        if (! $mode->requiresOfficePresence() || ! $hasOffices) {
            return false;
        }

        $hasCoordinates = ($attributes['latitude'] ?? null) !== null && ($attributes['longitude'] ?? null) !== null;

        return $hasCoordinates && $location === null;
    }

    private function lateMinutes(Carbon $checkInAt, array $shift): int
    {
        $shiftStart = Carbon::parse($checkInAt->toDateString().' '.$shift['starts_at']);
        $allowedFrom = $shiftStart->copy()->addMinutes($shift['grace_minutes']);

        return $checkInAt->greaterThan($allowedFrom)
            // Measured from the shift start, not the end of grace: grace
            // forgives lateness, it does not redefine the start of the day.
            ? (int) $shiftStart->diffInMinutes($checkInAt)
            : 0;
    }

    private function workedMinutes(Attendance $attendance, Carbon $checkOutAt): int
    {
        $checkIn = Carbon::parse($attendance->work_date->toDateString().' '.$attendance->check_in);
        $elapsed = (int) $checkIn->diffInMinutes($checkOutAt);

        return max(0, $elapsed - (int) $attendance->break_minutes);
    }

    private function statusFor(Attendance $attendance, int $workedMinutes): AttendanceStatus
    {
        if ($workedMinutes < config('attendance.half_day_threshold_minutes')) {
            return AttendanceStatus::HalfDay;
        }

        return $attendance->late_minutes > 0 ? AttendanceStatus::Late : AttendanceStatus::Present;
    }

    /** @return array<string, string|null> */
    private function deviceFrom(?Request $request): array
    {
        if ($request === null) {
            return [];
        }

        $agent = (string) $request->userAgent();

        return [
            'ip_address' => $request->ip(),
            'device_type' => $this->deviceType($agent),
            'device_os' => $this->match($agent, ['Windows' => 'Windows', 'Mac OS X' => 'macOS', 'Android' => 'Android', 'iPhone|iPad' => 'iOS', 'Linux' => 'Linux']),
            'device_browser' => $this->match($agent, ['Edg' => 'Edge', 'OPR|Opera' => 'Opera', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari']),
        ];
    }

    private function deviceType(string $agent): string
    {
        return match (true) {
            (bool) preg_match('/iPad|Tablet/i', $agent) => 'tablet',
            (bool) preg_match('/Mobile|Android|iPhone/i', $agent) => 'mobile',
            default => 'desktop',
        };
    }

    /** @param array<string, string> $patterns */
    private function match(string $agent, array $patterns): ?string
    {
        foreach ($patterns as $pattern => $label) {
            if (preg_match("/{$pattern}/i", $agent)) {
                return $label;
            }
        }

        return null;
    }
}
