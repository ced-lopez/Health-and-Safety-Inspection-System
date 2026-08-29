<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EstablishmentResource extends JsonResource
{
    public const CATEGORY_LABELS = [
        'food_establishment' => 'Food Establishment',
        'piggery' => 'Piggery',
        'poultry' => 'Poultry',
        'dog_raising_kennel' => 'Dog Raising / Kennel',
    ];

    public static function categoryLabel(?string $category): ?string
    {
        return self::CATEGORY_LABELS[$category] ?? $category;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'business_type' => $this->business_type,
            'category' => $this->category,
            'category_label' => self::categoryLabel($this->category),
            'owner_name' => $this->owner_name,
            'address' => $this->address,
            'barangay' => $this->barangay,
            'contact_number' => $this->contact_number,
            'email' => $this->email,
            'registration_number' => $this->registration_number,
            'status' => $this->status,
            'resident_id' => $this->resident_id,
            'ownership_status' => $this->ownership_status,
            'resident' => $this->whenLoaded('resident', fn () => [
                'id' => $this->resident->id,
                'name' => $this->resident->name,
            ]),
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'inspections_count' => $this->whenCounted('inspections'),
            'violations_count' => $this->whenCounted('violations'),
            'open_violations_count' => $this->whenCounted('openViolations'),
            'documents_count' => $this->whenCounted('documents'),
            'certifications_count' => $this->whenCounted('certifications'),
            'clearances_count' => $this->whenCounted('clearances'),
            'last_inspection_date' => $this->whenAggregated(
                'inspections',
                'inspection_date',
                'max',
                $this->inspections_max_inspection_date,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}