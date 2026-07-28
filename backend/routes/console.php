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
