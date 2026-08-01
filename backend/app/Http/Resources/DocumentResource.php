<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_type' => $this->document_type,
            'file_name' => $this->file_name,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'status' => $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'uploader' => new UserResource($this->whenLoaded('uploader')),
            'extraction' => new DocumentExtractionResource($this->whenLoaded('extraction')),
        ];
    }
}
