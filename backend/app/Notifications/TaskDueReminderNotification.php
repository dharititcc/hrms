<?php

namespace App\Notifications;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskDueReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Task $task,
        private readonly bool $overdue,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->headline())
            ->greeting("Hello {$notifiable->name},")
            ->line($this->body())
            ->action('View the task', config('app.frontend_url')."/dashboard/tasks/{$this->task->id}");
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->overdue ? 'Task overdue' : 'Task due today',
            'message' => $this->body(),
            'task_id' => $this->task->id,
            'due_date' => $this->task->due_date?->toDateString(),
            'overdue' => $this->overdue,
        ];
    }

    private function headline(): string
    {
        return $this->overdue
            ? "Overdue: {$this->task->subject}"
            : "Due today: {$this->task->subject}";
    }

    private function body(): string
    {
        $due = $this->task->due_date?->toFormattedDateString() ?? 'an unspecified date';

        return $this->overdue
            ? "“{$this->task->subject}” was due on {$due} and is still open."
            : "“{$this->task->subject}” is due today.";
    }
}
