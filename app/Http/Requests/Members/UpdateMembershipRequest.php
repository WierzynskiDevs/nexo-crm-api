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
            // super_admin fica fora do catálogo atribuível: é o papel de
            // administração cross-tenant (ver RolePermissionSeeder), e
            // qualquer Admin com "usuarios.editar" poderia se conceder a si
            // mesmo — ou a um cúmplice — acesso fora do próprio tenant.
            'role_id' => [
                'sometimes', 'uuid',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('slug', '!=', 'super_admin')),
            ],
            'status' => ['sometimes', Rule::in([MembershipStatus::Active->value, MembershipStatus::Inactive->value])],
        ];
    }
}
