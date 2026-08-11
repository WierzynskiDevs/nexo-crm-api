<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class EventPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $user->can('agenda.visualizar');
    }

    public function view(User $user, Event $event): bool
    {
        return $this->belongsToCurrentTenant($event) && $user->can('agenda.visualizar');
    }

    public function create(User $user): bool
    {
        return $user->can('agenda.criar');
    }

    public function update(User $user, Event $event): bool
    {
        return $this->belongsToCurrentTenant($event) && $user->can('agenda.editar');
    }

    public function delete(User $user, Event $event): bool
    {
        return $this->belongsToCurrentTenant($event) && $user->can('agenda.excluir');
    }
}
