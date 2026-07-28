<?php

namespace App\Support;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who can act inside a workspace: the owner, plus any staff member who has
 * accepted an invitation and therefore has a login account.
 *
 * Single source of truth for assignee validation, mention resolution and the
 * assignable-users endpoint, so those three can never disagree.
 */
final class WorkspaceUsers
{
    /** @return list<int> */
    public static function idsFor(int $ownerId): array
    {
        return Staff::query()
            ->where('owner_id', $ownerId)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->push($ownerId)
            ->unique()
            ->values()
            ->all();
    }

    public static function query(int $ownerId): Builder
    {
        return User::query()->whereIn('id', self::idsFor($ownerId));
    }
}
