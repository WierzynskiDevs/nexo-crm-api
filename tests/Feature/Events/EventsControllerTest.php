<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\EventGuest;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('creates an event with a mix of internal and external guests', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('sales');
    $colleague = User::factory()->create();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/events', [
            'title' => 'Demo com Acme',
            'kind' => 'demo',
            'starts_at' => '2026-09-01T14:00:00Z',
            'ends_at' => '2026-09-01T15:00:00Z',
            'guests' => [
                ['user_id' => $colleague->id],
                ['name' => 'Cliente Externo', 'email' => 'cliente@acme.test'],
            ],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Demo com Acme')
        ->assertJsonCount(2, 'data.guests');
});

it('rejects an event where ends_at is before starts_at', function () {
    ['token' => $token] = actingAsTenantUser('sales');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/events', [
            'title' => 'Inválido',
            'starts_at' => '2026-09-01T15:00:00Z',
            'ends_at' => '2026-09-01T14:00:00Z',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('ends_at');
});

it('rejects referencing a related lead from another tenant', function () {
    ['token' => $token] = actingAsTenantUser('sales');
    $foreignLead = Lead::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/events', [
            'title' => 'Reunião',
            'starts_at' => '2026-09-01T14:00:00Z',
            'ends_at' => '2026-09-01T15:00:00Z',
            'related_type' => 'lead',
            'related_id' => $foreignLead->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('related_id');
});

it('filters events by date range', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('sales');
    Event::factory()->create(['tenant_id' => $tenant->id, 'starts_at' => '2026-01-10T10:00:00Z', 'ends_at' => '2026-01-10T11:00:00Z']);
    Event::factory()->create(['tenant_id' => $tenant->id, 'starts_at' => '2026-03-10T10:00:00Z', 'ends_at' => '2026-03-10T11:00:00Z']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/events?from=2026-01-01&to=2026-01-31')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('cancels an event without deleting it', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('sales');
    $event = Event::factory()->create(['tenant_id' => $tenant->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/events/{$event->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.canceled_at', fn ($value) => $value !== null);

    expect(Event::find($event->id))->not->toBeNull();
});

it('rejects updating a guest that belongs to a different event, even in the same tenant', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('sales');
    $eventA = Event::factory()->create(['tenant_id' => $tenant->id]);
    $eventB = Event::factory()->create(['tenant_id' => $tenant->id]);
    $guestOfB = EventGuest::factory()->create(['event_id' => $eventB->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/events/{$eventA->id}/guests/{$guestOfB->id}", ['response_status' => 'accepted'])
        ->assertStatus(404);
});

it('returns 404 for an event belonging to another tenant', function () {
    ['token' => $token] = actingAsTenantUser('sales');
    $foreignEvent = Event::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/events/{$foreignEvent->id}")
        ->assertStatus(404);
});
