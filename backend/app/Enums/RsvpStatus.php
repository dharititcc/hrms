<?php

namespace App\Enums;

enum RsvpStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Tentative = 'tentative';
}
