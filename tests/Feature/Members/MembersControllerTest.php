<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\MembershipInviteNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('invites a new member, creating the user and sending the invite email', function () {
    Notification::fake();
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');
    $salesRole = Role::query()->where('slug', 'sales')->first();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/members', [
            'name' => 'Novo Vendedor',
            'email' => 'novo@acme.test',
            'role_id' => $salesRole->id,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'invited')
        ->assertJsonPath('data.user.email', 'novo@acme.test');

    $user = User::query()->where('email', 'novo@acme.test')->first();
    expect(Membership::query()->where('user_id', $user->id)->where('tenant_id', $tenant->id)->exists())->toBeTrue();

    Notification::assertSentTo($user, MembershipInviteNotification::class);
});

it('rejects inviting a member without usuarios.criar permission', function () {
    ['token' => $token] = actingAsTenantUser('sales');
    $role = Role::query()->where('slug', 'sales')->first();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/members', ['name' => 'X', 'email' => 'x@acme.test', 'role_id' => $role->id])
        ->assertStatus(403);
});

it('updates a membership role and records an audit log', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');
    $member = User::factory()->create();
    $salesRole = Role::query()->where('slug', 'sales')->first();
    $managerRole = Role::query()->where('slug', 'manager')->first();
    $membership = memberOf($member, $tenant, 'sales');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/members/{$membership->id}", ['role_id' => $managerRole->id])
        ->assertOk()
        ->assertJsonPath('data.role.slug', 'manager');

    expect(AuditLog::query()->where('action', 'member.updated')->where('auditable_id', $membership->id)->exists())->toBeTrue();
});

it('prevents a user from removing their own membership', function () {
    ['token' => $token, 'tenant' => $tenant, 'user' => $admin] = actingAsTenantUser('admin');
    $membership = Membership::query()->where('user_id', $admin->id)->where('tenant_id', $tenant->id)->first();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/members/{$membership->id}")
        ->assertStatus(422);
});

it('removes a member', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');
    $member = User::factory()->create();
    $membership = memberOf($member, $tenant, 'sales');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/members/{$membership->id}")
        ->assertStatus(204);

    expect(Membership::find($membership->id))->toBeNull();
});

it('lists only members belonging to the authenticated tenant', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');
    $otherTenant = Tenant::factory()->create();
    memberOf(User::factory()->create(), $otherTenant, 'sales');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/members')
        ->assertOk();

    // Só o admin autenticado deve aparecer (nenhum member extra foi criado no tenant dele).
    expect($response->json('data'))->toHaveCount(1);
});
