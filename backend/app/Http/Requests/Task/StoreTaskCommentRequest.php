<?php

namespace App\Http\Requests\Task;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $task = $this->route('task');

        return [
            'body' => ['required', 'string', 'max:20000'],
            // Replies are single level: the parent must be a top-level comment
            // on this same task.
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('task_comments', 'id')
                    ->where('task_id', $task?->id)
                    ->whereNull('parent_id')
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
