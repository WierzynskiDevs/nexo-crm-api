<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Models\Membership;
use Illuminate\Foundation\Http\FormRequest;

class InviteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Membership::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role_id' => ['required', 'uuid', 'exists:roles,id'],
        ];
    }
}
