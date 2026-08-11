<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use BelongsToTenant, HasFactory, HasUuidV7;

    protected $fillable = [
        'tenant_id',
        'created_by_user_id',
        'name',
        'prefix',
        'hashed_key',
        'scopes',
        'last_used_at',
        'revoked_at',
    ];

    protected $hidden = [
        'hashed_key',
    ];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * Gera um novo par (prefixo visível + segredo completo). O segredo completo
     * só existe neste retorno — apenas o hash é persistido no banco.
     */
    public static function generatePlainSecret(): array
    {
        $prefix = 'nx_'.Str::random(8);
        $secret = $prefix.'_'.Str::random(32);

        return [
            'prefix' => $prefix,
            'plain' => $secret,
            'hashed' => Hash::make($secret),
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
