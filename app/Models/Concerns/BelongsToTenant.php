<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Aplica o isolamento de tenant a um model de domínio: toda query passa a
 * ser automaticamente restrita ao tenant autenticado (TenantScope), e
 * `tenant_id` é preenchido a partir do TenantContext ao criar um registro
 * quando não foi informado explicitamente — reduz o risco de esquecer de
 * setar o tenant manualmente em algum Service/Controller.
 *
 * Nunca aplicar esta trait a models globais (Role, Permission, Plan), a
 * `User` (não pertence a um único tenant) ou a `Membership`/`AuditLog`
 * (precisam de consultas cross-tenant legítimas antes do tenant ser
 * resolvido, ou por natureza administrativa).
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            if (! $model->getAttribute('tenant_id')) {
                $model->setAttribute('tenant_id', app(TenantContext::class)->id());
            }
        });
    }
}
