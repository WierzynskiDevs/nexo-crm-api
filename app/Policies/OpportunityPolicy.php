<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Opportunity;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class OpportunityPolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $user->can('pipeline.visualizar');
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $this->belongsToCurrentTenant($opportunity) && $user->can('pipeline.visualizar');
    }

    public function create(User $user): bool
    {
        return $user->can('pipeline.criar');
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        return $this->belongsToCurrentTenant($opportunity) && $user->can('pipeline.editar');
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $this->belongsToCurrentTenant($opportunity) && $user->can('pipeline.excluir');
    }
}
