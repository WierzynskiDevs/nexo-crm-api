<?php

declare(strict_types=1);

namespace App\Enums;

enum MembershipStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Inactive = 'inactive';
}
