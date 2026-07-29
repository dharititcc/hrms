<?php

namespace App\Policies\Concerns;

use App\Enums\Action;
use App\Enums\Module;
use App\Models\User;

/**
 * Shared authorization rule for every workspace-scoped record.
 *
 * Both halves are required: the record must live in the caller's workspace
 * (tenancy) and their role must grant that action on that module
 * (permission). Keeping them together prevents a policy from accidentally
 * checking only one.
 */
trait AuthorizesWorkspaceAccess
{
    protected function allowsInWorkspace(User $user, int $ownerId, Module $module, Action $action): bool
    {
        return $ownerId === $user->workspaceOwnerId() && $user->hasPermission($module, $action);
    }
}
