<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceAccess;

class TaskPolicy
{
    use AuthorizesWorkspaceAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasAbility(Ability::View);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->allowsInWorkspace($user, $task->owner_id, Ability::View);
    }

    public function create(User $user): bool
    {
        return $user->hasAbility(Ability::Create);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->allowsInWorkspace($user, $task->owner_id, Ability::Edit);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->allowsInWorkspace($user, $task->owner_id, Ability::Delete);
    }

    /** Changing who a task is assigned to is a separate ability. */
    public function assign(User $user, Task $task): bool
    {
        return $this->allowsInWorkspace($user, $task->owner_id, Ability::Assign);
    }
}
