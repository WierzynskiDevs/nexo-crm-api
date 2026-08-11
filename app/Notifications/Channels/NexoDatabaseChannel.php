<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\Notification as NotificationModel;
use App\Notifications\Messages\NexoDatabaseMessage;
use Illuminate\Notifications\Notification;
use RuntimeException;

/**
 * Canal de notificação in-app: grava na tabela própria `notifications`
 * (multi-tenant, com notifiable polimórfico e read_at), que é a fonte do
 * sino da UI.
 *
 * Não usamos o canal "database" nativo do Laravel de propósito: ele espera o
 * schema dele (notifications.type = FQCN da classe, sem tenant_id) e não
 * conhece o isolamento por tenant do produto. Manter o canal próprio deixa
 * um mesmo evento de negócio alimentar sino e e-mail sem duplicar lógica.
 */
class NexoDatabaseChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toNexoDatabase')) {
            throw new RuntimeException(
                $notification::class.' precisa implementar toNexoDatabase() para usar o canal in-app.',
            );
        }

        $message = $notification->toNexoDatabase($notifiable);

        if (! $message instanceof NexoDatabaseMessage) {
            throw new RuntimeException(
                $notification::class.'::toNexoDatabase() precisa devolver um NexoDatabaseMessage.',
            );
        }

        // tenant_id explícito: o worker não tem TenantContext resolvido. O
        // insert não é afetado pela TenantScope (global scopes valem para
        // select/update/delete, não para insert).
        NotificationModel::query()->create([
            'tenant_id' => $message->tenantId,
            'type' => $message->type->value,
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'data' => $message->data,
        ]);
    }
}
