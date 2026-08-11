<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use App\Enums\TaskColumn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('task'));
    }

    public function rules(): array
    {
        return [
            'column' => ['required', Rule::in(array_column(TaskColumn::cases(), 'value'))],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
