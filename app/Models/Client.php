<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientHealth;
use App\Enums\ClientSegment;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use BelongsToTenant, HasFactory, HasUuidV7, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'workspace_id',
        'converted_from_lead_id',
        'owner_id',
        'name',
        'contact_name',
        'email',
        'phone',
        'mrr_cents',
        'health',
        'segment',
        'client_since',
        'archived_at',
    ];

    protected $casts = [
        'mrr_cents' => 'integer',
        'health' => ClientHealth::class,
        'segment' => ClientSegment::class,
        'client_since' => 'date',
        'archived_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function convertedFromLead(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'converted_from_lead_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(File::class, 'fileable');
    }
}
