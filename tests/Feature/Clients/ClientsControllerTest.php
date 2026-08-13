<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Lead;
use App\Models\Tenant;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('lists only clients belonging to the authenticated tenant', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('manager');
    Client::factory(2)->create(['tenant_id' => $tenant->id]);
    Client::factory(4)->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/clients')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('creates a client scoped to the tenant', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('manager');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/clients', ['name' => 'Zenith Bank', 'segment' => 'enterprise']);

    $response->assertCreated()->assertJsonPath('data.name', 'Zenith Bank');
    expect(Client::query()->where('tenant_id', $tenant->id)->where('name', 'Zenith Bank')->exists())->toBeTrue();
});

it('rejects referencing a lead from another tenant as converted_from_lead_id', function () {
    ['token' => $token] = actingAsTenantUser('manager');
    $foreignLead = Lead::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/clients', [
            'name' => 'Empresa X',
            'converted_from_lead_id' => $foreignLead->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('converted_from_lead_id');
});

it('returns 404 for a client belonging to another tenant', function () {
    ['token' => $token] = actingAsTenantUser('manager');
    $foreignClient = Client::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/clients/{$foreignClient->id}")
        ->assertStatus(404);
});
