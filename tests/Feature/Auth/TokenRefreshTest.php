<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('issues a new access token and rotates the refresh cookie', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tokens = app(TokenService::class);
    ['plain' => $refreshPlain, 'session' => $session] = $tokens->issueRefreshToken($user, $tenant, request());

    $response = $this->withUnencryptedCookie('refresh_token', $refreshPlain)
        ->withCredentials()
        ->postJson('/api/v1/auth/refresh');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['access_token', 'expires_in']])
        ->assertCookie('refresh_token');

    expect($response->getCookie('refresh_token', decrypt: false)->getValue())->not->toBe($refreshPlain);
    expect($session->refresh()->revoked_at)->not->toBeNull();
});

it('rejects refresh without a cookie', function () {
    $this->postJson('/api/v1/auth/refresh')->assertStatus(401);
});

it('rejects refresh with a revoked session', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tokens = app(TokenService::class);
    ['plain' => $refreshPlain, 'session' => $session] = $tokens->issueRefreshToken($user, $tenant, request());
    $tokens->revoke($session);

    $this->withUnencryptedCookie('refresh_token', $refreshPlain)
        ->withCredentials()
        ->postJson('/api/v1/auth/refresh')
        ->assertStatus(401);
});
