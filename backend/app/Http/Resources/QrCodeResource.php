<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QrCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'is_active' => $this->is_active,
            'verification_count' => $this->verification_count,
            'last_verified_at' => $this->last_verified_at?->toIso8601String(),
        ];
    }
}
