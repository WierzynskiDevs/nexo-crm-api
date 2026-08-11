<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Lead;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Services\Tenancy\TenantContext;

it('returns no rows when no tenant is resolved, even if data exists', function () {
    Lead::factory(3)->create(['tenant_id' => Tenant::factory()->create()->id]);

    expect(Lead::all())->toHaveCount(0);
});

it('only returns rows belonging to the resolved tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Lead::factory(2)->create(['tenant_id' => $tenantA->id]);
    Lead::factory(5)->create(['tenant_id' => $tenantB->id]);

    app(TenantContext::class)->set($tenantA);
    expect(Lead::all())->toHaveCount(2);
    expect(Lead::all()->pluck('tenant_id')->unique()->all())->toBe([$tenantA->id]);

    app(TenantContext::class)->set($tenantB);
    expect(Lead::all())->toHaveCount(5);
});

it('cannot fetch a record by id belonging to another tenant (IDOR guard)', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $foreignLead = Lead::factory()->create(['tenant_id' => $tenantB->id]);

    app(TenantContext::class)->set($tenantA);

    expect(Lead::find($foreignLead->id))->toBeNull();
});

it('auto-fills tenant_id from the resolved context when not provided explicitly', function () {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);

    $lead = Lead::create([
        'name' => 'Sem tenant_id explícito',
        'source' => 'inbound',
    ]);

    expect($lead->tenant_id)->toBe($tenant->id);
});

it('enforces isolation across different tenant-scoped models, not just Lead', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Client::factory(2)->create(['tenant_id' => $tenantA->id]);
    Client::factory(4)->create(['tenant_id' => $tenantB->id]);

    app(TenantContext::class)->set($tenantA);

    expect(Client::all())->toHaveCount(2);
});

it('allows a trusted escape hatch via withoutGlobalScope for legitimate cross-tenant access', function () {
    Tenant::factory()->create();
    Lead::factory(3)->create(['tenant_id' => Tenant::factory()->create()->id]);

    expect(Lead::withoutGlobalScope(TenantScope::class)->count())->toBe(3);
});
