<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams;

use App\Rules\TenantScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('team'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'lead_user_id' => ['nullable', 'uuid', TenantScopedRules::activeMember()],
            'pipeline_id' => ['nullable', 'uuid', TenantScopedRules::pipeline()],
            'goal_amount_cents' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
