<?php

namespace App\Policies;

use App\Enums\Action;
use App\Enums\Module;
use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceAccess;

class ProjectPolicy
{
    use AuthorizesWorkspaceAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Module::Projects, Action::View);
    }

    public function view(User $user, Project $project): bool
    {
        return $this->allowsInWorkspace($user, $project->owner_id, Module::Projects, Action::View);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Module::Projects, Action::Create);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->allowsInWorkspace($user, $project->owner_id, Module::Projects, Action::Edit);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->allowsInWorkspace($user, $project->owner_id, Module::Projects, Action::Delete);
    }
}
