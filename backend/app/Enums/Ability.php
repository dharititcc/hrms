<?php

namespace App\Enums;

/**
 * Granular abilities checked by policies. Kept as an enum rather than database
 * rows because the set is fixed by the product, not configured per install.
 */
enum Ability: string
{
    case View = 'view';
    case Create = 'create';
    case Edit = 'edit';
    case Delete = 'delete';
    case Assign = 'assign';
    case Comment = 'comment';
    case Upload = 'upload';
    case Export = 'export';
    case ManageAll = 'manage-all';
}
