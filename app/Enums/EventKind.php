<?php

declare(strict_types=1);

namespace App\Enums;

enum EventKind: string
{
    case Meeting = 'meeting';
    case Demo = 'demo';
    case Call = 'call';
    case Internal = 'internal';
    case Client = 'client';
}
