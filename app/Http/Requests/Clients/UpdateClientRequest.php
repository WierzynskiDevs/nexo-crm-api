<?php

declare(strict_types=1);

namespace App\Http\Requests\Clients;

use App\Enums\ClientHealth;
use App\Enums\ClientSegment;
use App\Rules\TenantScopedRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('client'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'mrr_cents' => ['sometimes', 'integer', 'min:0'],
            'health' => ['sometimes', Rule::in(array_column(ClientHealth::cases(), 'value'))],
            'segment' => ['sometimes', Rule::in(array_column(ClientSegment::cases(), 'value'))],
            'client_since' => ['nullable', 'date'],
            'owner_id' => ['nullable', 'uuid', TenantScopedRules::activeMember()],
            'workspace_id' => ['nullable', 'uuid', TenantScopedRules::workspace()],
            'archived_at' => ['nullable', 'date'],
        ];
    }
}
