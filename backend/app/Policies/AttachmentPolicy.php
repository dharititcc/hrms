<?php

namespace App\Policies;

use App\Enums\Action;
use App\Enums\Module;
use App\Models\Attachment;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceAccess;

class AttachmentPolicy
{
    use AuthorizesWorkspaceAccess;

    public function view(User $user, Attachment $attachment): bool
    {
        return $this->allowsInWorkspace($user, $attachment->owner_id, Module::Attachments, Action::View);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Module::Attachments, Action::Upload);
    }

    /** Uploaders may remove their own file; otherwise delete is required. */
    public function delete(User $user, Attachment $attachment): bool
    {
        if ($attachment->owner_id !== $user->workspaceOwnerId()) {
            return false;
        }

        return $attachment->uploaded_by === $user->id
            || $user->hasPermission(Module::Attachments, Action::Delete);
    }
}
