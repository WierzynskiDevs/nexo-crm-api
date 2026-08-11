<?php

declare(strict_types=1);

namespace App\Http\Requests\Opportunities;

use App\Models\Opportunity;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Opportunity::class);
    }

    /**
     * As regras "exists" abaixo são tenant-scoped explicitamente: a
     * validação "exists:tabela,coluna" consulta a tabela diretamente, sem
     * passar pela TenantScope do Eloquent — sem esse filtro extra, seria
     * possível referenciar um recurso de outro tenant e passar a validação.
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'pipeline_id' => [
                'required', 'uuid',
                Rule::exists('pipelines', 'id')->where('tenant_id', $tenantId),
            ],
            'pipeline_stage_id' => [
                'required', 'uuid',
                Rule::exists('pipeline_stages', 'id')->where('pipeline_id', $this->input('pipeline_id')),
            ],
            'lead_id' => [
                'nullable', 'uuid',
                Rule::exists('leads', 'id')->where('tenant_id', $tenantId),
            ],
            'client_id' => [
                'nullable', 'uuid',
                Rule::exists('clients', 'id')->where('tenant_id', $tenantId),
            ],
            'owner_id' => ['nullable', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'value_cents' => ['sometimes', 'integer', 'min:0'],
            'probability' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
        ];
    }
}
