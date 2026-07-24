<?php

namespace App\Http\Requests\Staff;

use App\Enums\StaffRole;
use App\Enums\StaffStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Staff;

class UpdateStaffRequest extends StoreStaffRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $routeStaff = $this->route('staff');
        $staffId = $routeStaff instanceof Staff ? $routeStaff->id : $routeStaff;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('staff', 'email')->where('owner_id', $this->user()->id)->ignore($staffId)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::enum(StaffRole::class)],
            'status' => ['required', Rule::enum(StaffStatus::class)],
        ];
    }
}
