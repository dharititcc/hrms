<?php

namespace App\Services;

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\RsvpStatus;
use App\Models\Meeting;
use App\Models\MeetingGuest;
use App\Models\MeetingParticipant;
use App\Models\User;
use App\Notifications\MeetingChangedNotification;
use App\Notifications\MeetingInvitationNotification;
use App\Repositories\MeetingRepository;
use App\Services\Meetings\MeetingLinkProvider;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class MeetingService
{
    public function __construct(
        private readonly MeetingRepository $repository,
        private readonly MeetingLinkProvider $linkProvider,
    ) {}

    public function list(int $ownerId, array $filters = []): LengthAwarePaginator
    {
        return $this->repository->paginateForOwner($ownerId, $filters);
    }

    public function find(Meeting $meeting): Meeting
    {
        return $this->repository->loadDetails($meeting);
    }

    public function create(User $author, array $attributes): Meeting
    {
        [$meeting, $participants, $guests] = DB::transaction(function () use ($author, $attributes): array {
            $participantIds = $attributes['participant_ids'] ?? [];
            $guestRows = $attributes['guests'] ?? [];
            unset($attributes['participant_ids'], $attributes['guests']);

            $meeting = Meeting::create([
                ...$attributes,
                'owner_id' => $author->workspaceOwnerId(),
                // Default both roles to whoever scheduled it.
                'host_id' => $attributes['host_id'] ?? $author->id,
                'organizer_id' => $attributes['organizer_id'] ?? $author->id,
                'status' => MeetingStatus::Scheduled,
                'created_by' => $author->id,
                'updated_by' => $author->id,
            ]);

            $this->applyGeneratedLink($meeting);

            return [$meeting, $this->syncParticipants($meeting, $participantIds), $this->addGuests($meeting, $guestRows)];
        });

        $this->sendInvitations($meeting, $participants, $guests);

        return $this->repository->loadDetails($meeting);
    }

    public function update(Meeting $meeting, User $editor, array $attributes): Meeting
    {
        [$meeting, $participants, $guests] = DB::transaction(function () use ($meeting, $editor, $attributes): array {
            $participantIds = $attributes['participant_ids'] ?? null;
            $guestRows = $attributes['guests'] ?? [];
            unset($attributes['participant_ids'], $attributes['guests']);

            $meeting->update([...$attributes, 'updated_by' => $editor->id]);

            // Only newly added attendees are invited, so an unrelated edit does
            // not re-invite everyone.
            $added = $participantIds === null ? collect() : $this->syncParticipants($meeting, $participantIds);

            return [$meeting->refresh(), $added, $this->addGuests($meeting, $guestRows)];
        });

        $this->sendInvitations($meeting, $participants, $guests);

        return $this->repository->loadDetails($meeting);
    }

    /**
     * Moves a meeting in place and clears every response.
     *
     * Attendees agreed to a specific time, so a previous "accepted" says
     * nothing about the new one. The meeting keeps its id, which preserves its
     * attachments, notes and activity.
     */
    public function reschedule(Meeting $meeting, User $editor, array $attributes): Meeting
    {
        $meeting = DB::transaction(function () use ($meeting, $editor, $attributes): Meeting {
            $meeting->update([
                'starts_at' => $attributes['starts_at'],
                'ends_at' => $attributes['ends_at'],
                'timezone' => $attributes['timezone'] ?? $meeting->timezone,
                'status' => MeetingStatus::Scheduled,
                'reminder_sent_at' => null,
                'updated_by' => $editor->id,
            ]);

            $reset = ['rsvp' => RsvpStatus::Pending, 'responded_at' => null];
            $meeting->participants()->update($reset);
            $meeting->guests()->update($reset);

            return $meeting->refresh();
        });

        $this->notifyAttendees($meeting, MeetingChangedNotification::RESCHEDULED);

        return $this->repository->loadDetails($meeting);
    }

    public function cancel(Meeting $meeting, User $editor): Meeting
    {
        $meeting = DB::transaction(function () use ($meeting, $editor): Meeting {
            $meeting->update(['status' => MeetingStatus::Cancelled, 'updated_by' => $editor->id]);

            return $meeting->refresh();
        });

        $this->notifyAttendees($meeting, MeetingChangedNotification::CANCELLED);

        return $this->repository->loadDetails($meeting);
    }

    /** Copies the meeting and its attendee list to a new time, responses cleared. */
    public function duplicate(Meeting $meeting, User $author, array $attributes): Meeting
    {
        $copy = DB::transaction(function () use ($meeting, $author, $attributes): Meeting {
            $copy = Meeting::create([
                ...$meeting->only([
                    'owner_id', 'title', 'agenda', 'description', 'type', 'host_id', 'organizer_id',
                    'timezone', 'meeting_link', 'location', 'reminder_minutes',
                ]),
                'starts_at' => $attributes['starts_at'],
                'ends_at' => $attributes['ends_at'],
                'status' => MeetingStatus::Scheduled,
                'created_by' => $author->id,
                'updated_by' => $author->id,
                'repeat_interval' => 1,
            ]);

            foreach ($meeting->participants as $participant) {
                MeetingParticipant::create(['meeting_id' => $copy->id, 'user_id' => $participant->user_id]);
            }

            foreach ($meeting->guests as $guest) {
                MeetingGuest::create(['meeting_id' => $copy->id, 'email' => $guest->email, 'name' => $guest->name]);
            }

            return $copy;
        });

        $this->sendInvitations($copy, $copy->participants()->with('user')->get(), $copy->guests()->get());

        return $this->repository->loadDetails($copy);
    }

    public function invite(Meeting $meeting, array $participantIds, array $guestRows): Meeting
    {
        [$participants, $guests] = DB::transaction(fn (): array => [
            $this->syncParticipants($meeting, $participantIds, detach: false),
            $this->addGuests($meeting, $guestRows),
        ]);

        $this->sendInvitations($meeting, $participants, $guests);

        return $this->repository->loadDetails($meeting);
    }

    public function respond(Meeting $meeting, User $user, RsvpStatus $rsvp): MeetingParticipant
    {
        $participant = MeetingParticipant::firstOrNew(['meeting_id' => $meeting->id, 'user_id' => $user->id]);
        $participant->fill(['rsvp' => $rsvp, 'responded_at' => now()])->save();

        return $participant->refresh();
    }

    public function respondAsGuest(MeetingGuest $guest, RsvpStatus $rsvp): MeetingGuest
    {
        $guest->update(['rsvp' => $rsvp, 'responded_at' => now()]);

        return $guest->refresh();
    }

    /** @param  array<int, bool>  $participantAttendance  user id => attended */
    public function recordAttendance(Meeting $meeting, array $participantAttendance, array $guestAttendance): Meeting
    {
        DB::transaction(function () use ($meeting, $participantAttendance, $guestAttendance): void {
            foreach ($participantAttendance as $userId => $attended) {
                $meeting->participants()->where('user_id', $userId)->update(['attended' => (bool) $attended]);
            }

            foreach ($guestAttendance as $guestId => $attended) {
                $meeting->guests()->whereKey($guestId)->update(['attended' => (bool) $attended]);
            }
        });

        return $this->repository->loadDetails($meeting);
    }

    public function delete(Meeting $meeting): void
    {
        DB::transaction(fn () => $meeting->delete());
    }

    /**
     * Asks the configured provider for a joining link, leaving any link the
     * caller supplied untouched. The default provider returns null, so the
     * organiser's own URL survives.
     */
    private function applyGeneratedLink(Meeting $meeting): void
    {
        if ($meeting->meeting_link !== null || ! $this->linkProvider->supports($meeting->type)) {
            return;
        }

        $link = $this->linkProvider->generateLink($meeting);

        if ($link !== null) {
            $meeting->update(['meeting_link' => $link]);
        }
    }

    /** @return Collection<int, MeetingParticipant> newly added participants */
    private function syncParticipants(Meeting $meeting, array $userIds, bool $detach = true): Collection
    {
        $existing = $meeting->participants()->pluck('user_id')->all();
        $added = collect();

        foreach (array_diff($userIds, $existing) as $userId) {
            $added->push(MeetingParticipant::create(['meeting_id' => $meeting->id, 'user_id' => $userId]));
        }

        if ($detach) {
            $meeting->participants()->whereNotIn('user_id', $userIds)->delete();
        }

        // A support collection has no load(), so eager load per model.
        return $added->each(fn (MeetingParticipant $participant) => $participant->load('user'));
    }

    /** @return Collection<int, MeetingGuest> newly added guests */
    private function addGuests(Meeting $meeting, array $rows): Collection
    {
        $existing = $meeting->guests()->pluck('email')->all();
        $added = collect();

        foreach ($rows as $row) {
            // Re-inviting the same address is a no-op rather than an error.
            if (in_array($row['email'], $existing, strict: true)) {
                continue;
            }

            $added->push(MeetingGuest::create([
                'meeting_id' => $meeting->id,
                'email' => $row['email'],
                'name' => $row['name'] ?? null,
            ]));
            $existing[] = $row['email'];
        }

        return $added;
    }

    private function sendInvitations(Meeting $meeting, Collection $participants, Collection $guests): void
    {
        $users = $participants->pluck('user')->filter();

        if ($users->isNotEmpty()) {
            Notification::send($users, new MeetingInvitationNotification($meeting));
        }

        // Guests have no account, so they are notified on demand by address.
        foreach ($guests as $guest) {
            Notification::route('mail', $guest->email)
                ->notify(new MeetingInvitationNotification($meeting, $guest->invite_token));
        }
    }

    private function notifyAttendees(Meeting $meeting, string $change): void
    {
        $users = $meeting->participants()->with('user')->get()->pluck('user')->filter();

        if ($users->isNotEmpty()) {
            Notification::send($users, new MeetingChangedNotification($meeting, $change));
        }

        foreach ($meeting->guests as $guest) {
            Notification::route('mail', $guest->email)->notify(new MeetingChangedNotification($meeting, $change));
        }
    }
}
