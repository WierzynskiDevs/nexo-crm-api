<?php

declare(strict_types=1);

namespace App\Http\Requests\Files;

use App\Models\Client;
use App\Models\File;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\Task;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFileRequest extends FormRequest
{
    public const FILEABLE_MODELS = [
        'lead' => Lead::class,
        'client' => Client::class,
        'task' => Task::class,
        'opportunity' => Opportunity::class,
    ];

    public function authorize(): bool
    {
        return $this->user()->can('create', File::class);
    }

    public function rules(): array
    {
        return [
            // 20MB, alinhado ao client_max_body_size do Nginx (docker/nginx/default.conf).
            'file' => ['required', 'file', 'max:20480'],
            'fileable_type' => ['nullable', Rule::in(array_keys(self::FILEABLE_MODELS))],
            'fileable_id' => ['nullable', 'uuid', 'required_with:fileable_type'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $fileableType = $this->input('fileable_type');
            $fileableId = $this->input('fileable_id');

            if (! $fileableType || ! $fileableId) {
                return;
            }

            $modelClass = self::FILEABLE_MODELS[$fileableType] ?? null;
            $tenantId = app(TenantContext::class)->id();

            if (! $modelClass || ! $modelClass::query()->where('tenant_id', $tenantId)->whereKey($fileableId)->exists()) {
                $validator->errors()->add('fileable_id', 'O registro relacionado não foi encontrado.');
            }
        });
    }
}
