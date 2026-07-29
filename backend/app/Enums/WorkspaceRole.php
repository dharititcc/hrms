<?php

namespace App\Enums;

/**
 * A user's role within a workspace. Derived from their employee record's role,
 * or Admin when they are the workspace owner.
 */
enum WorkspaceRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Employee = 'employee';
    case Client = 'client';

    /** Employee roles are the editable source of truth; map them onto workspace roles. */
    public static function fromEmployeeRole(EmployeeRole $role): self
    {
        return match ($role) {
            EmployeeRole::Admin => self::Admin,
            EmployeeRole::Manager => self::Manager,
            EmployeeRole::Employee => self::Employee,
            EmployeeRole::Client => self::Client,
        };
    }
}
