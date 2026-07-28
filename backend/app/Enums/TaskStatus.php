<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case OnHold = 'on_hold';

    /** Statuses that close a task; used for completion timestamps and reporting. */
    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], strict: true);
    }
}
