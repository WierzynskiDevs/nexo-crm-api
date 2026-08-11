<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'title' => $this->title,
            'kind' => $this->kind,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'location' => $this->location,
            'notes' => $this->notes,
            'related_type' => $this->related_type,
            'related_id' => $this->related_id,
            'canceled_at' => $this->canceled_at,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'guests' => EventGuestResource::collection($this->whenLoaded('guests')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
