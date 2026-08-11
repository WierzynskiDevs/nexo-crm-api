<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

/**
 * "Equipes" não tem módulo de permissão próprio no catálogo (o protótipo
 * também nunca teve) — fica sob "Usuários", por ser gestão de pessoas/
 * estrutura organizacional, não um recurso de CRM per se.
 */
class TeamPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $user->can('usuarios.visualizar');
    }

    public function view(User $user, Team $team): bool
    {
        return $this->belongsToCurrentTenant($team) && $user->can('usuarios.visualizar');
    }

    public function create(User $user): bool
    {
        return $user->can('usuarios.criar');
    }

    public function update(User $user, Team $team): bool
    {
        return $this->belongsToCurrentTenant($team) && $user->can('usuarios.editar');
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->belongsToCurrentTenant($team) && $user->can('usuarios.excluir');
    }
}
