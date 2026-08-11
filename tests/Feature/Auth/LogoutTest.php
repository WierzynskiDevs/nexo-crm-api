<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('revokes the session and blacklists the token on logout', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $tokens = app(TokenService::class);

    $accessToken = $tokens->issueAccessToken($user, $tenant);
    ['plain' => $refreshPlain, 'session' => $session] = $tokens->issueRefreshToken($user, $tenant, request());

    $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->withUnencryptedCookie('refresh_token', $refreshPlain)
        ->withCredentials()
        ->postJson('/api/v1/auth/logout')
        ->assertStatus(204);

    expect($session->refresh()->revoked_at)->not->toBeNull();

    $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->getJson('/api/v1/auth/me')
        ->assertStatus(401);
});
