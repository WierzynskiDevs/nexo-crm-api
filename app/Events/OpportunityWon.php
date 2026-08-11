<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Opportunity;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Os listeners destes eventos de domínio rodam de forma síncrona, ainda
 * dentro da requisição — é lá que existe TenantContext resolvido para ler os
 * models. O trabalho pesado (envio de e-mail) é que vai para a fila, através
 * das Notifications ShouldQueue, carregando só escalares.
 */
class OpportunityWon
{
    use Dispatchable;

    public function __construct(public readonly Opportunity $opportunity) {}
}
