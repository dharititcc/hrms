<?php

namespace App\Policies;

use App\Enums\Action;
use App\Enums\Module;
use App\Models\Staff;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceAccess;

class StaffPolicy
{
    use AuthorizesWorkspaceAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Module::Staff, Action::View);
    }

    public function view(User $user, Staff $staff): bool
    {
        return $this->allowsInWorkspace($user, $staff->owner_id, Module::Staff, Action::View);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Module::Staff, Action::Create);
    }

    public function update(User $user, Staff $staff): bool
    {
        return $this->allowsInWorkspace($user, $staff->owner_id, Module::Staff, Action::Edit);
    }

    public function delete(User $user, Staff $staff): bool
    {
        return $this->allowsInWorkspace($user, $staff->owner_id, Module::Staff, Action::Delete);
    }
}
