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
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    public function __construct(
        private readonly TokenService $tokens,
        private readonly RegistrationService $registration,
        private readonly TenantContext $tenantContext,
    ) {}

    #[OA\Post(
        path: '/api/v1/auth/register',
        summary: 'Registra uma nova empresa e seu primeiro usuário',
        description: 'Cria tenant, workspace, usuário e a membership de Admin numa única transação, e já devolve a sessão completa. Dispara o e-mail de verificação. Limitado por rate limit.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation', 'company_name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Marina Alves'),
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                    new OA\Property(property: 'company_name', description: 'Nome da empresa (tenant)', type: 'string', example: 'Acme Holdings'),
                ],
            ),
        ),
        tags: ['Autenticação'],
        responses: [
            new OA\Response(response: 200, description: 'Sessão criada', content: new OA\JsonContent(ref: '#/components/schemas/SessionEnvelope')),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, description: 'Rate limit excedido', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
        ],
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $result = $this->registration->register($request->validated());

        $result['user']->sendEmailVerificationNotification();

        return $this->issueFullSession($request, $result['user'], $result['tenant']->id, $result['role']->slug);
    }

    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'Autentica um usuário',
        description: <<<'MD'
            Com **uma** empresa ativa, devolve a sessão completa e o cookie de refresh.

            Com **mais de uma**, devolve `requires_tenant_selection: true` e um access
            token sem claim de tenant, que só serve para chamar `/auth/select-tenant`.

            Credenciais inválidas e usuário sem empresa ativa respondem 422 com
            mensagem genérica, para não revelar quais e-mails existem.
            MD,
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ],
            ),
        ),
        tags: ['Autenticação'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sessão completa, ou desafio de seleção de empresa',
                content: new OA\JsonContent(oneOf: [
                    new OA\Schema(ref: '#/components/schemas/SessionEnvelope'),
                    new OA\Schema(ref: '#/components/schemas/TenantSelectionEnvelope'),
                ]),
            ),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
            new OA\Response(response: 429, description: 'Rate limit excedido', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
        ],
    )]
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

    #[OA\Post(
        path: '/api/v1/auth/select-tenant',
        summary: 'Escolhe a empresa e emite a sessão completa',
        description: 'Segundo passo do login quando o usuário pertence a mais de uma empresa. A membership é revalidada aqui — não se confia no `tenant_id` enviado.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['tenant_id'],
                properties: [new OA\Property(property: 'tenant_id', type: 'string', format: 'uuid')],
            ),
        ),
        tags: ['Autenticação'],
        responses: [
            new OA\Response(response: 200, description: 'Sessão criada', content: new OA\JsonContent(ref: '#/components/schemas/SessionEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 422, description: 'Usuário não tem acesso ativo a esta empresa', content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')),
        ],
    )]
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

    #[OA\Post(
        path: '/api/v1/auth/refresh',
        summary: 'Renova o access token',
        description: 'Lê o refresh token do cookie httpOnly (não do corpo) e o rotaciona: o anterior é invalidado na mesma chamada. Requer que o cliente envie cookies (`credentials: include`).',
        tags: ['Autenticação'],
        responses: [
            new OA\Response(response: 200, description: 'Nova sessão, com cookie de refresh rotacionado', content: new OA\JsonContent(ref: '#/components/schemas/SessionEnvelope')),
            new OA\Response(response: 401, description: 'Cookie ausente, sessão revogada ou expirada', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
            new OA\Response(response: 429, description: 'Rate limit excedido', content: new OA\JsonContent(ref: '#/components/schemas/ErrorMessage')),
        ],
    )]
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

    #[OA\Post(
        path: '/api/v1/auth/logout',
        summary: 'Encerra a sessão',
        description: 'Revoga a sessão de refresh, invalida o access token e limpa o cookie.',
        security: [['bearerAuth' => []]],
        tags: ['Autenticação'],
        responses: [
            new OA\Response(response: 204, ref: '#/components/responses/NoContent'),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
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

    #[OA\Get(
        path: '/api/v1/auth/me',
        summary: 'Dados da sessão corrente',
        description: 'Usuário, empresa, papel e a lista de permissões efetivas — é a fonte que o frontend usa para montar a navegação. `tenant` e `role` vêm nulos se o token ainda não tiver claim de empresa.',
        security: [['bearerAuth' => []]],
        tags: ['Autenticação'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/CurrentSessionEnvelope')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
        ],
    )]
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
