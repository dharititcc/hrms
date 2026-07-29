<?php

namespace App\Support;

use App\Enums\Action;
use App\Enums\Module;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;

/**
 * Narrows a workspace-scoped query to the caller's own records unless they
 * hold view-all on that module.
 *
 * Tenancy (owner_id) is applied separately and always; this is the second,
 * finer cut. Keeping it in one place means a module cannot quietly forget it.
 */
final class RecordScope
{
    /**
     * @param  string  $staffColumn  the column identifying whose record it is
     */
    public static function apply(BuilderContract $query, User $user, Module $module, string $staffColumn = 'staff_id'): BuilderContract
    {
        if ($user->hasPermission($module, Action::ViewAll)) {
            return $query;
        }

        $employeeId = $user->employeeId();

        // A user with no employee record — the workspace owner — always holds
        // view-all, so reaching here without one means they own nothing
        // personal in this module. Return no rows rather than everything.
        return $employeeId === null
            ? $query->whereRaw('1 = 0')
            : $query->where($staffColumn, $employeeId);
    }

    /** Whether the caller may read a specific record in this module. */
    public static function allows(User $user, Module $module, ?int $recordEmployeeId): bool
    {
        if ($user->hasPermission($module, Action::ViewAll)) {
            return true;
        }

        return $recordEmployeeId !== null && $recordEmployeeId === $user->employeeId();
    }
}
