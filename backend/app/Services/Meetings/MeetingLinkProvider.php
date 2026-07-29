<?php

namespace App\Services\Meetings;

use App\Enums\MeetingType;
use App\Models\Meeting;

/**
 * Supplies the joining link for a virtual meeting.
 *
 * The seam exists so Google Meet (and Calendar sync) can be added without
 * touching the meeting service: bind a different implementation in the
 * container and the calling code is unchanged.
 */
interface MeetingLinkProvider
{
    public function supports(MeetingType $type): bool;

    /**
     * Returns a joining link, or null when the caller must supply one.
     *
     * Implementations must not throw when they cannot generate a link — a
     * meeting is still valid with a manually pasted URL.
     */
    public function generateLink(Meeting $meeting): ?string;
}
