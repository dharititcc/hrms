<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Both commands require a scheduler: `php artisan schedule:work` locally, or a
| cron entry running `schedule:run` every minute in deployment. Without it,
| recurring tasks are never generated and no reminders go out.
|
| Both are safe to run more than once a day — recurrence advances from
| last_recurred_at, and reminders are gated by last_reminded_on.
*/
Schedule::command('tasks:generate-recurrences')->dailyAt('01:00')->withoutOverlapping();
Schedule::command('tasks:send-due-reminders')->dailyAt('07:00')->withoutOverlapping();

/*
| Runs before the recurrence job so a forgotten check-out is closed and sent
| for approval rather than sitting at zero worked forever. Only touches days
| already past, so it can never close a shift somebody is still working.
*/
Schedule::command('attendance:close-abandoned')->dailyAt('00:30')->withoutOverlapping();
Schedule::command('meetings:generate-recurrences')->dailyAt('01:15')->withoutOverlapping();

/*
| Meeting reminders run every five minutes rather than daily: a reminder set
| for 15 minutes before the start is useless once a day has passed.
| reminder_sent_at keeps repeat runs from sending twice.
*/
Schedule::command('meetings:send-reminders')->everyFiveMinutes()->withoutOverlapping();
