<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Meeting;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceAccess;

class MeetingPolicy
{
    use AuthorizesWorkspaceAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasAbility(Ability::View);
    }

    public function view(User $user, Meeting $meeting): bool
    {
        return $this->allowsInWorkspace($user, $meeting->owner_id, Ability::View);
    }

    public function create(User $user): bool
    {
        return $user->hasAbility(Ability::Create);
    }

    /** The host and organiser can always manage their own meeting. */
    public function update(User $user, Meeting $meeting): bool
    {
        if ($meeting->owner_id !== $user->workspaceOwnerId()) {
            return false;
        }

        return in_array($user->id, [$meeting->host_id, $meeting->organizer_id], strict: true)
            || $user->hasAbility(Ability::Edit);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $this->allowsInWorkspace($user, $meeting->owner_id, Ability::Delete);
    }

    /** Inviting someone to a meeting is an assignment-shaped action. */
    public function invite(User $user, Meeting $meeting): bool
    {
        if ($meeting->owner_id !== $user->workspaceOwnerId()) {
            return false;
        }

        return in_array($user->id, [$meeting->host_id, $meeting->organizer_id], strict: true)
            || $user->hasAbility(Ability::Assign);
    }

    /** Anyone in the workspace who can see the meeting may answer for themselves. */
    public function respond(User $user, Meeting $meeting): bool
    {
        return $this->allowsInWorkspace($user, $meeting->owner_id, Ability::View);
    }
}
