<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory, HasUuidV7;

    protected $fillable = [
        'slug',
        'name',
        'price_cents',
        'seats_limit',
        'storage_limit_mb',
        'features',
        'is_active',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'seats_limit' => 'integer',
        'storage_limit_mb' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
