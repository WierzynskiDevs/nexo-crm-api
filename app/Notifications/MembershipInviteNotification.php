<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MembershipInviteNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // O tenant vai no link porque o token em si (broker de senha) não
        // carrega empresa nenhuma: sem ele, aceitar um convite ativaria as
        // memberships pendentes do usuário em TODAS as empresas que o
        // convidaram, inclusive as que ele nunca quis entrar.
        $url = sprintf(
            '%s/accept-invite?token=%s&email=%s&tenant_id=%s',
            rtrim((string) config('app.frontend_url'), '/'),
            $this->token,
            urlencode($notifiable->getEmailForPasswordReset()),
            $this->tenant->id,
        );

        return (new MailMessage)
            ->subject("Você foi convidado para o {$this->tenant->name} no Nexo CRM")
            ->line("Você foi convidado para colaborar em {$this->tenant->name} no Nexo CRM.")
            ->action('Aceitar convite', $url)
            ->line('Se você não esperava este convite, pode ignorar este e-mail.');
    }
}
