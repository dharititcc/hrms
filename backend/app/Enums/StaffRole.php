<?php

namespace App\Enums;

enum StaffRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Member = 'member';
}
