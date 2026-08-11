<?php

declare(strict_types=1);

use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('registers a new tenant with an admin user and returns tokens', function () {
    Notification::fake();

    $response = $this->postJson('/api/v1/auth/register', [
        'company_name' => 'Acme Ltda',
        'name' => 'Ana Admin',
        'email' => 'ana@acme.test',
        'password' => 'Senha123!',
        'password_confirmation' => 'Senha123!',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.role', 'admin')
        ->assertJsonPath('data.tenant.name', 'Acme Ltda')
        ->assertJsonPath('data.user.email', 'ana@acme.test')
        ->assertJsonStructure(['data' => ['access_token', 'expires_in', 'token_type']])
        ->assertCookie('refresh_token');

    $user = User::query()->where('email', 'ana@acme.test')->first();
    $tenant = Tenant::query()->where('name', 'Acme Ltda')->first();

    expect($user)->not->toBeNull();
    expect($tenant)->not->toBeNull();
    expect(Membership::query()->where('user_id', $user->id)->where('tenant_id', $tenant->id)->exists())->toBeTrue();

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects registration with a duplicate email', function () {
    User::factory()->create(['email' => 'dup@acme.test']);

    $this->postJson('/api/v1/auth/register', [
        'company_name' => 'Outra Empresa',
        'name' => 'Beto',
        'email' => 'dup@acme.test',
        'password' => 'Senha123!',
        'password_confirmation' => 'Senha123!',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('rejects registration when password confirmation does not match', function () {
    $this->postJson('/api/v1/auth/register', [
        'company_name' => 'Outra Empresa',
        'name' => 'Beto',
        'email' => 'beto@acme.test',
        'password' => 'Senha123!',
        'password_confirmation' => 'Diferente123!',
    ])->assertStatus(422)->assertJsonValidationErrors('password');
});
