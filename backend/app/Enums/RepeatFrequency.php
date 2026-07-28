<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum RepeatFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /** Advances a date by one interval of this frequency. */
    public function advance(CarbonInterface $date, int $interval = 1): CarbonInterface
    {
        return match ($this) {
            self::Daily => $date->copy()->addDays($interval),
            self::Weekly => $date->copy()->addWeeks($interval),
            self::Monthly => $date->copy()->addMonthsNoOverflow($interval),
            self::Yearly => $date->copy()->addYearsNoOverflow($interval),
        };
    }
}
