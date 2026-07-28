<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceAccess;

class ProjectPolicy
{
    use AuthorizesWorkspaceAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasAbility(Ability::View);
    }

    public function view(User $user, Project $project): bool
    {
        return $this->allowsInWorkspace($user, $project->owner_id, Ability::View);
    }

    public function create(User $user): bool
    {
        return $user->hasAbility(Ability::Create);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->allowsInWorkspace($user, $project->owner_id, Ability::Edit);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->allowsInWorkspace($user, $project->owner_id, Ability::Delete);
    }
}
