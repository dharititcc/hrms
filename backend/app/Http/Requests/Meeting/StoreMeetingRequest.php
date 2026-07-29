<?php

namespace App\Http\Requests\Meeting;

use App\Enums\MeetingType;
use App\Enums\RepeatFrequency;
use App\Support\WorkspaceUsers;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $workspaceUsers = WorkspaceUsers::idsFor($this->user()->workspaceOwnerId());

        return [
            'title' => ['required', 'string', 'max:255'],
            'agenda' => ['nullable', 'string', 'max:20000'],
            'description' => ['nullable', 'string', 'max:20000'],
            'type' => ['required', Rule::enum(MeetingType::class)],

            'host_id' => ['nullable', 'integer', Rule::in($workspaceUsers)],
            'organizer_id' => ['nullable', 'integer', Rule::in($workspaceUsers)],

            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            // Rejects anything PHP cannot resolve, so stored times stay meaningful.
            'timezone' => ['nullable', 'string', 'max:64', 'timezone'],

            'meeting_link' => ['nullable', 'url', 'max:2048'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:20000'],

            'reminder_minutes' => ['nullable', 'integer', 'min:0', 'max:20160'],

            'repeat_frequency' => ['nullable', Rule::enum(RepeatFrequency::class)],
            'repeat_interval' => ['nullable', 'integer', 'min:1', 'max:365'],
            'repeat_until' => ['nullable', 'date', 'after_or_equal:starts_at'],

            'participant_ids' => ['nullable', 'array'],
            'participant_ids.*' => ['integer', Rule::in($workspaceUsers)],

            'guests' => ['nullable', 'array'],
            'guests.*.email' => ['required', 'email', 'max:255'],
            'guests.*.name' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            // An offline meeting needs somewhere to be; a virtual one needs a
            // way in, though the link may arrive later from a provider.
            if ($this->input('type') === MeetingType::Offline->value && ! $this->filled('location')) {
                $validator->errors()->add('location', 'An offline meeting needs a location.');
            }
        });
    }
}
