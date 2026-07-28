<?php

namespace App\Console\Commands;

use App\Services\RecurringTaskService;
use Illuminate\Console\Command;

class GenerateRecurringTasks extends Command
{
    protected $signature = 'tasks:generate-recurrences';

    protected $description = 'Create the next occurrence of any recurring task that is due';

    public function handle(RecurringTaskService $service): int
    {
        $created = $service->generateDue();

        $this->info("Generated {$created} recurring task(s).");

        return self::SUCCESS;
    }
}
