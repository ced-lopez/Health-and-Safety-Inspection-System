<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inspection_request_id' => $this->inspection_request_id,
            'inspection_id' => $this->inspection_id,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'status' => $this->status,
            'or_number' => $this->reference_number,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'confirmed_by' => new UserResource($this->whenLoaded('confirmedBy')),
            'inspection_request' => $this->whenLoaded('inspectionRequest', function () {
                return [
                    'id' => $this->inspectionRequest->id,
                    'request_number' => $this->inspectionRequest->request_number,
                    'business_name' => $this->inspectionRequest->business_name,
                    'applicant_name' => $this->inspectionRequest->applicant_name,
                    'inspection_category' => $this->inspectionRequest->relationLoaded('inspectionCategory') && $this->inspectionRequest->inspectionCategory
                        ? ['name' => $this->inspectionRequest->inspectionCategory->name]
                        : null,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
