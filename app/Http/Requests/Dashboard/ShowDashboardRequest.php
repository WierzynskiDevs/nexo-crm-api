<?php

declare(strict_types=1);

namespace App\Http\Requests\Dashboard;

use App\Enums\DashboardPeriod;
use App\Models\Pipeline;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowDashboardRequest extends FormRequest
{
    /**
     * O dashboard consolida dados de pipeline/oportunidades, então é gated por
     * "pipeline.visualizar" — todos os papéis a possuem hoje, mas a checagem é
     * real e acompanha eventuais mudanças no catálogo de RBAC.
     */
    public function authorize(): bool
    {
        return $this->user()->can('viewAny', Pipeline::class);
    }

    public function rules(): array
    {
        return [
            'period' => ['sometimes', Rule::in(array_column(DashboardPeriod::cases(), 'value'))],
            // exists direto na tabela não passa pela TenantScope — daí o
            // filtro explícito de tenant (mesmo padrão do módulo de
            // oportunidades).
            'pipeline_id' => [
                'sometimes', 'uuid',
                Rule::exists('pipelines', 'id')
                    ->where('tenant_id', app(TenantContext::class)->id())
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function period(): DashboardPeriod
    {
        return DashboardPeriod::tryFrom((string) $this->string('period')) ?? DashboardPeriod::Last30Days;
    }
}
