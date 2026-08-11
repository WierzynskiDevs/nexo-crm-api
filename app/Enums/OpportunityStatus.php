<?php

declare(strict_types=1);

namespace App\Enums;

enum OpportunityStatus: string
{
    case Open = 'open';
    case Won = 'won';
    case Lost = 'lost';
}
