<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;

/**
 * Guarda o tenant autenticado da requisição atual. É resolvido uma única vez
 * pelo middleware ResolveTenantContext a partir do claim "tenant_id" do JWT
 * (nunca de um parâmetro enviado pelo cliente) e reaproveitado por toda a
 * aplicação — incluindo a TenantScope aplicada aos models de domínio.
 *
 * Vive como singleton no container, portanto é efetivamente por requisição
 * (o container é resolvido de novo a cada request HTTP).
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?string
    {
        return $this->tenant?->id;
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}
