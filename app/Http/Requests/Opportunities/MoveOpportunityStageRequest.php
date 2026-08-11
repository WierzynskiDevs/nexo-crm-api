<?php

declare(strict_types=1);

namespace App\Http\Requests\Opportunities;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveOpportunityStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('opportunity'));
    }

    public function rules(): array
    {
        return [
            'pipeline_stage_id' => [
                'required', 'uuid',
                Rule::exists('pipeline_stages', 'id')->where('pipeline_id', $this->route('opportunity')->pipeline_id),
            ],
            'lost_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
