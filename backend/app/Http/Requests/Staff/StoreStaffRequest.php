<?php

namespace App\Http\Requests\Staff;

use App\Enums\StaffRole;
use App\Enums\StaffStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('staff', 'email')->where('owner_id', $this->user()->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::enum(StaffRole::class)],
            'status' => ['required', Rule::enum(StaffStatus::class)],
        ];
    }
}
