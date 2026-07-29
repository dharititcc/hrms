<?php

namespace App\Enums;

enum SalaryAssignmentStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    /** Replaced by a later revision; kept for salary history. */
    case Superseded = 'superseded';
    case Ended = 'ended';
}
