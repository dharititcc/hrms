<?php

namespace App\Enums;

enum SalaryCalculation: string
{
    case Fixed = 'fixed';
    case PercentOfBasic = 'percent_of_basic';
    case PercentOfGross = 'percent_of_gross';
    /**
     * Entered per payslip rather than derived. Used for progressive taxes,
     * which cannot be reduced to one rate without being wrong.
     */
    case Manual = 'manual';

    public function isDerived(): bool
    {
        return $this === self::PercentOfBasic || $this === self::PercentOfGross;
    }
}
