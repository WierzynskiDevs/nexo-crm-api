<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('lead'));
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['sometimes', Rule::in(array_column(LeadSource::cases(), 'value'))],
            'status' => ['sometimes', Rule::in(array_column(LeadStatus::cases(), 'value'))],
            'priority' => ['sometimes', Rule::in(array_column(Priority::cases(), 'value'))],
            'score' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'value_cents' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'owner_id' => ['nullable', 'uuid'],
            'workspace_id' => ['nullable', 'uuid'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:60'],
        ];
    }
}
