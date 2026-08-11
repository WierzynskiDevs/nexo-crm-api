<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Client;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class ClientPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $user->can('clientes.visualizar');
    }

    public function view(User $user, Client $client): bool
    {
        return $this->belongsToCurrentTenant($client) && $user->can('clientes.visualizar');
    }

    public function create(User $user): bool
    {
        return $user->can('clientes.criar');
    }

    public function update(User $user, Client $client): bool
    {
        return $this->belongsToCurrentTenant($client) && $user->can('clientes.editar');
    }

    public function delete(User $user, Client $client): bool
    {
        return $this->belongsToCurrentTenant($client) && $user->can('clientes.excluir');
    }
}
