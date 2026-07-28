<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'is_public' => $this->is_public,
            'is_billable' => $this->is_billable,
            'hourly_rate' => $this->hourly_rate,
            'estimated_hours' => $this->estimated_hours,
            'start_date' => $this->start_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'archived_at' => $this->archived_at?->toISOString(),
            'parent_task_id' => $this->parent_task_id,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'repeat_frequency' => $this->repeat_frequency?->value,
            'repeat_interval' => $this->repeat_interval,
            'repeat_until' => $this->repeat_until?->toDateString(),
            'position' => $this->position,
            'assignees' => $this->whenLoaded('assignees', fn () => $this->assignees->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
            ])->all()),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])->all()),
            'related_label' => $this->whenLoaded('related', fn () => $this->related?->name ?? $this->related?->subject ?? null),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
