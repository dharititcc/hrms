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
            'staff_id' => $this->staff_id,
            'staff_name' => $this->whenLoaded('staff', fn () => $this->staff?->name),
            'work_date' => $this->work_date?->toDateString(),

            'check_in' => $this->check_in,
            'check_out' => $this->check_out,
            'status' => $this->status->value,
            'work_mode' => $this->work_mode->value,

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
