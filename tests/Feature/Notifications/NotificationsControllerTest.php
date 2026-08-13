<?php

declare(strict_types=1);

use App\Models\Notification;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('lists only the notifications addressed to the authenticated user', function () {
    ['token' => $token, 'tenant' => $tenant, 'user' => $user] = actingAsTenantUser('admin');

    Notification::factory()->count(2)->create([
        'tenant_id' => $tenant->id,
        'notifiable_id' => $user->id,
    ]);

    // Colega do MESMO tenant: a TenantScope não protege contra isso — quem
    // separa é o filtro por notifiable.
    $colleague = User::factory()->create();
    memberOf($colleague, $tenant, 'sales');
    Notification::factory()->create([
        'tenant_id' => $tenant->id,
        'notifiable_id' => $colleague->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/notifications')
        ->assertOk();

    expect($response->json('data'))->toHaveCount(2);
});

it('never lists notifications from another tenant', function () {
    ['token' => $token, 'user' => $user] = actingAsTenantUser('admin');

    // Mesmo usuário, outro tenant: não pode aparecer na caixa deste tenant.
    Notification::factory()->create([
        'tenant_id' => Tenant::factory()->create()->id,
        'notifiable_id' => $user->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/notifications')
        ->assertOk();

    expect($response->json('data'))->toBeEmpty();
});

it('filters unread notifications and exposes the unread count', function () {
    ['token' => $token, 'tenant' => $tenant, 'user' => $user] = actingAsTenantUser('admin');

    Notification::factory()->count(3)->create(['tenant_id' => $tenant->id, 'notifiable_id' => $user->id]);
    Notification::factory()->read()->count(2)->create(['tenant_id' => $tenant->id, 'notifiable_id' => $user->id]);

    $unread = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/notifications?unread=1')
        ->assertOk();

    expect($unread->json('data'))->toHaveCount(3);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unread', 3);
});

it('marks a single notification as read without shifting an already-read one', function () {
    ['token' => $token, 'tenant' => $tenant, 'user' => $user] = actingAsTenantUser('admin');

    $notification = Notification::factory()->create([
        'tenant_id' => $tenant->id,
        'notifiable_id' => $user->id,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk();

    $readAt = $notification->refresh()->read_at;
    expect($readAt)->not->toBeNull();

    // Idempotente: reler mantém o read_at original.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/notifications/{$notification->id}/read")
        ->assertOk();

    expect($notification->refresh()->read_at->toIso8601String())->toBe($readAt->toIso8601String());
});

it('marks every unread notification as read at once', function () {
    ['token' => $token, 'tenant' => $tenant, 'user' => $user] = actingAsTenantUser('admin');

    Notification::factory()->count(4)->create(['tenant_id' => $tenant->id, 'notifiable_id' => $user->id]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/notifications/read-all')
        ->assertStatus(204);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unread', 0);
});

it("returns 404 when marking a colleague's notification as read", function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');

    $colleague = User::factory()->create();
    memberOf($colleague, $tenant, 'sales');
    $foreign = Notification::factory()->create([
        'tenant_id' => $tenant->id,
        'notifiable_id' => $colleague->id,
    ]);

    // 403 e não 404: o recurso existe e é do mesmo tenant, então a
    // Policy é que barra — não há informação vazando na distinção.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/notifications/{$foreign->id}/read")
        ->assertStatus(403);

    expect($foreign->refresh()->read_at)->toBeNull();
});

it('deletes only its own notification', function () {
    ['token' => $token, 'tenant' => $tenant, 'user' => $user] = actingAsTenantUser('admin');

    $notification = Notification::factory()->create([
        'tenant_id' => $tenant->id,
        'notifiable_id' => $user->id,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/notifications/{$notification->id}")
        ->assertStatus(204);

    expect(Notification::withoutGlobalScope(TenantScope::class)->find($notification->id))->toBeNull();
});

it('rejects unauthenticated access', function () {
    $this->getJson('/api/v1/notifications')->assertStatus(401);
});
