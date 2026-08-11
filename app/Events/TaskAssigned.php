<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Task;
use Illuminate\Foundation\Events\Dispatchable;

/** Ver nota em OpportunityWon sobre execução síncrona dos listeners. */
class TaskAssigned
{
    use Dispatchable;

    public function __construct(
        public readonly Task $task,
        public readonly string $assigneeId,
    ) {}
}
