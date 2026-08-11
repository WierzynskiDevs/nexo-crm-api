<?php

declare(strict_types=1);

namespace App\Http\Requests\Members;

use App\Enums\MembershipStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('member'));
    }

    public function rules(): array
    {
        return [
            'role_id' => ['sometimes', 'uuid', 'exists:roles,id'],
            'status' => ['sometimes', Rule::in([MembershipStatus::Active->value, MembershipStatus::Inactive->value])],
        ];
    }
}
