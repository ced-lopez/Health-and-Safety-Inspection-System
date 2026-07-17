<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'document_kind' => 'certification',
            'id' => $this->id,
            'number' => $this->certificate_number,
            'document_type' => $this->certificate_type,
            'purpose' => null,
            'issue_date' => $this->issue_date?->toDateString(),
            'expiration_date' => $this->expiration_date?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'establishment_id' => $this->establishment_id,
            'inspection_id' => $this->inspection_id,
            'establishment' => new EstablishmentResource($this->whenLoaded('establishment')),
            'inspection' => $this->relationLoaded('inspection') && $this->inspection ? [
                'id' => $this->inspection->id,
                'inspection_date' => $this->inspection->inspection_date?->toDateString(),
                'status' => $this->inspection->status,
            ] : null,
            'issuer' => new UserResource($this->whenLoaded('issuer')),
            'qr_code' => new QrCodeResource($this->whenLoaded('qrCode')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
