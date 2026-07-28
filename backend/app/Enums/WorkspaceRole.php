<?php

namespace App\Enums;

/**
 * A user's role within a workspace. Derived from their staff record's role,
 * or Admin when they are the workspace owner.
 */
enum WorkspaceRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Employee = 'employee';
    case Client = 'client';

    /** Staff roles are the editable source of truth; map them onto workspace roles. */
    public static function fromStaffRole(StaffRole $role): self
    {
        return match ($role) {
            StaffRole::Admin => self::Admin,
            StaffRole::Manager => self::Manager,
            StaffRole::Member => self::Employee,
            StaffRole::Client => self::Client,
        };
    }
}
