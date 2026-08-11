<?php

declare(strict_types=1);

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('resolves the tenant from the JWT claim and exposes it on /me', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $role = Role::query()->where('slug', 'manager')->first();

    Membership::query()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
        'status' => MembershipStatus::Active,
        'joined_at' => now(),
    ]);

    $token = app(TokenService::class)->issueAccessToken($user, $tenant, $role);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.tenant.id', $tenant->id)
        ->assertJsonPath('data.role', 'manager');
});

it('rejects the request when the membership was revoked after the token was issued', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $role = Role::query()->where('slug', 'manager')->first();

    $membership = Membership::query()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
        'status' => MembershipStatus::Active,
        'joined_at' => now(),
    ]);

    $token = app(TokenService::class)->issueAccessToken($user, $tenant, $role);

    // O token já foi emitido com o claim de tenant; a membership é revogada depois.
    $membership->update(['status' => MembershipStatus::Inactive]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertStatus(403);
});

it('leaves the tenant context empty when the token has no tenant claim', function () {
    $user = User::factory()->create();
    $token = app(TokenService::class)->issueAccessToken($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.tenant', null)
        ->assertJsonPath('data.role', null);
});
