<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SelectTenantRequest;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\RegistrationService;
use App\Services\Auth\TokenService;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly RegistrationService $registration,
        private readonly TenantContext $tenantContext,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->registration->register($request->validated());

        $result['user']->sendEmailVerificationNotification();

        return $this->issueFullSession($request, $result['user'], $result['tenant']->id, $result['role']->slug);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email'))->first();

        if (! $user || ! Auth::guard('api')->getProvider()->validateCredentials($user, $request->only('password'))) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais informadas não conferem.'],
            ]);
        }

        $memberships = Membership::query()
            ->where('user_id', $user->id)
            ->where('status', MembershipStatus::Active)
            ->with('tenant', 'role')
            ->get();

        if ($memberships->isEmpty()) {
            throw ValidationException::withMessages([
                'email' => ['Este usuário não possui nenhuma empresa ativa associada.'],
            ]);
        }

        if ($memberships->count() === 1) {
            $membership = $memberships->first();

            return $this->issueFullSession($request, $user, $membership->tenant_id, $membership->role->slug);
        }

        $accessToken = $this->tokens->issueAccessToken($user);

        return response()->json([
            'data' => [
                'requires_tenant_selection' => true,
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'tenants' => TenantResource::collection($memberships->pluck('tenant')),
            ],
        ]);
    }

    public function selectTenant(SelectTenantRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();

        $membership = Membership::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $request->string('tenant_id'))
            ->where('status', MembershipStatus::Active)
            ->with('role')
            ->first();

        if (! $membership) {
            throw ValidationException::withMessages([
                'tenant_id' => ['Você não tem acesso a esta empresa.'],
            ]);
        }

        return $this->issueFullSession($request, $user, $membership->tenant_id, $membership->role->slug);
    }

    public function refresh(Request $request): JsonResponse
    {
        $plain = $request->cookie('refresh_token');

        if (! $plain) {
            return response()->json(['message' => 'Refresh token ausente.'], 401);
        }

        $session = $this->tokens->findValidSession($plain);

        if (! $session) {
            return response()->json(['message' => 'Sessão inválida ou expirada.'], 401)
                ->withCookie($this->tokens->forgetRefreshCookie());
        }

        $this->tokens->revoke($session);

        $user = $session->user;
        $role = $session->tenant_id
            ? Membership::query()->where('user_id', $user->id)->where('tenant_id', $session->tenant_id)->with('role')->first()?->role
            : null;

        $accessToken = $this->tokens->issueAccessToken($user, $session->tenant, $role);
        ['plain' => $newPlain] = $this->tokens->issueRefreshToken($user, $session->tenant, $request);

        return response()->json([
            'data' => [
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => (int) config('jwt.ttl') * 60,
            ],
        ])->withCookie($this->tokens->refreshCookie($newPlain));
    }

    public function logout(Request $request): JsonResponse
    {
        $plain = $request->cookie('refresh_token');

        if ($plain) {
            $session = $this->tokens->findValidSession($plain);
            if ($session) {
                $this->tokens->revoke($session);
            }
        }

        Auth::guard('api')->logout();

        return response()->json(null, 204)->withCookie($this->tokens->forgetRefreshCookie());
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::guard('api')->user();
        $tenant = $this->tenantContext->get();

        if (! $tenant) {
            return response()->json([
                'data' => [
                    'user' => new UserResource($user),
                    'tenant' => null,
                    'role' => null,
                    'permissions' => [],
                ],
            ]);
        }

        $membership = Membership::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenant->id)
            ->with('role.permissions')
            ->first();

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'tenant' => new TenantResource($tenant),
                'role' => $membership?->role->slug,
                'permissions' => $membership?->role->permissions->pluck('slug') ?? [],
            ],
        ]);
    }

    private function issueFullSession(Request $request, User $user, string $tenantId, string $roleSlug): JsonResponse
    {
        $role = Role::query()->where('slug', $roleSlug)->first();
        $tenant = Tenant::query()->find($tenantId);

        $accessToken = $this->tokens->issueAccessToken($user, $tenant, $role);
        ['plain' => $refreshPlain] = $this->tokens->issueRefreshToken($user, $tenant, $request);

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'tenant' => new TenantResource($tenant),
                'role' => $roleSlug,
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => (int) config('jwt.ttl') * 60,
            ],
        ])->withCookie($this->tokens->refreshCookie($refreshPlain));
    }
}
