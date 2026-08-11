<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventGuestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name ?? $this->whenLoaded('user', fn () => $this->user?->name),
            'email' => $this->email ?? $this->whenLoaded('user', fn () => $this->user?->email),
            'response_status' => $this->response_status,
        ];
    }
}
