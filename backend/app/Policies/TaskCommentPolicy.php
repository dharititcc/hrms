<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\TaskComment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceAccess;

class TaskCommentPolicy
{
    use AuthorizesWorkspaceAccess;

    /** Comments have no owner_id of their own; they inherit the task's workspace. */
    public function view(User $user, TaskComment $comment): bool
    {
        return $this->allowsInWorkspace($user, $comment->task->owner_id, Ability::View);
    }

    /** Only the author may edit their own words. */
    public function update(User $user, TaskComment $comment): bool
    {
        return $comment->task->owner_id === $user->workspaceOwnerId()
            && $comment->user_id === $user->id;
    }

    /** Authors can remove their own comment; moderators need the delete ability. */
    public function delete(User $user, TaskComment $comment): bool
    {
        if ($comment->task->owner_id !== $user->workspaceOwnerId()) {
            return false;
        }

        return $comment->user_id === $user->id || $user->hasAbility(Ability::Delete);
    }
}
