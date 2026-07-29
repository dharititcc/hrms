<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Support\WorkspaceRecords;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Accepts status and priority as either a single value or a list, so
     * ?status=pending and ?status[]=pending&status[]=review both work.
     */
    protected function prepareForValidation(): void
    {
        foreach (['status', 'priority'] as $key) {
            if ($this->filled($key) && ! is_array($this->input($key))) {
                $this->merge([$key => explode(',', (string) $this->input($key))]);
            }
        }

        // "mine=true" is shorthand for filtering to the caller.
        if ($this->boolean('mine')) {
            $this->merge(['assignee_id' => $this->user()->id]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(TaskStatus::class)],
            'priority' => ['nullable', 'array'],
            'priority.*' => [Rule::enum(TaskPriority::class)],
            'assignee_id' => ['nullable', 'integer'],
            'unassigned' => ['nullable', 'boolean'],
            'related_type' => ['nullable', 'string', Rule::in(WorkspaceRecords::aliases())],
            'related_id' => ['nullable', 'integer', 'required_with:related_type'],
            'due' => ['nullable', Rule::in(['overdue', 'today', 'week', 'none'])],
            'archived' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['subject', 'due_date', 'start_date', 'created_at', 'updated_at', 'priority', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
