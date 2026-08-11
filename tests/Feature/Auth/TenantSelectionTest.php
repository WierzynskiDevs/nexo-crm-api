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

it('issues a full session when selecting a tenant the user belongs to', function () {
    $user = User::factory()->create();
    $role = Role::query()->where('slug', 'manager')->first();
    $tenant = Tenant::factory()->create();

    Membership::query()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
        'status' => MembershipStatus::Active,
        'joined_at' => now(),
    ]);

    $selectionToken = app(TokenService::class)->issueAccessToken($user);

    $this->withHeader('Authorization', "Bearer {$selectionToken}")
        ->postJson('/api/v1/auth/select-tenant', ['tenant_id' => $tenant->id])
        ->assertOk()
        ->assertJsonPath('data.role', 'manager')
        ->assertJsonPath('data.tenant.id', $tenant->id)
        ->assertCookie('refresh_token');
});

it('rejects tenant selection for a tenant the user does not belong to', function () {
    $user = User::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $token = app(TokenService::class)->issueAccessToken($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/select-tenant', ['tenant_id' => $otherTenant->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('tenant_id');
});

it('rejects tenant selection without authentication', function () {
    $tenant = Tenant::factory()->create();

    $this->postJson('/api/v1/auth/select-tenant', ['tenant_id' => $tenant->id])
        ->assertStatus(401);
});
