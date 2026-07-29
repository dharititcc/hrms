<?php

namespace App\Http\Requests\Employee;

use App\Enums\EmployeeRole;
use App\Enums\EmployeeStatus;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends StoreEmployeeRequest
{
    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $routeEmployee = $this->route('employee');
        $employeeId = $routeEmployee instanceof Employee ? $routeEmployee->id : $routeEmployee;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('staff', 'email')->where('owner_id', $this->user()->workspaceOwnerId())->ignore($employeeId)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::enum(EmployeeRole::class)],
            'status' => ['required', Rule::enum(EmployeeStatus::class)],
        ];
    }
}
