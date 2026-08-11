<?php

declare(strict_types=1);

namespace App\Enums;

enum ClientSegment: string
{
    case Enterprise = 'enterprise';
    case MidMarket = 'mid_market';
    case Smb = 'smb';
}
