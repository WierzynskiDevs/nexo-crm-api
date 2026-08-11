<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Auth\TokenService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('verifies the email with a valid signed link', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
    );

    $path = str_replace(url('/'), '', $url);

    $this->postJson($path)->assertOk();

    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects verification with a mismatched hash', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => 'hash-errado-'.sha1($user->getEmailForVerification())],
    );

    $path = str_replace(url('/'), '', $url);

    $this->postJson($path)->assertStatus(403);
    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('resends the verification email for an authenticated unverified user', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $token = app(TokenService::class)->issueAccessToken($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/email/resend')
        ->assertOk();

    Notification::assertSentTo($user, VerifyEmail::class);
});
