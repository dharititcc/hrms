<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Meeting $meeting) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // Guests are notified on demand by address and have nothing in-app.
        return $notifiable instanceof \App\Models\User ? ['mail', 'database'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Starting soon: {$this->meeting->title}")
            ->line("“{$this->meeting->title}” starts at ".$this->localStart().'.');

        if ($this->meeting->type->isVirtual() && $this->meeting->meeting_link !== null) {
            $message->action('Join the meeting', $this->meeting->meeting_link);
        } elseif ($this->meeting->location !== null) {
            $message->line("Location: {$this->meeting->location}");
        }

        return $message;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Meeting starting soon',
            'message' => "“{$this->meeting->title}” starts at {$this->localStart()}.",
            'meeting_id' => $this->meeting->id,
            'starts_at' => $this->meeting->starts_at?->toISOString(),
        ];
    }

    private function localStart(): string
    {
        return $this->meeting->starts_at->timezone($this->meeting->timezone)->format('H:i')
            ." ({$this->meeting->timezone})";
    }
}
