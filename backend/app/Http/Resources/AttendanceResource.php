<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->staff_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee?->name),
            'work_date' => $this->work_date?->toDateString(),

            // The wall clock where the employee was, and the instant it maps
            // to. A viewer elsewhere is shown the instant in their own zone;
            // the wall clock is what a shift start is compared against.
            'check_in' => $this->check_in,
            'check_out' => $this->check_out,
            'check_in_at' => $this->check_in_at?->toISOString(),
            'check_out_at' => $this->check_out_at?->toISOString(),
            'timezone' => $this->timezone,
            'status' => $this->status->value,
            'work_mode' => $this->work_mode->value,

            // A day still running reports what has elapsed, so it does not
            // read as somebody who turned up and worked nothing.
            'is_open' => $this->isOpen(),
            'elapsed_minutes' => $this->elapsedMinutes(),

            'worked_minutes' => $this->worked_minutes,
            'worked_hours' => $this->workedHours(),
            'break_minutes' => $this->break_minutes,
            'late_minutes' => $this->late_minutes,
            'overtime_minutes' => $this->overtime_minutes,

            'check_in_location' => [
                'latitude' => $this->check_in_latitude,
                'longitude' => $this->check_in_longitude,
                'address' => $this->check_in_address,
                'office' => $this->whenLoaded('checkInLocation', fn () => $this->checkInLocation?->name),
            ],
            'check_out_location' => [
                'latitude' => $this->check_out_latitude,
                'longitude' => $this->check_out_longitude,
                'address' => $this->check_out_address,
            ],

            'device' => [
                'type' => $this->device_type,
                'os' => $this->device_os,
                'browser' => $this->device_browser,
                'ip_address' => $this->ip_address,
            ],

            'requires_approval' => $this->requires_approval,
            'is_manual' => $this->is_manual,
            'approved_at' => $this->approved_at?->toISOString(),
            'notes' => $this->notes,
        ];
    }
}
