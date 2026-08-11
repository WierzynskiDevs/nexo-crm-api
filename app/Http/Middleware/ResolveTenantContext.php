<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Roda depois de "auth:api". Resolve o tenant da requisição a partir do
 * claim "tenant_id" do JWT — nunca de um parâmetro enviado pelo cliente — e
 * revalida a membership a cada requisição (o token pode ter sido emitido
 * antes de a membership ser revogada/desativada).
 *
 * Se o token não tiver claim de tenant (ex.: token de seleção de tenant,
 * emitido durante o login multi-tenant), a requisição segue sem tenant
 * resolvido — cabe à rota decidir se isso é aceitável.
 */
class ResolveTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = Auth::guard('api')->payload()->get('tenant_id');

        if ($tenantId === null) {
            return $next($request);
        }

        $membership = Membership::query()
            ->where('user_id', Auth::guard('api')->id())
            ->where('tenant_id', $tenantId)
            ->where('status', MembershipStatus::Active)
            ->with('tenant')
            ->first();

        if (! $membership) {
            return response()->json([
                'message' => 'O acesso a esta empresa não é mais válido. Faça login novamente.',
            ], 403);
        }

        $this->context->set($membership->tenant);

        return $next($request);
    }
}
