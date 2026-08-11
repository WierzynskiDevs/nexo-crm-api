<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Pipeline;
use App\Models\User;
use App\Policies\Concerns\AuthorizesTenantResource;

class PipelinePolicy
{
    use AuthorizesTenantResource;

    public function viewAny(User $user): bool
    {
        return $user->can('pipeline.visualizar');
    }

    public function view(User $user, Pipeline $pipeline): bool
    {
        return $this->belongsToCurrentTenant($pipeline) && $user->can('pipeline.visualizar');
    }

    public function create(User $user): bool
    {
        return $user->can('pipeline.criar');
    }

    public function update(User $user, Pipeline $pipeline): bool
    {
        return $this->belongsToCurrentTenant($pipeline) && $user->can('pipeline.editar');
    }

    public function delete(User $user, Pipeline $pipeline): bool
    {
        return $this->belongsToCurrentTenant($pipeline) && $user->can('pipeline.excluir');
    }
}
