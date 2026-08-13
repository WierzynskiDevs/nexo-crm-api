<?php

declare(strict_types=1);

use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\StageTransition;
use App\Models\Tenant;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function pipelineWithStages(string $tenantId): Pipeline
{
    $pipeline = Pipeline::factory()->create(['tenant_id' => $tenantId]);
    PipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'position' => 0]);
    PipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'position' => 1, 'is_won' => true]);
    PipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'position' => 2, 'is_lost' => true]);

    return $pipeline->load('stages');
}

it('creates an opportunity and records the initial stage transition', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('sales');
    $pipeline = pipelineWithStages($tenant->id);
    $firstStage = $pipeline->stages->first();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/opportunities', [
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $firstStage->id,
            'name' => 'Negócio com a Acme',
            'value_cents' => 500000,
        ]);

    $response->assertCreated()->assertJsonPath('data.status', 'open');

    $opportunityId = $response->json('data.id');
    expect(StageTransition::query()->where('opportunity_id', $opportunityId)->count())->toBe(1);
});

it('rejects referencing a pipeline_stage_id that does not belong to the given pipeline', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('sales');
    $pipelineA = pipelineWithStages($tenant->id);
    $pipelineB = pipelineWithStages($tenant->id);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/opportunities', [
            'pipeline_id' => $pipelineA->id,
            'pipeline_stage_id' => $pipelineB->stages->first()->id,
            'name' => 'Inválido',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('pipeline_stage_id');
});

it('moves an opportunity to the won stage and marks it closed', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('sales');
    $pipeline = pipelineWithStages($tenant->id);
    $opportunity = Opportunity::factory()->create([
        'tenant_id' => $tenant->id,
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $pipeline->stages->first()->id,
    ]);
    $wonStage = $pipeline->stages->firstWhere('is_won', true);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/opportunities/{$opportunity->id}/stage", ['pipeline_stage_id' => $wonStage->id])
        ->assertOk()
        ->assertJsonPath('data.status', 'won')
        ->assertJsonPath('data.pipeline_stage_id', $wonStage->id);

    expect($opportunity->refresh()->closed_at)->not->toBeNull();
    expect(StageTransition::query()->where('opportunity_id', $opportunity->id)->count())->toBe(1);
});

it('rejects moving an opportunity to a stage of a different pipeline', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('sales');
    $pipelineA = pipelineWithStages($tenant->id);
    $pipelineB = pipelineWithStages($tenant->id);
    $opportunity = Opportunity::factory()->create([
        'tenant_id' => $tenant->id,
        'pipeline_id' => $pipelineA->id,
        'pipeline_stage_id' => $pipelineA->stages->first()->id,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/opportunities/{$opportunity->id}/stage", [
            'pipeline_stage_id' => $pipelineB->stages->first()->id,
        ])
        ->assertStatus(422);
});

it('returns 404 for an opportunity belonging to another tenant', function () {
    ['token' => $token] = actingAsTenantUser('sales');
    $foreignTenant = Tenant::factory()->create();
    $foreignPipeline = pipelineWithStages($foreignTenant->id);
    $foreignOpportunity = Opportunity::factory()->create([
        'tenant_id' => $foreignTenant->id,
        'pipeline_id' => $foreignPipeline->id,
        'pipeline_stage_id' => $foreignPipeline->stages->first()->id,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/opportunities/{$foreignOpportunity->id}")
        ->assertStatus(404);
});
