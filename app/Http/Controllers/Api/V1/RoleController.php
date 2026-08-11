<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\Membership;
use App\Models\Role;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Catálogo de RBAC somente leitura: papéis e permissões são seed do sistema,
 * não configuráveis por tenant (ver RolePermissionSeeder). A tela de
 * "Permissões" do protótipo mostra a matriz real aqui, mas não a edita.
 */
class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Membership::class);

        return RoleResource::collection(Role::query()->with('permissions')->orderBy('name')->get());
    }
}
