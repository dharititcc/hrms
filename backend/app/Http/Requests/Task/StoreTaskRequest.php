<?php

namespace App\Http\Requests\Task;

use App\Enums\RepeatFrequency;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Staff;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'status' => ['required', Rule::enum(TaskStatus::class)],
            'priority' => ['required', Rule::enum(TaskPriority::class)],
            'is_public' => ['nullable', 'boolean'],
            'is_billable' => ['nullable', 'boolean'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'parent_task_id' => ['nullable', 'integer', Rule::exists('tasks', 'id')->where('owner_id', $this->user()->workspaceOwnerId())],
            'repeat_frequency' => ['nullable', Rule::enum(RepeatFrequency::class)],
            'repeat_interval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'repeat_until' => ['nullable', 'date', 'after_or_equal:due_date'],
            'assignee_ids' => ['nullable', 'array'],
            // An assignee must be a user inside this workspace: either the
            // owner, or a staff member who has been invited and linked.
            'assignee_ids.*' => ['integer', Rule::in($this->workspaceUserIds())],
        ];
    }

    /** @return list<int> */
    protected function workspaceUserIds(): array
    {
        $ownerId = $this->user()->workspaceOwnerId();

        return [
            $ownerId,
            ...Staff::query()->where('owner_id', $ownerId)->whereNotNull('user_id')->pluck('user_id')->all(),
        ];
    }
}
