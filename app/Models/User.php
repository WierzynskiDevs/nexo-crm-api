<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Models\Concerns\HasUuidV7;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject, MustVerifyEmailContract
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuidV7, MustVerifyEmail, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    public function ownedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'owner_id');
    }

    public function ownedClients(): HasMany
    {
        return $this->hasMany(Client::class, 'owner_id');
    }

    public function ownedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'owner_id');
    }

    public function authSessions(): HasMany
    {
        return $this->hasMany(Session::class);
    }

    /**
     * Checagens de RBAC sempre tenant-scoped: um papel não existe "no
     * abstrato" para o usuário, só dentro da membership de um tenant
     * específico — nunca confiar em role/permissão vinda do cliente.
     */
    public function hasRole(string $roleSlug, Tenant $tenant): bool
    {
        return $this->activeMembershipQuery($tenant)
            ->whereHas('role', fn ($query) => $query->where('slug', $roleSlug))
            ->exists();
    }

    public function hasPermission(string $permissionSlug, Tenant $tenant): bool
    {
        return $this->activeMembershipQuery($tenant)
            ->whereHas('role.permissions', fn ($query) => $query->where('slug', $permissionSlug))
            ->exists();
    }

    private function activeMembershipQuery(Tenant $tenant): HasMany
    {
        return $this->memberships()
            ->where('tenant_id', $tenant->id)
            ->where('status', MembershipStatus::Active);
    }

    /**
     * O identificador do JWT é o UUID do usuário. Claims contextuais
     * (tenant_id, role) são adicionadas por token no momento da emissão
     * (ver App\Services\Auth\TokenService), não fixadas aqui.
     */
    public function getJWTIdentifier(): string
    {
        return (string) $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [];
    }
}
