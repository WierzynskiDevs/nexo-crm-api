<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\MembershipStatus;
use App\Services\Tenancy\TenantContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Regras de validação que amarram um id ao tenant autenticado.
 *
 * A regra "exists:tabela,coluna" do Laravel consulta a tabela diretamente,
 * sem passar pela TenantScope do Eloquent — então, sozinha, ela aceita
 * alegremente o id de um recurso de outro tenant. Todo campo que referencia
 * outro registro precisa de um filtro explícito de tenant, e centralizá-lo
 * aqui evita que a próxima feature esqueça.
 *
 * O caso mais fácil de deixar passar é o de usuário: `users` é tabela global
 * (uma pessoa pode pertencer a várias empresas), então a FK sozinha não diz
 * nada sobre tenant. O vínculo real vive em `memberships`.
 */
final class TenantScopedRules
{
    /**
     * Usuário que é membro ATIVO do tenant corrente.
     *
     * Valida contra memberships (e não users), porque é a membership que
     * define a que empresa a pessoa pertence.
     */
    public static function activeMember(): Exists
    {
        return Rule::exists('memberships', 'user_id')
            ->where('tenant_id', app(TenantContext::class)->id())
            ->where('status', MembershipStatus::Active->value);
    }

    /** Workspace pertencente ao tenant corrente. */
    public static function workspace(): Exists
    {
        return Rule::exists('workspaces', 'id')
            ->where('tenant_id', app(TenantContext::class)->id())
            ->whereNull('deleted_at');
    }

    /** Pipeline pertencente ao tenant corrente. */
    public static function pipeline(): Exists
    {
        return Rule::exists('pipelines', 'id')
            ->where('tenant_id', app(TenantContext::class)->id())
            ->whereNull('deleted_at');
    }
}
