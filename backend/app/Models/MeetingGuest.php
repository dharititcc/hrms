<?php

namespace App\Models;

use App\Enums\RsvpStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An external invitee. The invite token backs a public RSVP link, so it is
 * hidden from API output and generated automatically.
 */
#[Fillable(['meeting_id', 'name', 'email', 'rsvp', 'responded_at', 'attended', 'invite_token'])]
#[Hidden(['invite_token'])]
class MeetingGuest extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $guest): void {
            $guest->invite_token ??= Str::random(64);
            $guest->rsvp ??= RsvpStatus::Pending;
        });
    }

    protected function casts(): array
    {
        return [
            'rsvp' => RsvpStatus::class,
            'responded_at' => 'datetime',
            'attended' => 'boolean',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
