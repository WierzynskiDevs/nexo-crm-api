<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\Session;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

/**
 * Emite e revoga tokens de acesso (JWT, curto) e de refresh (opaco, longo,
 * rastreado na tabela `sessions` para permitir revogação/rotação real).
 *
 * O tenant/role de um login são decididos pelo servidor a partir da
 * membership verificada do usuário — nunca a partir de um valor enviado
 * pelo cliente — e viram claims do JWT apenas no momento da emissão.
 */
class TokenService
{
    public function issueAccessToken(User $user, ?Tenant $tenant = null, ?Role $role = null): string
    {
        $claims = array_filter([
            'tenant_id' => $tenant?->id,
            'role' => $role?->slug,
        ]);

        return Auth::guard('api')->claims($claims)->login($user);
    }

    /**
     * @return array{plain: string, session: Session}
     */
    public function issueRefreshToken(User $user, ?Tenant $tenant, Request $request): array
    {
        $plain = Str::random(64);

        $session = Session::query()->create([
            'user_id' => $user->id,
            'tenant_id' => $tenant?->id,
            'refresh_token_hash' => $this->hash($plain),
            'user_agent' => (string) $request->userAgent(),
            'ip_address' => $request->ip(),
            'last_used_at' => now(),
            'expires_at' => Carbon::now()->addMinutes((int) config('jwt.refresh_ttl')),
        ]);

        return ['plain' => $plain, 'session' => $session];
    }

    public function findValidSession(string $plainToken): ?Session
    {
        return Session::query()
            ->where('refresh_token_hash', $this->hash($plainToken))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function revoke(Session $session): void
    {
        $session->update(['revoked_at' => now()]);
    }

    public function revokeAllForUser(User $user): void
    {
        $user->authSessions()->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    public function refreshCookie(string $plain): SymfonyCookie
    {
        return Cookie::make(
            name: 'refresh_token',
            value: $plain,
            minutes: (int) config('jwt.refresh_ttl'),
            path: '/api/v1/auth',
            domain: null,
            secure: ! app()->environment('local'),
            httpOnly: true,
            raw: false,
            sameSite: 'lax',
        );
    }

    public function forgetRefreshCookie(): SymfonyCookie
    {
        return Cookie::forget('refresh_token', '/api/v1/auth');
    }

    private function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
