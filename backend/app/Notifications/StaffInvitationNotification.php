<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $workspaceName,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You have been invited to {$this->workspaceName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have been invited to join {$this->workspaceName}.")
            ->line('Set a password to activate your account.')
            ->action('Set your password', $this->setPasswordUrl($notifiable))
            ->line('If you were not expecting this invitation, you can ignore this email.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Workspace invitation',
            'message' => "You have been invited to join {$this->workspaceName}.",
            'workspace' => $this->workspaceName,
        ];
    }

    private function setPasswordUrl(object $notifiable): string
    {
        return config('app.frontend_url')
            .'/reset-password?token='.$this->token
            .'&email='.urlencode($notifiable->email);
    }
}
