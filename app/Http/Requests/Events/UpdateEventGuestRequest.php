<?php

declare(strict_types=1);

namespace App\Http\Requests\Events;

use App\Enums\EventGuestResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEventGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('event'));
    }

    public function rules(): array
    {
        return [
            'response_status' => ['required', Rule::in(array_column(EventGuestResponse::cases(), 'value'))],
        ];
    }
}
