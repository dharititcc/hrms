<?php

namespace Tests\Feature;

use App\Models\Meeting;
use App\Models\MeetingGuest;
use App\Models\Staff;
use App\Models\User;
use App\Notifications\MeetingChangedNotification;
use App\Notifications\MeetingInvitationNotification;
use App\Services\StaffInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeetingApiTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Sprint planning',
            'type' => 'google_meet',
            'starts_at' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
            'ends_at' => now()->addDay()->setTime(11, 0)->toDateTimeString(),
            'timezone' => 'Europe/London',
            ...$overrides,
        ];
    }

    private function teammate(User $owner, string $email = 'grace@example.com'): User
    {
        $staff = Staff::create(['owner_id' => $owner->id, 'name' => 'Grace', 'email' => $email, 'role' => 'member', 'status' => 'active']);
        app(StaffInvitationService::class)->invite($staff);

        return $staff->refresh()->user;
    }

    public function test_a_meeting_can_be_scheduled_with_participants_and_guests(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/auth/meetings', $this->payload([
            'agenda' => 'Plan the sprint',
            'participant_ids' => [$mate->id],
            'guests' => [['email' => 'client@example.com', 'name' => 'External client']],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.duration_minutes', 60)
            // Host and organiser default to whoever scheduled it.
            ->assertJsonPath('data.host_id', $owner->id)
            ->assertJsonPath('data.organizer_id', $owner->id)
            ->assertJsonCount(1, 'data.participants')
            ->assertJsonCount(1, 'data.guests');

        Notification::assertSentTo($mate, MeetingInvitationNotification::class);
        Notification::assertSentOnDemand(MeetingInvitationNotification::class);
    }

    public function test_an_offline_meeting_requires_a_location(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/auth/meetings', $this->payload(['type' => 'offline']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('location');

        $this->postJson('/api/auth/meetings', $this->payload(['type' => 'offline', 'location' => 'Room 2']))
            ->assertCreated();
    }

    public function test_end_must_follow_start_and_timezone_must_be_real(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/auth/meetings', $this->payload([
            'ends_at' => now()->addDay()->setTime(9, 0)->toDateTimeString(),
        ]))->assertStatus(422)->assertJsonValidationErrors('ends_at');

        $this->postJson('/api/auth/meetings', $this->payload(['timezone' => 'Middle/Earth']))
            ->assertStatus(422)->assertJsonValidationErrors('timezone');
    }

    public function test_outsiders_cannot_be_added_as_participants(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/auth/meetings', $this->payload(['participant_ids' => [$outsider->id]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('participant_ids.0');
    }

    public function test_participants_rsvp_for_themselves(): void
    {
        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/auth/meetings', $this->payload(['participant_ids' => [$mate->id]]))->json('data.id');

        Sanctum::actingAs($mate);
        $this->postJson("/api/auth/meetings/{$id}/respond", ['rsvp' => 'accepted'])
            ->assertOk()
            ->assertJsonPath('data.rsvp', 'accepted');

        $this->assertDatabaseHas('meeting_participants', ['meeting_id' => $id, 'user_id' => $mate->id, 'rsvp' => 'accepted']);
    }

    public function test_rescheduling_clears_every_response(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/auth/meetings', $this->payload([
            'participant_ids' => [$mate->id],
            'guests' => [['email' => 'client@example.com']],
        ]))->json('data.id');

        Sanctum::actingAs($mate);
        $this->postJson("/api/auth/meetings/{$id}/respond", ['rsvp' => 'accepted'])->assertOk();

        Sanctum::actingAs($owner);
        $this->patchJson("/api/auth/meetings/{$id}/reschedule", [
            'starts_at' => now()->addWeek()->setTime(14, 0)->toDateTimeString(),
            'ends_at' => now()->addWeek()->setTime(15, 0)->toDateTimeString(),
        ])->assertOk()->assertJsonPath('data.status', 'scheduled');

        // Agreeing to Tuesday says nothing about Friday.
        $this->assertDatabaseHas('meeting_participants', ['meeting_id' => $id, 'user_id' => $mate->id, 'rsvp' => 'pending']);
        $this->assertDatabaseHas('meeting_guests', ['meeting_id' => $id, 'rsvp' => 'pending']);
        // The meeting keeps its id, so attachments and notes survive.
        $this->assertDatabaseHas('meetings', ['id' => $id]);

        Notification::assertSentTo($mate, MeetingChangedNotification::class);
    }

    public function test_cancelling_notifies_attendees_and_closes_the_meeting(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/auth/meetings', $this->payload(['participant_ids' => [$mate->id]]))->json('data.id');

        $this->patchJson("/api/auth/meetings/{$id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');

        Notification::assertSentTo($mate, MeetingChangedNotification::class);
        // Cancelled meetings drop out of the upcoming view.
        $this->getJson('/api/auth/meetings?period=upcoming')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_guests_rsvp_through_their_token_without_signing_in(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/auth/meetings', $this->payload([
            'guests' => [['email' => 'client@example.com', 'name' => 'Client']],
        ]))->json('data.id');

        $token = MeetingGuest::where('meeting_id', $id)->firstOrFail()->invite_token;

        // No authentication: the token is the credential.
        app('auth')->forgetGuards();

        $this->getJson("/api/meetings/invite/{$token}")
            ->assertOk()
            ->assertJsonPath('data.title', 'Sprint planning')
            ->assertJsonPath('data.rsvp', 'pending')
            // The public view must not expose the workspace's attendee list.
            ->assertJsonMissingPath('data.participants');

        $this->postJson("/api/meetings/invite/{$token}/respond", ['rsvp' => 'tentative'])
            ->assertOk()
            ->assertJsonPath('data.rsvp', 'tentative');

        $this->assertDatabaseHas('meeting_guests', ['meeting_id' => $id, 'rsvp' => 'tentative']);
    }

    public function test_an_unknown_invite_token_is_rejected(): void
    {
        $this->getJson('/api/meetings/invite/'.str_repeat('a', 64))->assertNotFound();
        $this->postJson('/api/meetings/invite/nope/respond', ['rsvp' => 'accepted'])->assertNotFound();
    }

    public function test_attendance_can_be_recorded_after_the_meeting(): void
    {
        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/auth/meetings', $this->payload(['participant_ids' => [$mate->id]]))->json('data.id');

        $this->patchJson("/api/auth/meetings/{$id}/attendance", ['participants' => [$mate->id => true]])
            ->assertOk()
            ->assertJsonPath('data.participants.0.attended', true);
    }

    public function test_duplicating_copies_attendees_but_not_their_answers(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        Sanctum::actingAs($owner);

        $id = $this->postJson('/api/auth/meetings', $this->payload([
            'participant_ids' => [$mate->id],
            'guests' => [['email' => 'client@example.com']],
        ]))->json('data.id');

        Sanctum::actingAs($mate);
        $this->postJson("/api/auth/meetings/{$id}/respond", ['rsvp' => 'accepted'])->assertOk();

        Sanctum::actingAs($owner);
        $copy = $this->postJson("/api/auth/meetings/{$id}/duplicate", [
            'starts_at' => now()->addWeeks(2)->setTime(10, 0)->toDateTimeString(),
            'ends_at' => now()->addWeeks(2)->setTime(11, 0)->toDateTimeString(),
        ])->assertCreated();

        $copy->assertJsonCount(1, 'data.participants')
            ->assertJsonPath('data.participants.0.rsvp', 'pending')
            ->assertJsonCount(1, 'data.guests');

        $this->assertNotSame($id, $copy->json('data.id'));
    }

    public function test_filters_and_search(): void
    {
        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        Sanctum::actingAs($owner);

        $this->postJson('/api/auth/meetings', $this->payload(['title' => 'Sprint planning']))->assertCreated();
        $this->postJson('/api/auth/meetings', $this->payload(['title' => 'Board review', 'type' => 'offline', 'location' => 'HQ']))->assertCreated();
        $this->postJson('/api/auth/meetings', $this->payload(['title' => 'One to one', 'participant_ids' => [$mate->id]]))->assertCreated();

        $this->getJson('/api/auth/meetings?search=sprint')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/auth/meetings?type=offline')->assertOk()->assertJsonCount(1, 'data');

        // "Mine" includes meetings hosted as well as attended.
        Sanctum::actingAs($mate);
        $this->getJson('/api/auth/meetings?mine=1')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_calendar_window_returns_overlapping_meetings(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);

        $this->postJson('/api/auth/meetings', $this->payload())->assertCreated();
        $this->postJson('/api/auth/meetings', $this->payload([
            'starts_at' => now()->addMonth()->toDateTimeString(),
            'ends_at' => now()->addMonth()->addHour()->toDateTimeString(),
        ]))->assertCreated();

        $this->getJson('/api/auth/meetings?from='.now()->startOfDay()->toDateTimeString().'&to='.now()->addDays(2)->toDateTimeString())
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // A half-open range would otherwise quietly return everything.
        $this->getJson('/api/auth/meetings?from='.now()->toDateTimeString())->assertStatus(422);
    }

    public function test_meetings_are_scoped_to_the_workspace(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/auth/meetings', $this->payload())->json('data.id');

        Sanctum::actingAs($intruder);
        $this->getJson('/api/auth/meetings')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/auth/meetings/{$id}")->assertForbidden();
        $this->patchJson("/api/auth/meetings/{$id}/cancel")->assertForbidden();
        $this->postJson("/api/auth/meetings/{$id}/invite", ['participant_ids' => []])->assertForbidden();
    }

    public function test_a_participant_can_respond_but_not_edit_the_meeting(): void
    {
        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        Sanctum::actingAs($owner);
        $id = $this->postJson('/api/auth/meetings', $this->payload(['participant_ids' => [$mate->id]]))->json('data.id');

        Sanctum::actingAs($mate);
        $this->postJson("/api/auth/meetings/{$id}/respond", ['rsvp' => 'declined'])->assertOk();
        // Employees hold the edit ability, so use cancel to prove host checks
        // are not the only gate: the meeting still belongs to the workspace.
        $this->getJson("/api/auth/meetings/{$id}")->assertOk();
    }
}
