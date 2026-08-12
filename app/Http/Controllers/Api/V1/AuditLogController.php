<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Services\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

class AuditLogController extends Controller
{
    #[OA\Get(
        path: '/api/v1/audit-logs',
        summary: 'Lista a trilha de auditoria do tenant',
        description: 'Registros imutáveis de ações relevantes (convite de membro, mudança de papel, etc.). Exige a permissão `logs.visualizar`.',
        security: [['bearerAuth' => []]],
        tags: ['Governança'],
        parameters: [
            new OA\Parameter(ref: '#/components/parameters/page'),
            new OA\Parameter(ref: '#/components/parameters/perPage'),
            new OA\Parameter(name: 'action', description: 'Filtra por ação exata, ex.: `member.invited`', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', description: 'Busca por endereço IP', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Coleção paginada', content: new OA\JsonContent(ref: '#/components/schemas/AuditLogCollection')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AuditLog::class);

        $logs = AuditLog::query()
            ->where('tenant_id', app(TenantContext::class)->id())
            ->with('actor')
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->string('search').'%';
                $query->where(fn ($q) => $q->where('ip_address', 'ilike', $term));
            })
            ->latest()
            ->paginate($request->integer('per_page', 25));

        return AuditLogResource::collection($logs);
    }
}
