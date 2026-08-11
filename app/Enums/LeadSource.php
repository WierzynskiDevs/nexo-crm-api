<?php

declare(strict_types=1);

namespace App\Enums;

enum LeadSource: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case Referral = 'referral';
    case Event = 'event';
    case Ads = 'ads';
}
