<?php

namespace App\Policies\Concerns;

use App\Enums\Ability;
use App\Models\User;

/**
 * Shared authorization rule for every workspace-scoped record.
 *
 * Both halves are required: the record must live in the caller's workspace
 * (tenancy) and their role must grant the ability (permissions). Keeping them
 * together prevents a policy from accidentally checking only one.
 */
trait AuthorizesWorkspaceAccess
{
    protected function allowsInWorkspace(User $user, int $ownerId, Ability $ability): bool
    {
        return $ownerId === $user->workspaceOwnerId() && $user->hasAbility($ability);
    }
}
