<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Covers cancellation and rescheduling, which share a shape: the meeting an
 * attendee agreed to is no longer the meeting that will happen.
 */
class MeetingChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const CANCELLED = 'cancelled';

    public const RESCHEDULED = 'rescheduled';

    public function __construct(
        private readonly Meeting $meeting,
        private readonly string $change,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->headline())
            ->line($this->body());

        if ($this->change === self::RESCHEDULED) {
            $message->line($this->whenLine())
                ->line('Your earlier response has been cleared, so please answer again.')
                ->action('Respond to the invitation', config('app.frontend_url')."/dashboard/meetings/{$this->meeting->id}");
        }

        return $message;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->change === self::CANCELLED ? 'Meeting cancelled' : 'Meeting rescheduled',
            'message' => $this->body(),
            'meeting_id' => $this->meeting->id,
            'starts_at' => $this->meeting->starts_at?->toISOString(),
        ];
    }

    private function headline(): string
    {
        return $this->change === self::CANCELLED
            ? "Cancelled: {$this->meeting->title}"
            : "Rescheduled: {$this->meeting->title}";
    }

    private function body(): string
    {
        return $this->change === self::CANCELLED
            ? "“{$this->meeting->title}” has been cancelled."
            : "“{$this->meeting->title}” has been moved.";
    }

    private function whenLine(): string
    {
        return 'New time: '.$this->meeting->starts_at->timezone($this->meeting->timezone)->toDayDateTimeString()
            ." ({$this->meeting->timezone}).";
    }
}
