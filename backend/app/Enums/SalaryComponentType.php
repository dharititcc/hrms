<?php

namespace App\Enums;

enum SalaryComponentType: string
{
    case Earning = 'earning';
    case Deduction = 'deduction';
    /** Paid by the employer, shown on the slip but not taken from net pay. */
    case EmployerContribution = 'employer_contribution';

    public function reducesNetPay(): bool
    {
        return $this === self::Deduction;
    }
}
