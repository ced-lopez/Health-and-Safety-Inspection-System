<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InspectionRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'request_number' => $this->request_number,
            'applicant_name' => $this->applicant_name,
            'applicant_age' => $this->applicant_age,
            'applicant_address' => $this->applicant_address,
            'contact_number' => $this->contact_number,
            'email' => $this->email,
            'business_name' => $this->business_name,
            'remarks' => $this->remarks,
            'sub_path' => $this->sub_path,
            'declared_animal_count' => $this->declared_animal_count,
            'status' => $this->status,
            'documents_count' => $this->whenHas('documents_count'),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'resident' => new UserResource($this->whenLoaded('resident')),
            'inspection_category' => new InspectionCategoryResource($this->whenLoaded('inspectionCategory')),
            'application_type' => new ApplicationTypeResource($this->whenLoaded('applicationType')),
            'establishment' => new EstablishmentResource($this->whenLoaded('establishment')),
            'reviewed_by' => new UserResource($this->whenLoaded('reviewedBy')),
            'documents' => DocumentResource::collection($this->whenLoaded('documents')),
            'inspection_assignment' => new InspectionAssignmentResource($this->whenLoaded('inspectionAssignment')),
        ];
    }
}
