<?php

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MeetingGuest;
use App\Models\MeetingParticipant;
use App\Notifications\MeetingInvitationNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Generates upcoming occurrences of recurring meetings.
 *
 * Mirrors RecurringTaskService: only templates recur, and generated instances
 * carry recurrence_parent_id with no rule of their own so they cannot cascade.
 *
 * Unlike tasks, meetings are generated ahead of time rather than caught up:
 * a meeting that should have happened last week is of no use to anybody, so
 * only occurrences inside the look-ahead window are created.
 */
class RecurringMeetingService
{
    private const LOOK_AHEAD_DAYS = 60;

    private const MAX_PER_TEMPLATE = 20;

    public function generateUpcoming(?CarbonInterface $asOf = null): int
    {
        $now = Carbon::parse($asOf ?? now());
        $horizon = $now->copy()->addDays(self::LOOK_AHEAD_DAYS);
        $created = 0;

        Meeting::query()
            ->whereNotNull('repeat_frequency')
            ->whereNull('recurrence_parent_id')
            ->whereNotIn('status', [MeetingStatus::Cancelled->value])
            ->with(['participants', 'guests'])
            ->chunkById(100, function ($templates) use (&$created, $now, $horizon): void {
                foreach ($templates as $template) {
                    $created += $this->generateForTemplate($template, $now, $horizon);
                }
            });

        return $created;
    }

    private function generateForTemplate(Meeting $template, Carbon $now, Carbon $horizon): int
    {
        $frequency = $template->repeat_frequency;
        $interval = max(1, (int) $template->repeat_interval);

        $anchor = Carbon::parse($template->last_recurred_at ?? $template->starts_at);
        $duration = $template->durationMinutes();

        $created = 0;
        $next = $frequency->advance($anchor, $interval);
        $lastGenerated = null;

        while ($next->lessThanOrEqualTo($horizon) && $created < self::MAX_PER_TEMPLATE) {
            // repeat_until is a date but starts_at is a datetime, so compare
            // against the end of that day: "repeat until the 21st" includes a
            // meeting at 10:00 on the 21st.
            if ($template->repeat_until !== null && $next->greaterThan($template->repeat_until->copy()->endOfDay())) {
                break;
            }

            // Skip occurrences already in the past, but keep advancing so a
            // dormant template catches up to the present rather than stalling.
            if ($next->greaterThan($now)) {
                $this->createInstance($template, $next, $duration);
                $created++;
            }

            $lastGenerated = $next->copy();
            $next = $frequency->advance($next, $interval);
        }

        if ($lastGenerated !== null) {
            $template->forceFill(['last_recurred_at' => $lastGenerated])->saveQuietly();
        }

        return $created;
    }

    private function createInstance(Meeting $template, Carbon $startsAt, int $durationMinutes): void
    {
        $instance = DB::transaction(function () use ($template, $startsAt, $durationMinutes): Meeting {
            $instance = Meeting::create([
                ...$template->only([
                    'owner_id', 'title', 'agenda', 'description', 'type', 'host_id', 'organizer_id',
                    'timezone', 'meeting_link', 'location', 'reminder_minutes', 'created_by',
                ]),
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addMinutes($durationMinutes),
                'status' => MeetingStatus::Scheduled,
                'recurrence_parent_id' => $template->id,
                'updated_by' => $template->created_by,
                // The rule stays on the template only.
                'repeat_interval' => 1,
            ]);

            // Attendees carry over, but nobody has answered for this date yet.
            foreach ($template->participants as $participant) {
                MeetingParticipant::create(['meeting_id' => $instance->id, 'user_id' => $participant->user_id]);
            }

            foreach ($template->guests as $guest) {
                MeetingGuest::create(['meeting_id' => $instance->id, 'email' => $guest->email, 'name' => $guest->name]);
            }

            return $instance;
        });

        $this->invite($instance);
    }

    private function invite(Meeting $meeting): void
    {
        $users = $meeting->participants()->with('user')->get()->pluck('user')->filter();

        if ($users->isNotEmpty()) {
            Notification::send($users, new MeetingInvitationNotification($meeting));
        }

        foreach ($meeting->guests as $guest) {
            Notification::route('mail', $guest->email)
                ->notify(new MeetingInvitationNotification($meeting, $guest->invite_token));
        }
    }
}
