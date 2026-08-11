<?php

namespace App\Providers;

use App\Services\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function ($request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        $this->configureAuthEmailLinks();
    }

    /**
     * A API é consumida por uma SPA (nexo-crm-app). Os e-mails de verificação
     * e reset de senha devem apontar para o frontend, que por sua vez chama
     * o endpoint real da API com os mesmos parâmetros assinados — nunca para
     * uma rota de backend que o navegador acessaria diretamente.
     */
    private function configureAuthEmailLinks(): void
    {
        VerifyEmail::createUrlUsing(function ($notifiable) {
            $backendUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(60),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ],
            );

            parse_str((string) parse_url($backendUrl, PHP_URL_QUERY), $query);

            return sprintf(
                '%s/verify-email?id=%s&hash=%s&expires=%s&signature=%s',
                rtrim((string) config('app.frontend_url'), '/'),
                $notifiable->getKey(),
                sha1($notifiable->getEmailForVerification()),
                $query['expires'] ?? '',
                $query['signature'] ?? '',
            );
        });

        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return sprintf(
                '%s/reset-password?token=%s&email=%s',
                rtrim((string) config('app.frontend_url'), '/'),
                $token,
                urlencode($notifiable->getEmailForPasswordReset()),
            );
        });
    }
}
