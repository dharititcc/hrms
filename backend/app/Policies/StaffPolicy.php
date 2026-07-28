<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Staff;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceAccess;

class StaffPolicy
{
    use AuthorizesWorkspaceAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasAbility(Ability::View);
    }

    public function view(User $user, Staff $staff): bool
    {
        return $this->allowsInWorkspace($user, $staff->owner_id, Ability::View);
    }

    public function create(User $user): bool
    {
        return $user->hasAbility(Ability::Create);
    }

    public function update(User $user, Staff $staff): bool
    {
        return $this->allowsInWorkspace($user, $staff->owner_id, Ability::Edit);
    }

    public function delete(User $user, Staff $staff): bool
    {
        return $this->allowsInWorkspace($user, $staff->owner_id, Ability::Delete);
    }
}
