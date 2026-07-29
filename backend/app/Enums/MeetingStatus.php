<?php

namespace App\Enums;

enum MeetingStatus: string
{
    case Scheduled = 'scheduled';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';

    /** Statuses where the meeting will not go ahead as originally planned. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::Rescheduled], strict: true);
    }
}
