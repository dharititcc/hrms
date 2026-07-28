<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Attachment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceAccess;

class AttachmentPolicy
{
    use AuthorizesWorkspaceAccess;

    public function view(User $user, Attachment $attachment): bool
    {
        return $this->allowsInWorkspace($user, $attachment->owner_id, Ability::View);
    }

    public function create(User $user): bool
    {
        return $user->hasAbility(Ability::Upload);
    }

    /** Uploaders may remove their own file; otherwise the delete ability is required. */
    public function delete(User $user, Attachment $attachment): bool
    {
        if ($attachment->owner_id !== $user->workspaceOwnerId()) {
            return false;
        }

        return $attachment->uploaded_by === $user->id || $user->hasAbility(Ability::Delete);
    }
}
