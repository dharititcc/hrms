<?php

namespace App\Enums;

/**
 * Where the work happened. Kept separate from AttendanceStatus: someone can be
 * late and working from home, and those are two different facts.
 */
enum WorkMode: string
{
    case Office = 'office';
    case Remote = 'remote';
    case Field = 'field';

    /** Only office attendance is subject to the geofence. */
    public function requiresOfficePresence(): bool
    {
        return $this === self::Office;
    }
}
