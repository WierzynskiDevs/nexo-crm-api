<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Event as CalendarEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** Ver nota em OpportunityWon sobre execução síncrona dos listeners. */
class EventScheduled
{
    use Dispatchable;

    public function __construct(public readonly CalendarEvent $event) {}
}
