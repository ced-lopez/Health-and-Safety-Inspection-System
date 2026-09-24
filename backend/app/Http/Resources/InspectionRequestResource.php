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
            'preferred_schedule_at' => $this->preferred_schedule_at?->toIso8601String(),
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
            'schedules' => InspectionScheduleResource::collection($this->whenLoaded('schedules')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'payment_status' => $this->paymentStatus(),
        ];
    }

    private function paymentStatus(): array
    {
        if (array_key_exists('application_fee_paid', $this->resource->getAttributes())
            || array_key_exists('clearance_fee_paid', $this->resource->getAttributes())) {
            return [
                'application_fee_paid' => (bool) $this->application_fee_paid,
                'clearance_fee_paid' => (bool) $this->clearance_fee_paid,
            ];
        }

        $payments = $this->relationLoaded('payments') ? $this->payments : collect();

        return [
            'application_fee_paid' => $payments->contains(
                fn ($payment) => $payment->type === 'application_fee' && $payment->status === 'paid'
            ),
            'clearance_fee_paid' => $payments->contains(
                fn ($payment) => $payment->type === 'clearance_fee' && $payment->status === 'paid'
            ),
        ];
    }
}
