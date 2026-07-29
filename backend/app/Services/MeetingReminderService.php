<?php

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Notifications\MeetingReminderNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class MeetingReminderService
{
    /**
     * Notifies attendees of meetings whose reminder window has opened.
     *
     * reminder_sent_at makes this idempotent, so the command can run every
     * minute without sending twice. Meetings that have already started are
     * skipped rather than reminded late — a reminder after the fact is noise.
     *
     * @return int number of meetings that triggered reminders
     */
    public function sendDue(?CarbonInterface $asOf = null): int
    {
        $now = Carbon::parse($asOf ?? now());
        $sent = 0;

        Meeting::query()
            ->whereNotNull('reminder_minutes')
            ->whereNull('reminder_sent_at')
            ->where('starts_at', '>', $now)
            ->whereNotIn('status', [MeetingStatus::Cancelled->value, MeetingStatus::Rescheduled->value])
            ->with(['participants.user', 'guests'])
            ->chunkById(100, function ($meetings) use (&$sent, $now): void {
                foreach ($meetings as $meeting) {
                    // The window opens reminder_minutes before the start.
                    if ($now->lessThan($meeting->starts_at->copy()->subMinutes($meeting->reminder_minutes))) {
                        continue;
                    }

                    $meeting->forceFill(['reminder_sent_at' => $now])->saveQuietly();

                    $users = $meeting->participants->pluck('user')->filter();

                    if ($users->isNotEmpty()) {
                        Notification::send($users, new MeetingReminderNotification($meeting));
                    }

                    foreach ($meeting->guests as $guest) {
                        Notification::route('mail', $guest->email)->notify(new MeetingReminderNotification($meeting));
                    }

                    $sent++;
                }
            });

        return $sent;
    }
}
