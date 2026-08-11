<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('grants a permission that was seeded for the role', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    memberOf($user, $tenant, 'sales');
    app(TenantContext::class)->set($tenant);

    expect($user->can('leads.criar'))->toBeTrue();
});

it('denies a permission that was not seeded for the role', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    memberOf($user, $tenant, 'sales');
    app(TenantContext::class)->set($tenant);

    expect($user->can('leads.excluir'))->toBeFalse();
    expect($user->can('empresas.visualizar'))->toBeFalse();
});

it('lets super_admin bypass every permission, including tenant-admin-only modules', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    memberOf($user, $tenant, 'super_admin');
    app(TenantContext::class)->set($tenant);

    expect($user->can('empresas.configurar'))->toBeTrue();
    expect($user->can('logs.excluir'))->toBeTrue();
});

it('denies admin access to the Empresas module, unlike super_admin', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    memberOf($user, $tenant, 'admin');
    app(TenantContext::class)->set($tenant);

    expect($user->can('empresas.visualizar'))->toBeFalse();
    expect($user->can('leads.configurar'))->toBeTrue();
});

it('denies every permission when no tenant is resolved', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    memberOf($user, $tenant, 'super_admin');
    // Nenhum TenantContext::set() foi chamado.

    expect($user->can('leads.visualizar'))->toBeFalse();
});

it('denies permissions for a tenant the user does not belong to', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $user = User::factory()->create();
    memberOf($user, $tenantA, 'admin');
    app(TenantContext::class)->set($tenantB);

    expect($user->can('leads.visualizar'))->toBeFalse();
});
