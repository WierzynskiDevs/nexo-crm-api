<?php

declare(strict_types=1);

use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('creates a team with initial members', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');
    // Precisam ser membros ativos do tenant: desde a Fase 12, referenciar um
    // usuário de fora da empresa é rejeitado na validação.
    $memberA = User::factory()->create();
    $memberB = User::factory()->create();
    memberOf($memberA, $tenant, 'sales');
    memberOf($memberB, $tenant, 'sales');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/teams', [
            'name' => 'Squad Enterprise',
            'member_ids' => [$memberA->id, $memberB->id],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Squad Enterprise')
        ->assertJsonCount(2, 'data.members');
});

it('attaches and detaches a team member', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');
    $team = Team::factory()->create(['tenant_id' => $tenant->id]);
    $user = User::factory()->create();
    memberOf($user, $tenant, 'sales');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/teams/{$team->id}/members", ['user_id' => $user->id])
        ->assertOk()
        ->assertJsonCount(1, 'data.members');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/teams/{$team->id}/members/{$user->id}")
        ->assertStatus(204);

    expect($team->members()->count())->toBe(0);
});

it('returns 404 for a team belonging to another tenant', function () {
    ['token' => $token] = actingAsTenantUser('admin');
    $foreignTeam = Team::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/teams/{$foreignTeam->id}")
        ->assertStatus(404);
});
