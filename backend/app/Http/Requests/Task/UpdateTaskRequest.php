<?php

namespace App\Http\Requests\Task;

use Illuminate\Validation\Rule;

class UpdateTaskRequest extends StoreTaskRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            // A task may not be its own parent.
            'parent_task_id' => [
                'nullable',
                'integer',
                'different:task_id',
                Rule::exists('tasks', 'id')->where('owner_id', $this->user()->workspaceOwnerId()),
                Rule::notIn([$this->route('task')?->id]),
            ],
        ];
    }
}
