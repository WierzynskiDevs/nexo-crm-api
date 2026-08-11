<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventGuestResponse;
use App\Models\Concerns\HasUuidV7;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventGuest extends Model
{
    use HasFactory, HasUuidV7;

    protected $fillable = [
        'event_id',
        'user_id',
        'name',
        'email',
        'response_status',
    ];

    protected $casts = [
        'response_status' => EventGuestResponse::class,
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
