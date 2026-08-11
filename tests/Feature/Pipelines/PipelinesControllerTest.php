<?php

declare(strict_types=1);

use App\Models\Pipeline;
use App\Models\Tenant;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('creates a pipeline with default stages', function () {
    ['token' => $token] = actingAsTenantUser('admin');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/pipelines', [
            'name' => 'Comercial',
            'stages' => ['Novo lead', 'Qualificação', 'Fechado', 'Perdido'],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Comercial')
        ->assertJsonCount(4, 'data.stages')
        ->assertJsonPath('data.stages.2.is_won', true)
        ->assertJsonPath('data.stages.3.is_lost', true);
});

it('adds, updates and reorders stages of a pipeline', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');
    $pipeline = Pipeline::factory()->create(['tenant_id' => $tenant->id]);
    $stage1 = \App\Models\PipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'position' => 0]);
    $stage2 = \App\Models\PipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'position' => 1]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/pipelines/{$pipeline->id}/stages", ['name' => 'Nova etapa'])
        ->assertCreated();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/pipelines/{$pipeline->id}/stages/{$stage1->id}", ['name' => 'Renomeada'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renomeada');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/pipelines/{$pipeline->id}/stages/reorder", [
            'stage_ids' => [$stage2->id, $stage1->id],
        ])
        ->assertOk();

    expect($stage2->refresh()->position)->toBe(0);
    expect($stage1->refresh()->position)->toBe(1);
});

it('rejects modifying a stage of a pipeline belonging to another tenant', function () {
    ['token' => $token] = actingAsTenantUser('admin');
    $foreignPipeline = Pipeline::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/pipelines/{$foreignPipeline->id}/stages", ['name' => 'Invasão'])
        ->assertStatus(404);
});
