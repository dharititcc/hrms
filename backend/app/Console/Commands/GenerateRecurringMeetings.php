<?php

namespace App\Console\Commands;

use App\Services\RecurringMeetingService;
use Illuminate\Console\Command;

class GenerateRecurringMeetings extends Command
{
    protected $signature = 'meetings:generate-recurrences';

    protected $description = 'Create upcoming occurrences of recurring meetings';

    public function handle(RecurringMeetingService $service): int
    {
        $created = $service->generateUpcoming();

        $this->info("Generated {$created} recurring meeting(s).");

        return self::SUCCESS;
    }
}
