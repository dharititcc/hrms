<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case HalfDay = 'half_day';
    case Absent = 'absent';
    case OnLeave = 'on_leave';
    case Holiday = 'holiday';

    public function countsAsWorked(): bool
    {
        return in_array($this, [self::Present, self::Late, self::HalfDay], strict: true);
    }
}
