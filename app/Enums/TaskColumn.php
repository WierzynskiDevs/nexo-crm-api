<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskColumn: string
{
    case Backlog = 'backlog';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Done = 'done';
}
