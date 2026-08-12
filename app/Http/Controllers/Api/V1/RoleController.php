<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Membership;
use App\Models\Role;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

/**
 * Catálogo de RBAC somente leitura: papéis e permissões são seed do sistema,
 * não configuráveis por tenant (ver RolePermissionSeeder). A tela de
 * "Permissões" do protótipo mostra a matriz real aqui, mas não a edita.
 */
class RoleController extends Controller
{
    #[OA\Get(
        path: '/api/v1/roles',
        summary: 'Catálogo de papéis e permissões',
        description: 'Somente leitura: papéis e permissões são seed do produto, iguais para todos os tenants, e não são configuráveis via API.',
        security: [['bearerAuth' => []]],
        tags: ['Governança'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/RoleCollection')),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthorized'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
        ],
    )]
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Membership::class);

        return RoleResource::collection(Role::query()->with('permissions')->orderBy('name')->get());
    }
}
