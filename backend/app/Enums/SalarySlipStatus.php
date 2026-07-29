<?php

namespace App\Enums;

/**
 * Where one payslip stands, which is not the same as where its run stands.
 *
 * A run is approved as a whole, but paid one slip at a time — a failed bank
 * transfer leaves that employee unpaid while everybody else is settled.
 */
enum SalarySlipStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /** Derived from what has actually been recorded against the slip. */
    public static function forAmounts(float $net, float $paid): self
    {
        if ($paid <= 0) {
            return self::Approved;
        }

        return $paid + 0.001 >= $net ? self::Paid : self::PartiallyPaid;
    }
}
