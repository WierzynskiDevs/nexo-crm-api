<?php

declare(strict_types=1);

use App\Enums\OpportunityStatus;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Tenant;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('aggregates real kpis for the authenticated tenant', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');

    $pipeline = Pipeline::factory()->create(['tenant_id' => $tenant->id, 'is_default' => true]);
    $stage = PipelineStage::factory()->create(['pipeline_id' => $pipeline->id]);

    // Duas oportunidades ganhas dentro da janela de 30 dias: 5.000 + 3.000.
    Opportunity::factory()->count(2)->sequence(
        ['value_cents' => 500_000, 'closed_at' => now()->subDays(3)],
        ['value_cents' => 300_000, 'closed_at' => now()->subDays(10)],
    )->create([
        'tenant_id' => $tenant->id,
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $stage->id,
        'status' => OpportunityStatus::Won,
    ]);

    // Fora da janela de 30 dias — não pode entrar na receita.
    Opportunity::factory()->create([
        'tenant_id' => $tenant->id,
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $stage->id,
        'status' => OpportunityStatus::Won,
        'value_cents' => 900_000,
        'closed_at' => now()->subDays(200),
    ]);

    Lead::factory()->count(4)->create(['tenant_id' => $tenant->id]);
    Client::factory()->count(3)->create(['tenant_id' => $tenant->id, 'archived_at' => null]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/dashboard?period=30d')
        ->assertOk();

    expect($response->json('data.period'))->toBe('30d')
        ->and($response->json('data.kpis.revenue_cents.value'))->toBe(800_000)
        ->and($response->json('data.kpis.average_ticket_cents.value'))->toBe(400_000)
        ->and($response->json('data.kpis.leads.value'))->toBe(4)
        ->and($response->json('data.kpis.clients.value'))->toBe(3)
        ->and($response->json('data.conversion.won_opportunities'))->toBe(2)
        // toEqual: um float redondo volta do JSON como int (50.0 -> 50).
        ->and($response->json('data.conversion.rate'))->toEqual(50.0); // 2 ganhas / 4 leads
});

it('builds a continuous sales series with empty buckets filled in', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');

    $pipeline = Pipeline::factory()->create(['tenant_id' => $tenant->id, 'is_default' => true]);
    Opportunity::factory()->create([
        'tenant_id' => $tenant->id,
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => PipelineStage::factory()->create(['pipeline_id' => $pipeline->id])->id,
        'status' => OpportunityStatus::Won,
        'value_cents' => 250_000,
        'closed_at' => now()->subDays(2),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/dashboard?period=7d')
        ->assertOk();

    $series = $response->json('data.sales_series');

    // 7 dias corridos, mesmo com venda em um único dia.
    expect($series)->toHaveCount(7)
        ->and(collect($series)->sum('revenue_cents'))->toBe(250_000)
        ->and(collect($series)->where('revenue_cents', 0))->toHaveCount(6);
});

it('builds the funnel from the real pipeline stages', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');

    $pipeline = Pipeline::factory()->create(['tenant_id' => $tenant->id, 'is_default' => true]);
    $qualificacao = PipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'name' => 'Qualificação', 'position' => 0]);
    $proposta = PipelineStage::factory()->create(['pipeline_id' => $pipeline->id, 'name' => 'Proposta', 'position' => 1]);

    Opportunity::factory()->count(3)->create([
        'tenant_id' => $tenant->id,
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $qualificacao->id,
        'value_cents' => 100_000,
    ]);
    Opportunity::factory()->create([
        'tenant_id' => $tenant->id,
        'pipeline_id' => $pipeline->id,
        'pipeline_stage_id' => $proposta->id,
        'value_cents' => 700_000,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/dashboard?period=30d')
        ->assertOk();

    expect($response->json('data.funnel'))->toHaveCount(2)
        ->and($response->json('data.funnel.0.stage'))->toBe('Qualificação')
        ->and($response->json('data.funnel.0.count'))->toBe(3)
        ->and($response->json('data.funnel.0.value_cents'))->toBe(300_000)
        ->and($response->json('data.funnel.1.stage'))->toBe('Proposta')
        ->and($response->json('data.funnel.1.count'))->toBe(1);
});

it('never aggregates data from another tenant', function () {
    ['token' => $token] = actingAsTenantUser('admin');

    $other = Tenant::factory()->create();
    $otherPipeline = Pipeline::factory()->create(['tenant_id' => $other->id, 'is_default' => true]);
    Opportunity::factory()->create([
        'tenant_id' => $other->id,
        'pipeline_id' => $otherPipeline->id,
        'pipeline_stage_id' => PipelineStage::factory()->create(['pipeline_id' => $otherPipeline->id])->id,
        'status' => OpportunityStatus::Won,
        'value_cents' => 5_000_000,
        'closed_at' => now()->subDay(),
    ]);
    Lead::factory()->count(9)->create(['tenant_id' => $other->id]);
    Client::factory()->count(7)->create(['tenant_id' => $other->id]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/dashboard?period=30d')
        ->assertOk();

    expect($response->json('data.kpis.revenue_cents.value'))->toBe(0)
        ->and($response->json('data.kpis.leads.value'))->toBe(0)
        ->and($response->json('data.kpis.clients.value'))->toBe(0)
        // Sem pipeline próprio, o funil vem vazio em vez de cair no do outro tenant.
        ->and($response->json('data.funnel'))->toBeEmpty();
});

it('rejects a pipeline_id belonging to another tenant', function () {
    ['token' => $token] = actingAsTenantUser('admin');

    $otherPipeline = Pipeline::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/dashboard?pipeline_id={$otherPipeline->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('pipeline_id');
});

it('rejects an unknown period', function () {
    ['token' => $token] = actingAsTenantUser('admin');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/dashboard?period=decada')
        ->assertStatus(422)
        ->assertJsonValidationErrors('period');
});

it('rejects unauthenticated access', function () {
    $this->getJson('/api/v1/dashboard')->assertStatus(401);
});
