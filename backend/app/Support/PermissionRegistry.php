<?php

namespace App\Support;

use App\Enums\Ability;
use App\Enums\WorkspaceRole;

/**
 * Single source of truth for which abilities each workspace role holds.
 *
 * Deliberately a static map rather than a database ACL: the ability set is
 * fixed by the product. If per-user overrides are ever needed, add a pivot and
 * union it with these defaults here — callers do not change.
 */
final class PermissionRegistry
{
    /** @return array<string, list<Ability>> */
    private static function map(): array
    {
        return [
            WorkspaceRole::Admin->value => Ability::cases(),
            WorkspaceRole::Manager->value => [
                Ability::View, Ability::Create, Ability::Edit, Ability::Delete,
                Ability::Assign, Ability::Comment, Ability::Upload, Ability::Export,
            ],
            WorkspaceRole::Employee->value => [
                Ability::View, Ability::Create, Ability::Edit,
                Ability::Comment, Ability::Upload,
            ],
            WorkspaceRole::Client->value => [
                Ability::View, Ability::Comment,
            ],
        ];
    }

    /** @return list<Ability> */
    public static function abilitiesFor(WorkspaceRole $role): array
    {
        return self::map()[$role->value] ?? [];
    }

    public static function allows(WorkspaceRole $role, Ability $ability): bool
    {
        return in_array($ability, self::abilitiesFor($role), strict: true);
    }
}
