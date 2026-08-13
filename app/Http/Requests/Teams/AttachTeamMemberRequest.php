<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams;

use App\Rules\TenantScopedRules;
use Illuminate\Foundation\Http\FormRequest;

class AttachTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('team'));
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'uuid', TenantScopedRules::activeMember()],
        ];
    }
}
