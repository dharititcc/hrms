<?php

namespace App\Policies;

use App\Models\ProjectTask;
use App\Models\User;

class ProjectTaskPolicy
{
    public function view(User $user, ProjectTask $task): bool
    {
        return $task->owner_id === $user->id;
    }

    public function update(User $user, ProjectTask $task): bool
    {
        return $task->owner_id === $user->id;
    }

    public function delete(User $user, ProjectTask $task): bool
    {
        return $task->owner_id === $user->id;
    }
}
