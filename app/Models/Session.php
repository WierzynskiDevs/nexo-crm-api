<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sessão de autenticação (dispositivo/refresh token do JWT) — não é a sessão
 * web do Laravel.
 */
class Session extends Model
{
    use HasFactory, HasUuidV7;

    protected $fillable = [
        'user_id',
        'tenant_id',
        'refresh_token_hash',
        'user_agent',
        'ip_address',
        'last_used_at',
        'expires_at',
        'revoked_at',
    ];

    protected $hidden = [
        'refresh_token_hash',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
