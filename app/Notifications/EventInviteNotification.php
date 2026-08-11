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

/** Ver nota sobre escalares em OpportunityWonNotification. */
class EventInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tenantId,
        private readonly string $eventId,
        private readonly string $title,
        private readonly string $startsAt,
        private readonly ?string $location,
        private readonly ?string $organizerName,
    ) {}

    public function via(object $notifiable): array
    {
        return [NexoDatabaseChannel::class, 'mail'];
    }

    public function toNexoDatabase(object $notifiable): NexoDatabaseMessage
    {
        return new NexoDatabaseMessage(
            tenantId: $this->tenantId,
            type: NotificationType::EventInvited,
            data: [
                'event_id' => $this->eventId,
                'title' => $this->title,
                'starts_at' => $this->startsAt,
                'location' => $this->location,
                'organizer_name' => $this->organizerName,
            ],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Convite: {$this->title}")
            ->line("Você foi convidado para \"{$this->title}\".")
            ->line('Início: '.$this->startsAt.'.');

        if ($this->location !== null) {
            $mail->line('Local: '.$this->location.'.');
        }

        return $mail->action('Ver na agenda', sprintf(
            '%s/agenda/%s',
            rtrim((string) config('app.frontend_url'), '/'),
            $this->eventId,
        ));
    }
}
