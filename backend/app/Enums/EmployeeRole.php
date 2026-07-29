<?php

namespace App\Enums;

enum EmployeeRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Employee = 'employee';
    case Client = 'client';
}
