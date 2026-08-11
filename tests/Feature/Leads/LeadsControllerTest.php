<?php

declare(strict_types=1);

use App\Models\Lead;
use App\Models\Tenant;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('lists only leads belonging to the authenticated tenant', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('sales');
    Lead::factory(3)->create(['tenant_id' => $tenant->id]);
    Lead::factory(5)->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/leads')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('creates a lead scoped to the authenticated tenant with tags', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('sales');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/leads', [
            'name' => 'Maria Fernandes',
            'company' => 'Fernandes Ltda',
            'source' => 'inbound',
            'tags' => ['quente', 'prioridade'],
        ]);

    $response->assertCreated()->assertJsonPath('data.name', 'Maria Fernandes');
    expect($response->json('data.tags'))->toEqualCanonicalizing(['quente', 'prioridade']);

    $lead = Lead::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->first();
    expect($lead->tenant_id)->toBe($tenant->id);
});

it('rejects creating a lead without the leads.criar permission', function () {
    ['token' => $token] = actingAsTenantUser('support');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/leads', ['name' => 'X', 'source' => 'inbound'])
        ->assertStatus(403);
});

it('returns 404 for a lead belonging to another tenant (not 403 — avoids confirming existence)', function () {
    ['token' => $token] = actingAsTenantUser('sales');
    $foreignLead = Lead::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/leads/{$foreignLead->id}")
        ->assertStatus(404);
});

it('updates a lead and can replace its tags', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('sales');
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Original']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/leads/{$lead->id}", ['name' => 'Atualizado', 'tags' => ['novo']])
        ->assertOk()
        ->assertJsonPath('data.name', 'Atualizado')
        ->assertJsonPath('data.tags', ['novo']);
});

it('deletes a lead when the user has leads.excluir', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/leads/{$lead->id}")
        ->assertStatus(204);

    expect(Lead::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->withTrashed()->find($lead->id)->trashed())->toBeTrue();
});

it('rejects deleting a lead without leads.excluir (sales role)', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('sales');
    $lead = Lead::factory()->create(['tenant_id' => $tenant->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/leads/{$lead->id}")
        ->assertStatus(403);
});

it('filters leads by status and search term', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('sales');
    Lead::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Carlos Souza', 'status' => 'new']);
    Lead::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ana Lima', 'status' => 'qualified']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/leads?status=qualified')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Ana Lima');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/leads?search=Carlos')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Carlos Souza');
});

it('rejects unauthenticated access', function () {
    $this->getJson('/api/v1/leads')->assertStatus(401);
});
