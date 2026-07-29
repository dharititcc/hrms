<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkShiftResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'starts_at' => substr((string) $this->starts_at, 0, 5),
            'ends_at' => substr((string) $this->ends_at, 0, 5),
            'grace_minutes' => $this->grace_minutes,
            'break_minutes' => $this->break_minutes,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,

            // What a full day comes to under this shift, so the screen can say
            // it rather than making somebody work it out.
            'paid_minutes' => $this->paidMinutes(),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function paidMinutes(): int
    {
        [$startHour, $startMinute] = array_pad(explode(':', (string) $this->starts_at), 2, '0');
        [$endHour, $endMinute] = array_pad(explode(':', (string) $this->ends_at), 2, '0');

        $length = ((int) $endHour * 60 + (int) $endMinute) - ((int) $startHour * 60 + (int) $startMinute);

        return max(0, $length - (int) $this->break_minutes);
    }
}
