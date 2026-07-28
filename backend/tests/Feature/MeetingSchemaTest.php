<?php

namespace Tests\Feature;

use App\Enums\MeetingStatus;
use App\Enums\MeetingType;
use App\Enums\RepeatFrequency;
use App\Enums\RsvpStatus;
use App\Models\Meeting;
use App\Models\MeetingGuest;
use App\Models\MeetingParticipant;
use App\Models\Tag;
use App\Models\User;
use App\Services\Meetings\MeetingLinkProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MeetingSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function meeting(User $owner, array $overrides = []): Meeting
    {
        return Meeting::create([
            'owner_id' => $owner->id,
            'title' => 'Sprint planning',
            'type' => MeetingType::GoogleMeet,
            'status' => MeetingStatus::Scheduled,
            'host_id' => $owner->id,
            'organizer_id' => $owner->id,
            'starts_at' => now()->addDay()->setTime(10, 0),
            'ends_at' => now()->addDay()->setTime(11, 30),
            'timezone' => 'Europe/London',
            'created_by' => $owner->id,
            ...$overrides,
        ]);
    }

    public function test_casts_defaults_and_duration(): void
    {
        $owner = User::factory()->create();
        $meeting = $this->meeting($owner)->refresh();

        $this->assertSame(MeetingStatus::Scheduled, $meeting->status);
        $this->assertSame(MeetingType::GoogleMeet, $meeting->type);
        $this->assertSame('Europe/London', $meeting->timezone);
        // Duration is derived, never stored, so it cannot drift from the dates.
        $this->assertSame(90, $meeting->durationMinutes());
        $this->assertTrue($meeting->type->isVirtual());
        $this->assertFalse(MeetingType::Offline->isVirtual());
    }

    public function test_participants_default_to_pending_and_are_unique(): void
    {
        $owner = User::factory()->create();
        $attendee = User::factory()->create();
        $meeting = $this->meeting($owner);

        $participant = MeetingParticipant::create(['meeting_id' => $meeting->id, 'user_id' => $attendee->id]);

        $this->assertSame(RsvpStatus::Pending, $participant->refresh()->rsvp);
        $this->assertFalse($participant->attended);
        $this->assertCount(1, $meeting->participants);
        $this->assertTrue($meeting->participantUsers->first()->is($attendee));

        // The unique constraint prevents inviting the same person twice.
        $this->expectException(\Illuminate\Database\QueryException::class);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'user_id' => $attendee->id]);
    }

    public function test_guests_get_a_generated_token_that_is_hidden_from_output(): void
    {
        $owner = User::factory()->create();
        $meeting = $this->meeting($owner);

        $guest = MeetingGuest::create(['meeting_id' => $meeting->id, 'email' => 'client@example.com', 'name' => 'External client']);

        $this->assertNotEmpty($guest->invite_token);
        $this->assertSame(64, strlen($guest->invite_token));
        $this->assertSame(RsvpStatus::Pending, $guest->refresh()->rsvp);
        // The token backs a public link, so it must never leak through the model.
        $this->assertArrayNotHasKey('invite_token', $guest->toArray());
    }

    public function test_guest_tokens_are_unique_across_meetings(): void
    {
        $owner = User::factory()->create();
        $first = MeetingGuest::create(['meeting_id' => $this->meeting($owner)->id, 'email' => 'a@example.com']);
        $second = MeetingGuest::create(['meeting_id' => $this->meeting($owner)->id, 'email' => 'a@example.com']);

        $this->assertNotSame($first->invite_token, $second->invite_token);
    }

    public function test_deleting_a_meeting_removes_its_attendees(): void
    {
        $owner = User::factory()->create();
        $meeting = $this->meeting($owner);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'user_id' => User::factory()->create()->id]);
        MeetingGuest::create(['meeting_id' => $meeting->id, 'email' => 'guest@example.com']);

        $meeting->forceDelete();

        $this->assertDatabaseCount('meeting_participants', 0);
        $this->assertDatabaseCount('meeting_guests', 0);
    }

    public function test_soft_delete_keeps_the_meeting_recoverable(): void
    {
        $owner = User::factory()->create();
        $meeting = $this->meeting($owner);

        $meeting->delete();

        $this->assertSoftDeleted('meetings', ['id' => $meeting->id]);
        $this->assertSame(0, Meeting::count());
        $this->assertSame(1, Meeting::withTrashed()->count());
    }

    public function test_upcoming_past_and_between_scopes(): void
    {
        $owner = User::factory()->create();

        $this->meeting($owner, ['title' => 'Tomorrow']);
        $this->meeting($owner, ['title' => 'Last week', 'starts_at' => now()->subWeek(), 'ends_at' => now()->subWeek()->addHour()]);
        // Cancelled meetings are not upcoming, however future.
        $this->meeting($owner, ['title' => 'Cancelled', 'status' => MeetingStatus::Cancelled]);

        $this->assertSame(1, Meeting::upcoming()->count());
        $this->assertSame(1, Meeting::past()->count());

        // A calendar window returns anything overlapping it.
        $window = Meeting::between(now()->addDay()->startOfDay(), now()->addDay()->endOfDay())->count();
        $this->assertSame(2, $window);
    }

    public function test_reschedule_links_the_original_to_its_replacement(): void
    {
        $owner = User::factory()->create();
        $original = $this->meeting($owner, ['title' => 'Original']);
        $replacement = $this->meeting($owner, ['title' => 'Moved', 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHour()]);

        $original->update(['status' => MeetingStatus::Rescheduled, 'rescheduled_to_id' => $replacement->id]);

        // The original survives as history rather than being overwritten.
        $this->assertTrue($original->refresh()->rescheduledTo->is($replacement));
        $this->assertSame(MeetingStatus::Rescheduled, $original->status);
        $this->assertTrue($original->status->isClosed());
    }

    public function test_recurrence_and_tags_reuse_the_shared_behaviour(): void
    {
        $owner = User::factory()->create();
        $template = $this->meeting($owner, [
            'repeat_frequency' => RepeatFrequency::Weekly,
            'repeat_until' => now()->addMonths(2)->toDateString(),
        ]);
        $instance = $this->meeting($owner, ['recurrence_parent_id' => $template->id]);

        $this->assertTrue($template->repeats());
        $this->assertFalse($instance->repeats());
        $this->assertTrue($instance->recurrenceParent->is($template));

        $tag = Tag::create(['owner_id' => $owner->id, 'name' => 'leadership', 'color' => 'blue']);
        $template->tags()->sync([$tag->id]);

        // Tags are polymorphic, so meetings reuse the task tag table.
        $this->assertCount(1, $template->fresh()->tags);
        $this->assertDatabaseHas('taggables', ['taggable_type' => 'meeting', 'taggable_id' => $template->id]);
    }

    public function test_default_link_provider_declines_to_invent_a_link(): void
    {
        $owner = User::factory()->create();
        $provider = app(MeetingLinkProvider::class);

        // Until Google credentials are wired up, the organiser supplies the URL.
        // Fabricating one would produce links that look valid and fail on click.
        $this->assertTrue($provider->supports(MeetingType::GoogleMeet));
        $this->assertFalse($provider->supports(MeetingType::Offline));
        $this->assertNull($provider->generateLink($this->meeting($owner)));
    }

    public function test_timestamps_are_stored_in_utc_while_the_zone_is_preserved(): void
    {
        $owner = User::factory()->create();
        $meeting = $this->meeting($owner, [
            'starts_at' => Carbon::parse('2026-08-01 09:00:00', 'Europe/London')->utc(),
            'ends_at' => Carbon::parse('2026-08-01 10:00:00', 'Europe/London')->utc(),
            'timezone' => 'Europe/London',
        ])->refresh();

        // London is UTC+1 in August, so 09:00 local is stored as 08:00 UTC.
        $this->assertSame('2026-08-01 08:00:00', $meeting->starts_at->toDateTimeString());
        $this->assertSame('Europe/London', $meeting->timezone);
        $this->assertSame(60, $meeting->durationMinutes());
    }
}
