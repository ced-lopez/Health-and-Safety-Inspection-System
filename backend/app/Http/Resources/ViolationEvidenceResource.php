<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ViolationEvidenceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'violation_id' => $this->violation_id,
            'file_path' => $this->file_path,
            'file_url' => asset(Storage::url($this->file_path)),
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'evidence_type' => $this->evidence_type,
            'description' => $this->description,
            'uploader' => new UserResource($this->whenLoaded('uploader')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
