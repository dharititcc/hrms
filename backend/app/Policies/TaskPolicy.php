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

    /**
     * Listing is filtered in the repository, but a record can also be fetched
     * by id, so involvement is checked here too. Otherwise a Client could read
     * any task by guessing a number.
     */
    public function view(User $user, Task $task): bool
    {
        if (! $this->allowsInWorkspace($user, $task->owner_id, Module::Tasks, Action::View)) {
            return false;
        }

        if ($user->hasPermission(Module::Tasks, Action::ViewAll)) {
            return true;
        }

        return $task->is_public
            || $task->created_by === $user->id
            || $task->assignees->contains('id', $user->id)
            || $task->followers->contains('id', $user->id);
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
