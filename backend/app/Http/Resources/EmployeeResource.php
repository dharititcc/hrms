<?php

namespace App\Http\Resources;

use App\Services\EmployeePermissionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'status' => $this->status->value,

            // The office they normally work from. Null for remote and field
            // workers, and wherever no office has been defined yet.
            /*
            | What this person may actually do, role adjusted by any overrides
            | recorded against them, and how many of those there are so the
            | list can show that somebody differs from their role.
            */
            'permissions' => app(EmployeePermissionService::class)->effectiveFor($this->resource),
            'permission_overrides' => $this->permissionOverrides()->count(),

            'attendance_location_id' => $this->attendance_location_id,
            'office_name' => $this->whenLoaded('office', fn () => $this->office?->name),
            // Whether this employee has a login account, and can therefore
            // be assigned tasks or invited to meetings.
            'has_account' => $this->user_id !== null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
