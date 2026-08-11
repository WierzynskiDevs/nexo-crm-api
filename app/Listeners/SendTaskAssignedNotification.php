<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\MembershipStatus;
use App\Events\TaskAssigned;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\TaskAssignedNotification;
use Illuminate\Support\Facades\Auth;

class SendTaskAssignedNotification
{
    public function handle(TaskAssigned $event): void
    {
        $task = $event->task;
        $actor = Auth::guard('api')->user();

        // Ninguém precisa ser avisado de tarefa que atribuiu a si mesmo.
        if ($actor !== null && $actor->id === $event->assigneeId) {
            return;
        }

        // O responsável precisa ser membro ativo DESTE tenant: sem essa
        // checagem, um owner_id apontando para usuário de fora vazaria o
        // título da tarefa por e-mail.
        $isMember = Membership::query()
            ->where('tenant_id', $task->tenant_id)
            ->where('user_id', $event->assigneeId)
            ->where('status', MembershipStatus::Active)
            ->exists();

        if (! $isMember) {
            return;
        }

        $assignee = User::query()->find($event->assigneeId);

        $assignee?->notify(new TaskAssignedNotification(
            tenantId: $task->tenant_id,
            taskId: $task->id,
            taskTitle: $task->title,
            dueAt: $task->due_at?->toIso8601String(),
            assignedByName: $actor?->name,
        ));
    }
}
