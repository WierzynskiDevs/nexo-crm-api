<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventKind;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use BelongsToTenant, HasFactory, HasUuidV7, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'owner_id',
        'title',
        'kind',
        'starts_at',
        'ends_at',
        'location',
        'notes',
        'related_type',
        'related_id',
        'canceled_at',
    ];

    protected $casts = [
        'kind' => EventKind::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function guests(): HasMany
    {
        return $this->hasMany(EventGuest::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
