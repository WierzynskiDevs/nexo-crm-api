<?php

declare(strict_types=1);

namespace App\Enums;

enum ClientHealth: string
{
    case Healthy = 'healthy';
    case Attention = 'attention';
    case Risk = 'risk';
}
