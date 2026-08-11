<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Notifications\Channels\NexoDatabaseChannel;
use App\Notifications\Messages\NexoDatabaseMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Só escalares no construtor, nunca o model Opportunity: a notificação é
 * enfileirada, e um model com TenantScope não sobrevive à volta da fila —
 * o worker restauraria o registro sem tenant resolvido e o scope (que falha
 * fechado) devolveria "não encontrado".
 */
class OpportunityWonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tenantId,
        private readonly string $opportunityId,
        private readonly string $opportunityName,
        private readonly int $valueCents,
        private readonly ?string $wonByName,
    ) {}

    public function via(object $notifiable): array
    {
        return [NexoDatabaseChannel::class, 'mail'];
    }

    public function toNexoDatabase(object $notifiable): NexoDatabaseMessage
    {
        return new NexoDatabaseMessage(
            tenantId: $this->tenantId,
            type: NotificationType::OpportunityWon,
            data: [
                'opportunity_id' => $this->opportunityId,
                'opportunity_name' => $this->opportunityName,
                'value_cents' => $this->valueCents,
                'won_by_name' => $this->wonByName,
            ],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $value = number_format($this->valueCents / 100, 2, ',', '.');

        return (new MailMessage)
            ->subject("Oportunidade ganha: {$this->opportunityName}")
            ->line("A oportunidade \"{$this->opportunityName}\" foi marcada como ganha.")
            ->line("Valor: R$ {$value}.")
            ->action('Ver oportunidade', sprintf(
                '%s/oportunidades/%s',
                rtrim((string) config('app.frontend_url'), '/'),
                $this->opportunityId,
            ));
    }
}
