<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fileable_type' => $this->fileable_type,
            'fileable_id' => $this->fileable_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'uploaded_by' => new UserResource($this->whenLoaded('uploadedBy')),
            'created_at' => $this->created_at,
        ];
    }
}
