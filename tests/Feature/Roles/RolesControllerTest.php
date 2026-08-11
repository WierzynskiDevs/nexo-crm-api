<?php

declare(strict_types=1);

use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('lists the real RBAC catalog (roles with their permissions)', function () {
    ['token' => $token] = actingAsTenantUser('admin');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/roles')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(5);

    $superAdmin = collect($response->json('data'))->firstWhere('slug', 'super_admin');
    expect($superAdmin['permissions'])->toHaveCount(60); // 10 modulos x 6 acoes

    $admin = collect($response->json('data'))->firstWhere('slug', 'admin');
    expect($admin['permissions'])->toHaveCount(54); // todos exceto "Empresas"
});
