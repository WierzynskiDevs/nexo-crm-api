<?php

declare(strict_types=1);

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('logs in a user with a single active membership and issues a full session', function () {
    $user = User::factory()->create(['password' => Hash::make('Senha123!')]);
    $tenant = Tenant::factory()->create();
    $role = Role::query()->where('slug', 'sales')->first();

    Membership::query()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
        'status' => MembershipStatus::Active,
        'joined_at' => now(),
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Senha123!',
    ])->assertOk()
        ->assertJsonPath('data.role', 'sales')
        ->assertJsonPath('data.tenant.id', $tenant->id)
        ->assertCookie('refresh_token');
});

it('rejects login with a wrong password using a generic message', function () {
    $user = User::factory()->create(['password' => Hash::make('Senha123!')]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'errada',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('rejects login for a user without any active membership', function () {
    $user = User::factory()->create(['password' => Hash::make('Senha123!')]);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Senha123!',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('returns a tenant selection challenge for a user with multiple active memberships', function () {
    $user = User::factory()->create(['password' => Hash::make('Senha123!')]);
    $role = Role::query()->where('slug', 'sales')->first();

    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    foreach ([$tenantA, $tenantB] as $tenant) {
        Membership::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'Senha123!',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.requires_tenant_selection', true)
        ->assertJsonCount(2, 'data.tenants')
        ->assertCookieMissing('refresh_token');
});
