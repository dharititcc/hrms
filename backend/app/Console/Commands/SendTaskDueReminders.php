<?php

namespace App\Console\Commands;

use App\Services\TaskReminderService;
use Illuminate\Console\Command;

class SendTaskDueReminders extends Command
{
    protected $signature = 'tasks:send-due-reminders';

    protected $description = 'Notify assignees of tasks due today or overdue';

    public function handle(TaskReminderService $service): int
    {
        $reminded = $service->sendDueReminders();

        $this->info("Sent reminders for {$reminded} task(s).");

        return self::SUCCESS;
    }
}
