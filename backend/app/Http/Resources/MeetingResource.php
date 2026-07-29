<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeetingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'agenda' => $this->agenda,
            'description' => $this->description,
            'type' => $this->type->value,
            'status' => $this->status->value,

            'host_id' => $this->host_id,
            'host_name' => $this->whenLoaded('host', fn () => $this->host?->name),
            'organizer_id' => $this->organizer_id,
            'organizer_name' => $this->whenLoaded('organizer', fn () => $this->organizer?->name),

            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'timezone' => $this->timezone,
            // Derived, so it can never disagree with the timestamps above.
            'duration_minutes' => $this->durationMinutes(),

            'meeting_link' => $this->meeting_link,
            'location' => $this->location,
            'notes' => $this->notes,
            'recording_url' => $this->recording_url,
            'transcript' => $this->transcript,

            'reminder_minutes' => $this->reminder_minutes,
            'repeat_frequency' => $this->repeat_frequency?->value,
            'repeat_interval' => $this->repeat_interval,
            'repeat_until' => $this->repeat_until?->toDateString(),
            'recurrence_parent_id' => $this->recurrence_parent_id,

            'participants' => $this->whenLoaded('participants', fn () => $this->participants->map(fn ($participant) => [
                'id' => $participant->id,
                'user_id' => $participant->user_id,
                'name' => $participant->user?->name,
                'rsvp' => $participant->rsvp->value,
                'responded_at' => $participant->responded_at?->toISOString(),
                'attended' => $participant->attended,
            ])->all()),

            'guests' => $this->whenLoaded('guests', fn () => $this->guests->map(fn ($guest) => [
                'id' => $guest->id,
                'name' => $guest->name,
                'email' => $guest->email,
                'rsvp' => $guest->rsvp->value,
                'responded_at' => $guest->responded_at?->toISOString(),
                'attended' => $guest->attended,
            ])->all()),

            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ])->all()),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
