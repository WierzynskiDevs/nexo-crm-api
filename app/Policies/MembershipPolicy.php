<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Membership;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

/**
 * Membership não tem TenantScope automática (precisa ser consultável antes
 * do tenant ser resolvido, durante o login) — a revalidação manual aqui é a
 * única barreira, não apenas uma camada extra de defesa.
 */
class MembershipPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $user->can('usuarios.visualizar');
    }

    public function view(User $user, Membership $membership): bool
    {
        return $this->belongsToCurrentTenant($membership) && $user->can('usuarios.visualizar');
    }

    public function create(User $user): bool
    {
        return $user->can('usuarios.criar');
    }

    public function update(User $user, Membership $membership): bool
    {
        return $this->belongsToCurrentTenant($membership) && $user->can('usuarios.editar');
    }

    public function delete(User $user, Membership $membership): bool
    {
        return $this->belongsToCurrentTenant($membership) && $user->can('usuarios.excluir');
    }
}
