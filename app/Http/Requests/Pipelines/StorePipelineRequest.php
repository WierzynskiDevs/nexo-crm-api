<?php

declare(strict_types=1);

namespace App\Http\Requests\Pipelines;

use App\Models\Pipeline;
use Illuminate\Foundation\Http\FormRequest;

class StorePipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Pipeline::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'workspace_id' => ['nullable', 'uuid'],
            'is_default' => ['sometimes', 'boolean'],
            'stages' => ['sometimes', 'array', 'min:1'],
            'stages.*' => ['string', 'max:255'],
        ];
    }
}
