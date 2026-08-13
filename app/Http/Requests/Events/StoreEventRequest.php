<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Enums\EventKind;
use App\Models\Client;
use App\Models\Event;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Rules\TenantScopedRules;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEventRequest extends FormRequest
{
    private const RELATED_MODELS = [
        'lead' => Lead::class,
        'client' => Client::class,
        'opportunity' => Opportunity::class,
    ];

    public function authorize(): bool
    {
        return $this->user()->can('create', Event::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'kind' => ['sometimes', Rule::in(array_column(EventKind::cases(), 'value'))],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'location' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'uuid', TenantScopedRules::activeMember()],
            'workspace_id' => ['nullable', 'uuid', TenantScopedRules::workspace()],
            'related_type' => ['nullable', Rule::in(array_keys(self::RELATED_MODELS))],
            'related_id' => ['nullable', 'uuid', 'required_with:related_type'],
            'guests' => ['sometimes', 'array'],
            'guests.*.user_id' => ['sometimes', 'uuid', TenantScopedRules::activeMember()],
            'guests.*.name' => ['sometimes', 'string', 'max:255'],
            'guests.*.email' => ['sometimes', 'email', 'max:255'],
        ];
    }

    /**
     * A tabela de "related" depende do valor de related_type — não dá para
     * expressar isso com uma regra "exists" declarativa simples.
     */
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
