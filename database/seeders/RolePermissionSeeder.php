<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seed real de roles/permissions do Nexo CRM. Substitui a matriz sintética do
 * protótipo (fórmula sem relação com regra de negócio) por um mapeamento
 * explícito, decidido a partir da semântica de cada papel:
 *
 * - super_admin: acesso total, incluindo o módulo "Empresas" (administração
 *   cross-tenant — não delegável a papéis de dentro de um tenant).
 * - admin: acesso total dentro do próprio tenant, exceto "Empresas".
 * - manager: visualizar/criar/editar/exportar no núcleo de CRM; leitura de
 *   Usuários/Logs; leitura+edição de Configurações.
 * - sales: visualizar/criar/editar no núcleo de CRM, sem excluir/exportar.
 * - support: leitura no núcleo de CRM, com criar/editar restrito a Tarefas.
 */
class RolePermissionSeeder extends Seeder
{
    private const MODULES = [
        'Leads', 'Clientes', 'Pipeline', 'Tarefas', 'Arquivos',
        'Usuários', 'Empresas', 'Logs', 'Configurações',
    ];

    private const ACTIONS = ['visualizar', 'criar', 'editar', 'excluir', 'exportar', 'configurar'];

    private const CRM_MODULES = ['Leads', 'Clientes', 'Pipeline', 'Tarefas', 'Arquivos'];

    public function run(): void
    {
        $permissions = [];
        foreach (self::MODULES as $module) {
            foreach (self::ACTIONS as $action) {
                $permissions[$module][$action] = Permission::query()->firstOrCreate(
                    ['module' => $module, 'action' => $action],
                    ['slug' => Str::slug($module).'.'.Str::slug($action)],
                );
            }
        }

        $roles = [
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'manager' => 'Manager',
            'sales' => 'Sales',
            'support' => 'Support',
        ];

        foreach ($roles as $slug => $name) {
            $role = Role::query()->firstOrCreate(['slug' => $slug], ['name' => $name]);
            $role->permissions()->sync($this->permissionIdsFor($slug, $permissions));
        }
    }

    /**
     * @param  array<string, array<string, Permission>>  $permissions
     * @return array<int, string>
     */
    private function permissionIdsFor(string $roleSlug, array $permissions): array
    {
        $ids = [];

        $grant = function (array $modules, array $actions) use (&$ids, $permissions) {
            foreach ($modules as $module) {
                foreach ($actions as $action) {
                    $ids[] = $permissions[$module][$action]->id;
                }
            }
        };

        match ($roleSlug) {
            'super_admin' => $grant(self::MODULES, self::ACTIONS),
            'admin' => $grant(array_diff(self::MODULES, ['Empresas']), self::ACTIONS),
            'manager' => (function () use ($grant) {
                $grant(self::CRM_MODULES, ['visualizar', 'criar', 'editar', 'exportar']);
                $grant(['Usuários', 'Logs'], ['visualizar']);
                $grant(['Configurações'], ['visualizar', 'editar']);
            })(),
            'sales' => $grant(self::CRM_MODULES, ['visualizar', 'criar', 'editar']),
            'support' => (function () use ($grant) {
                $grant(array_diff(self::CRM_MODULES, ['Tarefas']), ['visualizar']);
                $grant(['Tarefas'], ['visualizar', 'criar', 'editar']);
            })(),
            default => null,
        };

        return array_values(array_unique($ids));
    }
}
