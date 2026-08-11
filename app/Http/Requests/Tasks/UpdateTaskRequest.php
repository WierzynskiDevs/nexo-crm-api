<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use App\Enums\Priority;
use App\Enums\TaskColumn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'column' => ['sometimes', Rule::in(array_column(TaskColumn::cases(), 'value'))],
            'priority' => ['sometimes', Rule::in(array_column(Priority::cases(), 'value'))],
            'tag' => ['nullable', 'string', 'max:60'],
            'owner_id' => ['nullable', 'uuid'],
            'due_at' => ['nullable', 'date'],
        ];
    }
}
