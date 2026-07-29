<?php

namespace App\Enums;

enum MeetingType: string
{
    case GoogleMeet = 'google_meet';
    case Zoom = 'zoom';
    case Teams = 'teams';
    case Offline = 'offline';

    /** Offline meetings have a location instead of a joining link. */
    public function isVirtual(): bool
    {
        return $this !== self::Offline;
    }
}
