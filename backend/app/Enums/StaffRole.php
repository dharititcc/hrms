<?php

namespace App\Enums;

enum StaffRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    /** Maps to the Employee workspace role. */
    case Member = 'member';
    case Client = 'client';
}
