<?php

declare(strict_types=1);

namespace App\Notifications\Messages;

use App\Enums\NotificationType;

/**
 * Payload de uma notificação in-app (sino da UI).
 *
 * O tenant vem explícito e não do TenantContext: quando a notificação é
 * entregue, ela já está rodando no worker de fila, onde não existe
 * requisição autenticada nem contexto de tenant resolvido.
 */
final readonly class NexoDatabaseMessage
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $tenantId,
        public NotificationType $type,
        public array $data,
    ) {}
}
