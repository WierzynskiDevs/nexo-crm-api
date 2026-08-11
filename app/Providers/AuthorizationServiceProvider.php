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
 * Super Admin nunca é bloqueado por essa checagem.
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

            if ($user->hasRole('super_admin', $tenant)) {
                return true;
            }

            if (! Permission::query()->where('slug', $ability)->exists()) {
                return null;
            }

            return $user->hasPermission($ability, $tenant);
        });
    }
}
