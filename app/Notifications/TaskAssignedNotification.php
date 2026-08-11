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
class TaskAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tenantId,
        private readonly string $taskId,
        private readonly string $taskTitle,
        private readonly ?string $dueAt,
        private readonly ?string $assignedByName,
    ) {}

    public function via(object $notifiable): array
    {
        return [NexoDatabaseChannel::class, 'mail'];
    }

    public function toNexoDatabase(object $notifiable): NexoDatabaseMessage
    {
        return new NexoDatabaseMessage(
            tenantId: $this->tenantId,
            type: NotificationType::TaskAssigned,
            data: [
                'task_id' => $this->taskId,
                'task_title' => $this->taskTitle,
                'due_at' => $this->dueAt,
                'assigned_by_name' => $this->assignedByName,
            ],
        );
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject("Nova tarefa atribuída a você: {$this->taskTitle}")
            ->line("A tarefa \"{$this->taskTitle}\" foi atribuída a você no Nexo CRM.");

        if ($this->dueAt !== null) {
            $mail->line('Prazo: '.$this->dueAt.'.');
        }

        return $mail->action('Ver tarefa', sprintf(
            '%s/tarefas/%s',
            rtrim((string) config('app.frontend_url'), '/'),
            $this->taskId,
        ));
    }
}
