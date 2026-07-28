<?php

namespace App\Policies;

use App\Enums\Action;
use App\Enums\Module;
use App\Models\Task;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceAccess;

class TaskPolicy
{
    use AuthorizesWorkspaceAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Module::Tasks, Action::View);
    }

    public function view(User $user, Task $task): bool
    {
        return $this->allowsInWorkspace($user, $task->owner_id, Module::Tasks, Action::View);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Module::Tasks, Action::Create);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->allowsInWorkspace($user, $task->owner_id, Module::Tasks, Action::Edit);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->allowsInWorkspace($user, $task->owner_id, Module::Tasks, Action::Delete);
    }

    public function assign(User $user, Task $task): bool
    {
        return $this->allowsInWorkspace($user, $task->owner_id, Module::Tasks, Action::Assign);
    }
}
