<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Defesa em profundidade: a TenantScope já impede que um recurso de outro
 * tenant seja carregado (404 antes mesmo da Policy rodar), mas a Policy
 * revalida explicitamente — nunca confiar em uma única camada.
 */
trait AuthorizesTenantResource
{
    protected function belongsToCurrentTenant(Model $model): bool
    {
        $tenantId = app(TenantContext::class)->id();

        return $tenantId !== null && $model->tenant_id === $tenantId;
    }
}
