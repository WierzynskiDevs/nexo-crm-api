<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OpportunityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pipeline_id' => $this->pipeline_id,
            'pipeline_stage_id' => $this->pipeline_stage_id,
            'lead_id' => $this->lead_id,
            'client_id' => $this->client_id,
            'name' => $this->name,
            'value_cents' => $this->value_cents,
            'probability' => $this->probability,
            'expected_close_date' => $this->expected_close_date,
            'status' => $this->status,
            'lost_reason' => $this->lost_reason,
            'closed_at' => $this->closed_at,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
