<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\WorkMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'owner_id', 'staff_id', 'work_shift_id', 'work_date', 'check_in', 'check_out',
    'check_in_at', 'check_out_at', 'timezone',
    'status', 'work_mode', 'notes',
    'worked_minutes', 'break_minutes', 'late_minutes', 'overtime_minutes',
    'break_after_minutes', 'overtime_after_minutes',
    'check_in_latitude', 'check_in_longitude', 'check_in_address', 'check_in_location_id',
    'check_out_latitude', 'check_out_longitude', 'check_out_address',
    'device_type', 'device_os', 'device_browser', 'ip_address',
    'requires_approval', 'is_manual', 'approved_by', 'approved_at',
])]
#[Hidden(['owner_id'])]
class Attendance extends Model
{
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            // Absolute instants, so any viewer can be shown them in their own
            // zone. check_in and check_out remain the local wall clock.
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'status' => AttendanceStatus::class,
            'work_mode' => WorkMode::class,
            'worked_minutes' => 'integer',
            'break_minutes' => 'integer',
            'late_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'check_in_latitude' => 'float',
            'check_in_longitude' => 'float',
            'check_out_latitude' => 'float',
            'check_out_longitude' => 'float',
            'requires_approval' => 'boolean',
            'is_manual' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'staff_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class, 'work_shift_id');
    }

    public function checkInLocation(): BelongsTo
    {
        return $this->belongsTo(AttendanceLocation::class, 'check_in_location_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeAwaitingApproval(Builder $query): Builder
    {
        return $query->where('requires_approval', true);
    }

    /** "7h 30m", for display. */
    /** Checked in and not yet out, so nothing has been derived for it. */
    public function isOpen(): bool
    {
        return $this->check_in !== null && $this->check_out === null;
    }

    /**
     * Minutes since check-in on a day still open, for showing progress
     * instead of a zero that reads as "worked nothing".
     */
    public function elapsedMinutes(): int
    {
        if (! $this->isOpen() || $this->check_in_at === null) {
            return 0;
        }

        return max(0, (int) $this->check_in_at->diffInMinutes(now()));
    }

    public function workedHours(): string
    {
        $hours = intdiv($this->worked_minutes, 60);
        $minutes = $this->worked_minutes % 60;

        return $hours === 0 ? "{$minutes}m" : ($minutes === 0 ? "{$hours}h" : "{$hours}h {$minutes}m");
    }
}
