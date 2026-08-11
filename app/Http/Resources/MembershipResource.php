<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'invited_at' => $this->invited_at,
            'joined_at' => $this->joined_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'role' => new RoleResource($this->whenLoaded('role')),
        ];
    }
}
