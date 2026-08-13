<?php

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // CACHE_STORE=array é um singleton por processo: sem isso, os
        // limiters de "throttle" acumulam hits entre testes (mesmo IP) e
        // passam a devolver 429 em testes que nada têm a ver com rate limit.
        Cache::flush();
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function memberOf(User $user, Tenant $tenant, string $roleSlug): Membership
{
    return Membership::query()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => Role::query()->where('slug', $roleSlug)->firstOrFail()->id,
        'status' => MembershipStatus::Active,
        'joined_at' => now(),
    ]);
}

/**
 * Cria tenant + usuário + membership ativa e emite um access token completo
 * (com claims de tenant/role), pronto para autenticar chamadas HTTP nos
 * testes de recursos de domínio. Requer que RolePermissionSeeder já tenha
 * rodado no teste (roles/permissions precisam existir).
 *
 * @return array{token: string, tenant: Tenant, user: User}
 */
function actingAsTenantUser(string $roleSlug = 'admin'): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    memberOf($user, $tenant, $roleSlug);

    $role = Role::query()->where('slug', $roleSlug)->firstOrFail();
    $token = app(TokenService::class)->issueAccessToken($user, $tenant, $role);

    return ['token' => $token, 'tenant' => $tenant, 'user' => $user];
}
