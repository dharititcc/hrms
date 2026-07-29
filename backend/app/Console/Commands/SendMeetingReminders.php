<?php

namespace App\Console\Commands;

use App\Services\MeetingReminderService;
use Illuminate\Console\Command;

class SendMeetingReminders extends Command
{
    protected $signature = 'meetings:send-reminders';

    protected $description = 'Notify attendees of meetings whose reminder window has opened';

    public function handle(MeetingReminderService $service): int
    {
        $sent = $service->sendDue();

        $this->info("Sent reminders for {$sent} meeting(s).");

        return self::SUCCESS;
    }
}
