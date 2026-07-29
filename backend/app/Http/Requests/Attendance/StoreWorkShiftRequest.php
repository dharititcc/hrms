<?php

namespace App\Http\Requests\Attendance;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreWorkShiftRequest extends FormRequest
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
            'starts_at' => ['required', 'date_format:H:i,H:i:s'],
            'ends_at' => ['required', 'date_format:H:i,H:i:s'],
            // Grace forgives lateness; it does not move the start of the day.
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $start = (string) $this->input('starts_at');
            $end = (string) $this->input('ends_at');

            // Overnight shifts are not modelled anywhere else in attendance,
            // so accepting one here would produce days that never add up.
            if ($start !== '' && $end !== '' && $end <= $start) {
                $validator->errors()->add('ends_at', 'The shift must end after it starts. Overnight shifts are not supported yet.');
            }

            $length = $this->minutesBetween($start, $end);

            if ($length !== null && (int) $this->input('break_minutes') >= $length) {
                $validator->errors()->add('break_minutes', 'The break cannot be as long as the shift itself.');
            }
        });
    }

    private function minutesBetween(string $start, string $end): ?int
    {
        if ($start === '' || $end === '') {
            return null;
        }

        [$startHour, $startMinute] = array_pad(explode(':', $start), 2, '0');
        [$endHour, $endMinute] = array_pad(explode(':', $end), 2, '0');

        return ((int) $endHour * 60 + (int) $endMinute) - ((int) $startHour * 60 + (int) $startMinute);
    }
}
