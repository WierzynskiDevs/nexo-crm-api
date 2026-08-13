<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Permission;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Resolve qualquer ability cujo nome seja um slug do catálogo de permissions
 * (ex.: "leads.criar") contra o papel do usuário dentro do tenant atual —
 * sem precisar declarar cada permissão individualmente via Gate::define().
 *
 * Abilities que não correspondem a um slug conhecido retornam null (deixam
 * a resolução padrão do Gate seguir, ex.: Policies de recurso da Fase 6).
 */
class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability) {
            $tenant = app(TenantContext::class)->get();

            if (! $tenant) {
                return null;
            }

            // A checagem de slug vem ANTES do atalho de super_admin de
            // propósito. Um Gate::before que devolve true para qualquer
            // ability curto-circuitaria as Policies de recurso inteiras —
            // inclusive o belongsToCurrentTenant() delas. Como Membership e
            // AuditLog não têm TenantScope, essa checagem é a única barreira
            // de tenant nesses recursos, e pulá-la deixaria um super_admin
            // alcançar registros de outros tenants por ID direto.
            //
            // Restringindo o atalho aos slugs de permissão, o super_admin
            // continua tendo todas as permissões (as Policies chamam
            // $user->can('modulo.acao'), que cai aqui), mas a Policy roda e
            // a checagem de tenant permanece de pé.
            if (! Permission::query()->where('slug', $ability)->exists()) {
                return null;
            }

            if ($user->hasRole('super_admin', $tenant)) {
                return true;
            }

            return $user->hasPermission($ability, $tenant);
        });
    }
}
