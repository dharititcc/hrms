<?php

namespace App\Notifications;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly User $assignedBy,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("You have been assigned “{$this->task->subject}”")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->assignedBy->name} assigned you a task: {$this->task->subject}");

        if ($this->task->due_date !== null) {
            $message->line("Due {$this->task->due_date->toFormattedDateString()}.");
        }

        return $message->action('View the task', config('app.frontend_url')."/tasks/{$this->task->id}");
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Task assigned',
            'message' => "{$this->assignedBy->name} assigned you “{$this->task->subject}”.",
            'task_id' => $this->task->id,
            'due_date' => $this->task->due_date?->toDateString(),
            'priority' => $this->task->priority->value,
        ];
    }
}
