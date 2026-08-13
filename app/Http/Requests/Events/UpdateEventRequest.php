<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Enums\EventKind;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Rules\TenantScopedRules;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEventRequest extends FormRequest
{
    private const RELATED_MODELS = [
        'lead' => Lead::class,
        'client' => Client::class,
        'opportunity' => Opportunity::class,
    ];

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event'));
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'kind' => ['sometimes', Rule::in(array_column(EventKind::cases(), 'value'))],
            'starts_at' => ['sometimes', 'date'],
            'ends_at' => ['sometimes', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'uuid', TenantScopedRules::activeMember()],
            'related_type' => ['nullable', Rule::in(array_keys(self::RELATED_MODELS))],
            'related_id' => ['nullable', 'uuid', 'required_with:related_type'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $relatedType = $this->input('related_type');
            $relatedId = $this->input('related_id');

            if (! $relatedType || ! $relatedId) {
                return;
            }

            $modelClass = self::RELATED_MODELS[$relatedType] ?? null;
            $tenantId = app(TenantContext::class)->id();

            if (! $modelClass || ! $modelClass::query()->where('tenant_id', $tenantId)->whereKey($relatedId)->exists()) {
                $validator->errors()->add('related_id', 'O registro relacionado não foi encontrado.');
            }
        });
    }
}
