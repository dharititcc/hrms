<?php

namespace Tests\Feature;

use App\Enums\MeetingStatus;
use App\Enums\RepeatFrequency;
use App\Models\Employee;
use App\Models\Meeting;
use App\Models\MeetingGuest;
use App\Models\MeetingParticipant;
use App\Models\User;
use App\Notifications\MeetingInvitationNotification;
use App\Notifications\MeetingReminderNotification;
use App\Services\EmployeeInvitationService;
use App\Services\MeetingReminderService;
use App\Services\RecurringMeetingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MeetingSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function meeting(User $owner, array $overrides = []): Meeting
    {
        return Meeting::create([
            'owner_id' => $owner->id,
            'title' => 'Weekly standup',
            'type' => 'google_meet',
            'status' => MeetingStatus::Scheduled,
            'host_id' => $owner->id,
            'organizer_id' => $owner->id,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHour()->addMinutes(30),
            'timezone' => 'UTC',
            'created_by' => $owner->id,
            ...$overrides,
        ]);
    }

    private function teammate(User $owner, string $email = 'grace@example.com'): User
    {
        $employee = Employee::create(['owner_id' => $owner->id, 'name' => 'Grace', 'email' => $email, 'role' => 'employee', 'status' => 'active']);
        app(EmployeeInvitationService::class)->invite($employee);

        return $employee->refresh()->user;
    }

    // --- Reminders ------------------------------------------------------

    public function test_reminders_fire_once_inside_the_window(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        $meeting = $this->meeting($owner, ['starts_at' => now()->addMinutes(10), 'ends_at' => now()->addMinutes(40), 'reminder_minutes' => 15]);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'user_id' => $mate->id]);
        MeetingGuest::create(['meeting_id' => $meeting->id, 'email' => 'client@example.com']);

        $service = app(MeetingReminderService::class);

        $this->assertSame(1, $service->sendDue());
        Notification::assertSentTo($mate, MeetingReminderNotification::class);
        Notification::assertSentOnDemand(MeetingReminderNotification::class);

        // Running again must not re-notify.
        $this->assertSame(0, $service->sendDue());
        Notification::assertSentToTimes($mate, MeetingReminderNotification::class, 1);
    }

    public function test_reminders_wait_until_the_window_opens(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        // Two hours away with a 15 minute reminder: nothing yet.
        $meeting = $this->meeting($owner, ['starts_at' => now()->addHours(2), 'ends_at' => now()->addHours(3), 'reminder_minutes' => 15]);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'user_id' => $mate->id]);

        $service = app(MeetingReminderService::class);
        $this->assertSame(0, $service->sendDue());

        $this->travel(110)->minutes();
        $this->assertSame(1, $service->sendDue());
    }

    public function test_started_and_cancelled_meetings_are_not_reminded(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $mate = $this->teammate($owner);

        // A reminder after the meeting has begun is noise, not help.
        $started = $this->meeting($owner, ['starts_at' => now()->subMinutes(5), 'ends_at' => now()->addMinutes(25), 'reminder_minutes' => 15]);
        MeetingParticipant::create(['meeting_id' => $started->id, 'user_id' => $mate->id]);

        $cancelled = $this->meeting($owner, ['starts_at' => now()->addMinutes(10), 'ends_at' => now()->addMinutes(40), 'reminder_minutes' => 15, 'status' => MeetingStatus::Cancelled]);
        MeetingParticipant::create(['meeting_id' => $cancelled->id, 'user_id' => $mate->id]);

        $this->assertSame(0, app(MeetingReminderService::class)->sendDue());
        Notification::assertNotSentTo($mate, MeetingReminderNotification::class);
    }

    public function test_meetings_without_a_reminder_are_left_alone(): void
    {
        $owner = User::factory()->create();
        $this->meeting($owner, ['starts_at' => now()->addMinutes(5), 'ends_at' => now()->addMinutes(35)]);

        $this->assertSame(0, app(MeetingReminderService::class)->sendDue());
    }

    // --- Recurrence -----------------------------------------------------

    public function test_upcoming_occurrences_are_generated_with_attendees_reset(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $mate = $this->teammate($owner);
        $template = $this->meeting($owner, [
            'repeat_frequency' => RepeatFrequency::Weekly,
            'repeat_until' => now()->addWeeks(3)->toDateString(),
        ]);
        MeetingParticipant::create(['meeting_id' => $template->id, 'user_id' => $mate->id, 'rsvp' => 'accepted']);
        MeetingGuest::create(['meeting_id' => $template->id, 'email' => 'client@example.com']);

        $created = app(RecurringMeetingService::class)->generateUpcoming();

        $this->assertSame(3, $created);
        $instances = Meeting::where('recurrence_parent_id', $template->id)->orderBy('starts_at')->get();

        // Duration carries over from the template.
        $this->assertSame(30, $instances->first()->durationMinutes());
        // Accepting one week says nothing about the next.
        $this->assertSame('pending', $instances->first()->participants->first()->rsvp->value);
        $this->assertCount(1, $instances->first()->guests);

        Notification::assertSentTo($mate, MeetingInvitationNotification::class);
    }

    public function test_generation_is_idempotent_and_instances_do_not_recur(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $template = $this->meeting($owner, [
            'repeat_frequency' => RepeatFrequency::Weekly,
            'repeat_until' => now()->addWeeks(2)->toDateString(),
        ]);

        $service = app(RecurringMeetingService::class);
        $this->assertSame(2, $service->generateUpcoming());
        $this->assertSame(0, $service->generateUpcoming());

        $instance = Meeting::where('recurrence_parent_id', $template->id)->first();
        $this->assertNull($instance->repeat_frequency);
        $this->assertFalse($instance->repeats());
    }

    public function test_past_occurrences_are_skipped_but_the_series_catches_up(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        // Anchored three weeks back: those dates are gone, so only future
        // occurrences inside the horizon are created.
        $template = $this->meeting($owner, [
            'starts_at' => now()->subWeeks(3),
            'ends_at' => now()->subWeeks(3)->addHour(),
            'repeat_frequency' => RepeatFrequency::Weekly,
        ]);

        $created = app(RecurringMeetingService::class)->generateUpcoming();

        $this->assertGreaterThan(0, $created);
        $this->assertSame(0, Meeting::where('recurrence_parent_id', $template->id)->where('starts_at', '<', now())->count());
    }

    public function test_cancelled_templates_do_not_generate(): void
    {
        $owner = User::factory()->create();
        $this->meeting($owner, [
            'repeat_frequency' => RepeatFrequency::Weekly,
            'status' => MeetingStatus::Cancelled,
        ]);

        $this->assertSame(0, app(RecurringMeetingService::class)->generateUpcoming());
    }

    public function test_commands_run_and_report(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $this->meeting($owner, ['repeat_frequency' => RepeatFrequency::Weekly, 'repeat_until' => now()->addWeeks(2)->toDateString()]);

        $this->artisan('meetings:generate-recurrences')->expectsOutputToContain('Generated 2')->assertSuccessful();
        $this->artisan('meetings:send-reminders')->assertSuccessful();
    }
}
