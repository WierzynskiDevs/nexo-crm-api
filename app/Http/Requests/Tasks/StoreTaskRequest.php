<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use App\Enums\Priority;
use App\Enums\TaskColumn;
use App\Models\Task;
use App\Rules\TenantScopedRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Task::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'column' => ['sometimes', Rule::in(array_column(TaskColumn::cases(), 'value'))],
            'priority' => ['sometimes', Rule::in(array_column(Priority::cases(), 'value'))],
            'tag' => ['nullable', 'string', 'max:60'],
            'owner_id' => ['nullable', 'uuid', TenantScopedRules::activeMember()],
            'workspace_id' => ['nullable', 'uuid', TenantScopedRules::workspace()],
            'due_at' => ['nullable', 'date'],
            'checklist' => ['sometimes', 'array'],
            'checklist.*' => ['string', 'max:255'],
        ];
    }
}
