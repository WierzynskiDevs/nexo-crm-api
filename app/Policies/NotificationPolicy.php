<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

/**
 * Notificação é pessoal: mesmo dentro do mesmo tenant, um usuário nunca
 * acessa a notificação de outro. A TenantScope garante o isolamento entre
 * tenants; a checagem de destinatário aqui é o que separa usuários DENTRO
 * do mesmo tenant — não existe permissão de RBAC que dê acesso à caixa
 * alheia.
 */
class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Notification $notification): bool
    {
        return $this->belongsTo($user, $notification);
    }

    public function update(User $user, Notification $notification): bool
    {
        return $this->belongsTo($user, $notification);
    }

    public function delete(User $user, Notification $notification): bool
    {
        return $this->belongsTo($user, $notification);
    }

    private function belongsTo(User $user, Notification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass()
            && $notification->notifiable_id === $user->getKey();
    }
}
