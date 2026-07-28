<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\ProjectTask;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceAccess;

class ProjectTaskPolicy
{
    use AuthorizesWorkspaceAccess;

    public function view(User $user, ProjectTask $task): bool
    {
        return $this->allowsInWorkspace($user, $task->owner_id, Ability::View);
    }

    public function update(User $user, ProjectTask $task): bool
    {
        return $this->allowsInWorkspace($user, $task->owner_id, Ability::Edit);
    }

    public function delete(User $user, ProjectTask $task): bool
    {
        return $this->allowsInWorkspace($user, $task->owner_id, Ability::Delete);
    }
}
