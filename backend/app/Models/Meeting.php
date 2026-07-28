<?php

namespace App\Models;

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\RepeatFrequency;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'owner_id', 'title', 'agenda', 'description', 'type', 'status',
    'host_id', 'organizer_id', 'starts_at', 'ends_at', 'timezone',
    'meeting_link', 'location', 'notes', 'recording_url', 'transcript',
    'reminder_minutes', 'reminder_sent_at',
    'repeat_frequency', 'repeat_interval', 'repeat_until', 'recurrence_parent_id', 'last_recurred_at',
    'rescheduled_to_id', 'external_calendar_id', 'external_event_id',
    'created_by', 'updated_by',
])]
#[Hidden(['owner_id'])]
class Meeting extends Model
{
    use HasAttachments, HasTags, LogsActivity, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => MeetingStatus::class,
            'type' => MeetingType::class,
            'repeat_frequency' => RepeatFrequency::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'repeat_until' => 'date',
            'last_recurred_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'reminder_minutes' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Attendees who hold workspace accounts. */
    public function participants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    /** Convenience access to the participant users themselves. */
    public function participantUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_participants')
            ->withPivot(['rsvp', 'responded_at', 'attended'])
            ->withTimestamps();
    }

    /** External invitees with no account. */
    public function guests(): HasMany
    {
        return $this->hasMany(MeetingGuest::class);
    }

    public function recurrenceParent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'recurrence_parent_id');
    }

    /** The meeting that replaced this one when it was moved. */
    public function rescheduledTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_to_id');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now())
            ->whereNotIn('status', [MeetingStatus::Cancelled->value, MeetingStatus::Rescheduled->value]);
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where('ends_at', '<', now());
    }

    /** Meetings overlapping a window, for calendar views. */
    public function scopeBetween(Builder $query, mixed $from, mixed $to): Builder
    {
        return $query->where('starts_at', '<', $to)->where('ends_at', '>', $from);
    }

    public function durationMinutes(): int
    {
        return (int) abs($this->starts_at->diffInMinutes($this->ends_at));
    }

    public function repeats(): bool
    {
        if ($this->repeat_frequency === null) {
            return false;
        }

        return $this->repeat_until === null || $this->repeat_until->isFuture();
    }
}
