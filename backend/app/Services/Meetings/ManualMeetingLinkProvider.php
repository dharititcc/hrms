<?php

namespace App\Services\Meetings;

use App\Enums\MeetingType;
use App\Models\Meeting;

/**
 * Default provider: the organiser pastes their own link.
 *
 * Deliberately generates nothing. Returning a fabricated Meet or Zoom URL would
 * produce links that look valid and fail on click, which is worse than asking
 * for one. Replace the binding in AppServiceProvider once Google credentials
 * are configured.
 */
class ManualMeetingLinkProvider implements MeetingLinkProvider
{
    public function supports(MeetingType $type): bool
    {
        return $type->isVirtual();
    }

    public function generateLink(Meeting $meeting): ?string
    {
        return null;
    }
}
