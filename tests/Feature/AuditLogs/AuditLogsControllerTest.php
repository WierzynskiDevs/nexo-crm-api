<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('records an audit log entry when a membership is updated and exposes it scoped to the tenant', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');
    $member = User::factory()->create();
    $membership = memberOf($member, $tenant, 'sales');
    $managerRole = Role::query()->where('slug', 'manager')->first();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/members/{$membership->id}", ['role_id' => $managerRole->id])
        ->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/audit-logs')
        ->assertOk()
        ->assertJsonPath('data.0.action', 'member.updated');
});

it('rejects listing audit logs without logs.visualizar permission', function () {
    ['token' => $token] = actingAsTenantUser('sales');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/audit-logs')
        ->assertStatus(403);
});

it('never exposes audit logs from another tenant', function () {
    ['token' => $token] = actingAsTenantUser('admin');

    // Log de um tenant totalmente diferente, criado direto (sem passar pela
    // API) para não depender de autenticar com dois tokens no mesmo teste —
    // o JWTGuard cacheia o usuário resolvido na instância, e trocar de token
    // no meio do teste não força uma nova resolução (é uma peculiaridade dos
    // testes, não existe em produção: cada requisição real tem seu próprio
    // container). O ponto sob teste aqui é só o filtro de tenant da query.
    AuditLog::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'action' => 'member.updated']);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/audit-logs')
        ->assertOk();

    expect($response->json('data'))->toBeEmpty();
});
