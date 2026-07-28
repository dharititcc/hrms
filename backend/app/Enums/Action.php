<?php

namespace App\Enums;

enum Action: string
{
    case View = 'view';
    case Create = 'create';
    case Edit = 'edit';
    case Delete = 'delete';
    /** Give work to, or grant access to, somebody else. */
    case Assign = 'assign';
    /** Decide on a request: leave, expenses. Distinct from editing it. */
    case Approve = 'approve';
    case Comment = 'comment';
    case Upload = 'upload';
    case Export = 'export';
}
