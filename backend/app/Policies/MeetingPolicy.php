<?php

namespace App\Policies;

use App\Enums\Action;
use App\Enums\Module;
use App\Models\Meeting;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceAccess;

class MeetingPolicy
{
    use AuthorizesWorkspaceAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Module::Meetings, Action::View);
    }

    /** As with tasks, involvement is checked so an id cannot be guessed. */
    public function view(User $user, Meeting $meeting): bool
    {
        if (! $this->allowsInWorkspace($user, $meeting->owner_id, Module::Meetings, Action::View)) {
            return false;
        }

        if ($user->hasPermission(Module::Meetings, Action::ViewAll)) {
            return true;
        }

        return in_array($user->id, [$meeting->host_id, $meeting->organizer_id], strict: true)
            || $meeting->participants()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Module::Meetings, Action::Create);
    }

    /** The host and organiser can always manage their own meeting. */
    public function update(User $user, Meeting $meeting): bool
    {
        if ($meeting->owner_id !== $user->workspaceOwnerId()) {
            return false;
        }

        return in_array($user->id, [$meeting->host_id, $meeting->organizer_id], strict: true)
            || $user->hasPermission(Module::Meetings, Action::Edit);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        return $this->allowsInWorkspace($user, $meeting->owner_id, Module::Meetings, Action::Delete);
    }

    public function invite(User $user, Meeting $meeting): bool
    {
        if ($meeting->owner_id !== $user->workspaceOwnerId()) {
            return false;
        }

        return in_array($user->id, [$meeting->host_id, $meeting->organizer_id], strict: true)
            || $user->hasPermission(Module::Meetings, Action::Assign);
    }

    /** Anyone who can see the meeting may answer for themselves. */
    public function respond(User $user, Meeting $meeting): bool
    {
        return $this->allowsInWorkspace($user, $meeting->owner_id, Module::Meetings, Action::View);
    }
}
