<?php

namespace App\Enums;

/**
 * Status of a payroll run.
 *
 * Distinct from the older App\Enums\PayrollStatus (draft/processed), which
 * belongs to the original payroll_periods table this module supersedes. The
 * two coexist only until that table is retired.
 */
enum PayrollRunStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    /** Once approved, figures are committed and must not be recalculated. */
    public function isLocked(): bool
    {
        return in_array($this, [self::Approved, self::Paid], strict: true);
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::PendingApproval], strict: true);
    }
}
