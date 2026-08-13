<?php

declare(strict_types=1);

namespace App\Http\Requests\Opportunities;

use App\Rules\TenantScopedRules;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOpportunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('opportunity'));
    }

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'lead_id' => [
                'nullable', 'uuid',
                Rule::exists('leads', 'id')->where('tenant_id', $tenantId),
            ],
            'client_id' => [
                'nullable', 'uuid',
                Rule::exists('clients', 'id')->where('tenant_id', $tenantId),
            ],
            'owner_id' => ['nullable', 'uuid', TenantScopedRules::activeMember()],
            'value_cents' => ['sometimes', 'integer', 'min:0'],
            'probability' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
        ];
    }
}
