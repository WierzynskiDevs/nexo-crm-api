<?php

declare(strict_types=1);

namespace App\Http\Requests\Teams;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Team::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'lead_user_id' => ['nullable', 'uuid'],
            'pipeline_id' => ['nullable', 'uuid'],
            'goal_amount_cents' => ['nullable', 'integer', 'min:0'],
            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => ['uuid'],
        ];
    }
}
