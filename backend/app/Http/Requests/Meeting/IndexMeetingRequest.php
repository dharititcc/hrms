<?php

namespace App\Http\Requests\Meeting;

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['status', 'type'] as $key) {
            if ($this->filled($key) && ! is_array($this->input($key))) {
                $this->merge([$key => explode(',', (string) $this->input($key))]);
            }
        }

        if ($this->boolean('mine')) {
            $this->merge(['participant_id' => $this->user()->id]);
        }
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::enum(MeetingStatus::class)],
            'type' => ['nullable', 'array'],
            'type.*' => [Rule::enum(MeetingType::class)],
            'participant_id' => ['nullable', 'integer'],
            'host_id' => ['nullable', 'integer'],
            // Calendar window. Both ends required together so a half-open range
            // cannot silently return everything.
            'from' => ['nullable', 'date', 'required_with:to'],
            'to' => ['nullable', 'date', 'after_or_equal:from', 'required_with:from'],
            'period' => ['nullable', Rule::in(['upcoming', 'past'])],
            'sort' => ['nullable', Rule::in(['starts_at', 'title', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
