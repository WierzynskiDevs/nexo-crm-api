<?php

declare(strict_types=1);

namespace App\Models\Scopes;

use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restringe toda query de um model de domínio ao tenant autenticado da
 * requisição atual.
 *
 * Deliberadamente NÃO faz exceção para `runningInConsole()`: os testes
 * automatizados (Pest/PHPUnit) também rodam via CLI, então uma exceção por
 * console tornaria os testes de isolamento inúteis (a regra "desligaria"
 * exatamente onde ela precisa ser provada). Ferramentas internas confiáveis
 * que legitimamente precisam de acesso cross-tenant (comandos artisan,
 * jobs administrativos) devem usar o escape hatch nativo do Eloquent:
 * `Model::withoutGlobalScope(TenantScope::class)`.
 *
 * Se nenhum tenant foi resolvido (ex.: token sem claim de tenant, ou nenhum
 * contexto setado), a query falha fechada: não retorna nenhuma linha, em vez
 * de arriscar expor dados de todos os tenants.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn('tenant_id'), $tenantId);
    }
}
