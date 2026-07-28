<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class TaskMentionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly TaskComment $comment,
        private readonly User $author,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->author->name} mentioned you on “{$this->task->subject}”")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->author->name} mentioned you in a comment:")
            ->line($this->excerpt())
            ->action('View the task', config('app.frontend_url')."/dashboard/tasks/{$this->task->id}");
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'You were mentioned',
            'message' => "{$this->author->name} mentioned you on “{$this->task->subject}”.",
            'task_id' => $this->task->id,
            'comment_id' => $this->comment->id,
            'excerpt' => $this->excerpt(),
        ];
    }

    /** Strips mention tokens so the preview reads naturally. */
    private function excerpt(): string
    {
        $plain = preg_replace('/@\[([^\]]+)\]\(user:\d+\)/', '@$1', $this->comment->body) ?? $this->comment->body;

        return Str::limit(trim($plain), 140);
    }
}
