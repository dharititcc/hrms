<?php

namespace App\Http\Requests\Task;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTimeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'started_at' => ['required', 'date'],
            // Manual entries cannot be open-ended or run backwards, and time
            // cannot be logged against the future.
            'ended_at' => ['required', 'date', 'after:started_at', 'before_or_equal:now'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
