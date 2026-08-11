<?php

declare(strict_types=1);

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Session;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\TokenService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('always responds generically to forgot-password, regardless of the email existing', function () {
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nao-existe@acme.test'])
        ->assertOk()
        ->assertJsonPath('data.message', 'Se o e-mail existir, enviaremos instruções de redefinição de senha.');
});

it('sends a reset notification pointing to the frontend when the email exists', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();

    Notification::assertSentTo($user, ResetPassword::class);
});

it('resets the password with a valid token, revokes existing sessions and allows login with the new password', function () {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->create();
    $role = Role::query()->where('slug', 'sales')->first();

    Membership::query()->create([
        'user_id' => $user->id,
        'tenant_id' => $tenant->id,
        'role_id' => $role->id,
        'status' => MembershipStatus::Active,
        'joined_at' => now(),
    ]);

    app(TokenService::class)->issueRefreshToken($user, $tenant, request());

    $token = Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'NovaSenha123!',
        'password_confirmation' => 'NovaSenha123!',
    ])->assertOk();

    expect(Session::query()->where('user_id', $user->id)->whereNull('revoked_at')->exists())->toBeFalse();

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'senha-antiga-qualquer',
    ])->assertStatus(422);

    $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'NovaSenha123!',
    ])->assertOk();
});

it('rejects reset-password with an invalid token', function () {
    $user = User::factory()->create();

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'token-invalido',
        'email' => $user->email,
        'password' => 'NovaSenha123!',
        'password_confirmation' => 'NovaSenha123!',
    ])->assertStatus(422)->assertJsonValidationErrors('email');
});
