<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Registro de auditoria para ações administrativas sensíveis (CLAUDE.md
 * §16): alteração de papel, suspensão, exclusão, alteração de permissões.
 * Tabela append-only — nunca editada após criada.
 */
class AuditLogger
{
    public function log(
        string $action,
        ?Model $auditable = null,
        array $oldValues = [],
        array $newValues = [],
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::create([
            'tenant_id' => app(TenantContext::class)->id(),
            'actor_user_id' => Auth::guard('api')->id(),
            'action' => $action,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
