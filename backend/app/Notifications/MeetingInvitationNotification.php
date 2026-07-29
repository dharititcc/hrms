<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string|null  $guestToken  set for external guests, who answer
     *                                   through a public link rather than by
     *                                   signing in
     */
    public function __construct(
        private readonly Meeting $meeting,
        private readonly ?string $guestToken = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // Guests have no account, so there is nothing to store in-app for them.
        return $this->guestToken === null ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Invitation: {$this->meeting->title}")
            ->line("You have been invited to “{$this->meeting->title}”.")
            ->line($this->whenLine());

        if ($this->meeting->agenda !== null) {
            $message->line("Agenda: {$this->meeting->agenda}");
        }

        $message->line($this->meeting->type->isVirtual()
            ? ($this->meeting->meeting_link ?? 'A joining link will follow.')
            : "Location: {$this->meeting->location}");

        return $message->action('Respond to the invitation', $this->respondUrl());
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Meeting invitation',
            'message' => "You have been invited to “{$this->meeting->title}”.",
            'meeting_id' => $this->meeting->id,
            'starts_at' => $this->meeting->starts_at?->toISOString(),
        ];
    }

    private function whenLine(): string
    {
        return $this->meeting->starts_at->timezone($this->meeting->timezone)->toDayDateTimeString()
            ." ({$this->meeting->timezone}), {$this->meeting->durationMinutes()} minutes.";
    }

    private function respondUrl(): string
    {
        return $this->guestToken === null
            ? config('app.frontend_url')."/meetings/{$this->meeting->id}"
            : config('app.frontend_url')."/meetings/invite/{$this->guestToken}";
    }
}
