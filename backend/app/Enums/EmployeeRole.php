<?php

namespace App\Enums;

enum EmployeeRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    /** Maps to the Employee workspace role. */
    case Member = 'member';
    case Client = 'client';
}
