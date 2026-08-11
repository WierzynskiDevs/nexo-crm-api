<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use BelongsToTenant, HasFactory, HasUuidV7, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'lead_user_id',
        'pipeline_id',
        'name',
        'goal_amount_cents',
    ];

    protected $casts = [
        'goal_amount_cents' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function leadUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_user_id');
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')->withTimestamps();
    }
}
