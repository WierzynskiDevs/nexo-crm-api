<?php

declare(strict_types=1);

use App\Enums\MembershipStatus;
use App\Models\Lead;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Regressões das falhas encontradas na auditoria de segurança da Fase 12.
 * Cada teste aqui reproduz um ataque concreto que passava antes da correção.
 */
beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('rejects uploading a file whose real content is HTML, even disguised by extension', function () {
    Storage::fake('local');
    ['token' => $token] = actingAsTenantUser('admin');

    // Arquivo real em disco, não UploadedFile::fake(): o fake do Laravel deriva
    // o MIME do NOME do arquivo, então ele nunca exercitaria a checagem de
    // conteúdo — que é justamente o que precisa ser provado aqui.
    $caminho = tempnam(sys_get_temp_dir(), 'nexo').'.png';
    file_put_contents(
        $caminho,
        '<html><script>fetch("/api/v1/auth/refresh",{method:"POST"})</script></html>',
    );

    // Conteúdo HTML com extensão .png: servido inline na origem da API, viraria
    // XSS com acesso ao cookie de refresh da vítima.
    $arquivo = new UploadedFile($caminho, 'inofensivo.png', 'image/png', null, true);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/files', ['file' => $arquivo])
        ->assertStatus(422)
        ->assertJsonValidationErrors('file');

    @unlink($caminho);
});

it('serves downloads as attachment with nosniff, never inline', function () {
    Storage::fake('local');
    ['token' => $token] = actingAsTenantUser('admin');

    $upload = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/files', ['file' => UploadedFile::fake()->create('relatorio.pdf', 10, 'application/pdf')])
        ->assertCreated();

    $url = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/files/{$upload->json('data.id')}/download-url")
        ->assertOk()
        ->json('data.url');

    $response = $this->get($url)->assertOk();

    expect($response->headers->get('content-disposition'))->toStartWith('attachment')
        ->and($response->headers->get('x-content-type-options'))->toBe('nosniff');
});

it('adds security headers to every api response', function () {
    ['token' => $token] = actingAsTenantUser('admin');

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/leads')
        ->assertOk();

    expect($response->headers->get('x-content-type-options'))->toBe('nosniff')
        ->and($response->headers->get('x-frame-options'))->toBe('DENY');
});

it('prevents an admin from promoting themselves to super admin', function () {
    ['token' => $token, 'tenant' => $tenant, 'user' => $user] = actingAsTenantUser('admin');
    $minhaMembership = Membership::query()->where('user_id', $user->id)->where('tenant_id', $tenant->id)->firstOrFail();
    $superAdmin = Role::query()->where('slug', 'super_admin')->firstOrFail();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/members/{$minhaMembership->id}", ['role_id' => $superAdmin->id])
        ->assertStatus(422);

    expect($minhaMembership->refresh()->role_id)->not->toBe($superAdmin->id);
});

it('never allows assigning the super admin role to anyone', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');
    $colega = User::factory()->create();
    $membership = memberOf($colega, $tenant, 'sales');
    $superAdmin = Role::query()->where('slug', 'super_admin')->firstOrFail();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/members/{$membership->id}", ['role_id' => $superAdmin->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('role_id');
});

it('keeps the tenant check alive for a super admin (Gate::before must not skip policies)', function () {
    // Membership não tem TenantScope: a checagem da Policy é a ÚNICA barreira.
    // Um Gate::before que devolvesse true para qualquer ability a pularia.
    ['token' => $token] = actingAsTenantUser('super_admin');

    $outroTenant = Tenant::factory()->create();
    $estranho = User::factory()->create();
    $membershipAlheia = memberOf($estranho, $outroTenant, 'sales');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/members/{$membershipAlheia->id}", ['status' => 'inactive'])
        ->assertStatus(403);

    expect($membershipAlheia->refresh()->status)->toBe(MembershipStatus::Active);
});

it('rejects assigning an owner who belongs to another tenant', function () {
    ['token' => $token] = actingAsTenantUser('admin');

    $estranho = User::factory()->create();
    memberOf($estranho, Tenant::factory()->create(), 'sales');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/leads', [
            'name' => 'Lead teste',
            'source' => 'inbound',
            'owner_id' => $estranho->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('owner_id');

    expect(Lead::query()->count())->toBe(0);
});

it('rejects adding a team member who belongs to another tenant', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');

    $equipe = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/teams', ['name' => 'Comercial'])
        ->assertCreated();

    $estranho = User::factory()->create();
    memberOf($estranho, Tenant::factory()->create(), 'sales');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/teams/{$equipe->json('data.id')}/members", ['user_id' => $estranho->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('user_id');
});

it('rejects inviting someone who is already an active member', function () {
    ['token' => $token, 'tenant' => $tenant] = actingAsTenantUser('admin');
    $colega = User::factory()->create(['email' => 'colega@example.com']);
    $membership = memberOf($colega, $tenant, 'sales');
    $admin = Role::query()->where('slug', 'admin')->firstOrFail();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/members', [
            'name' => 'Qualquer',
            'email' => 'colega@example.com',
            'role_id' => $admin->id,
        ])
        ->assertStatus(422);

    // O membro continua ativo e com o papel original — reconvidar não pode
    // servir de atalho para editar quem já está dentro.
    $membership->refresh();
    expect($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->role_id)->not->toBe($admin->id);
});
