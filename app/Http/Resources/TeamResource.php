<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'goal_amount_cents' => $this->goal_amount_cents,
            'pipeline_id' => $this->pipeline_id,
            'lead' => new UserResource($this->whenLoaded('leadUser')),
            'members' => UserResource::collection($this->whenLoaded('members')),
            'created_at' => $this->created_at,
        ];
    }
}
