<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\Priority;
use App\Models\Lead;
use App\Rules\TenantScopedRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Lead::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['required', Rule::in(array_column(LeadSource::cases(), 'value'))],
            'status' => ['sometimes', Rule::in(array_column(LeadStatus::cases(), 'value'))],
            'priority' => ['sometimes', Rule::in(array_column(Priority::cases(), 'value'))],
            'score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'value_cents' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'owner_id' => ['nullable', 'uuid', TenantScopedRules::activeMember()],
            'workspace_id' => ['nullable', 'uuid', TenantScopedRules::workspace()],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:60'],
        ];
    }
}
