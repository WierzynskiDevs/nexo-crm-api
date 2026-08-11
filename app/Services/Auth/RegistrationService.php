<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\MembershipStatus;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Cadastro self-service: cria uma nova empresa (tenant) junto com o primeiro
 * usuário, que se torna Admin dessa empresa. Não é usado para aceitar
 * convites de um tenant já existente (ver InviteController).
 */
class RegistrationService
{
    /**
     * @return array{user: User, tenant: Tenant, role: Role}
     */
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::query()->create([
                'name' => $data['company_name'],
                'slug' => $this->uniqueSlug($data['company_name']),
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(14),
            ]);

            Workspace::query()->create([
                'tenant_id' => $tenant->id,
                'name' => 'Principal',
                'slug' => 'principal',
                'is_default' => true,
            ]);

            Setting::query()->create(['tenant_id' => $tenant->id]);

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $adminRole = Role::query()->where('slug', 'admin')->firstOrFail();

            Membership::query()->create([
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'role_id' => $adminRole->id,
                'status' => MembershipStatus::Active,
                'joined_at' => now(),
            ]);

            return ['user' => $user, 'tenant' => $tenant, 'role' => $adminRole];
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);

        do {
            $slug = $base.'-'.Str::lower(Str::random(4));
        } while (Tenant::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
