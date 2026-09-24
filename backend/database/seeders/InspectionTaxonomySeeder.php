<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InspectionTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $categories = [
            [
                'name' => 'Business Establishments',
                'slug' => 'business_establishments',
                'description' => 'Health and safety inspections for business establishments.',
                'requires_business_details' => true,
            ],
            [
                'name' => 'Piggery',
                'slug' => 'piggery',
                'description' => 'Health, sanitation, and nuisance inspections for piggery operations.',
                'requires_business_details' => false,
            ],
            [
                'name' => 'Poultry',
                'slug' => 'poultry',
                'description' => 'Health, sanitation, and nuisance inspections for poultry operations.',
                'requires_business_details' => false,
            ],
            [
                'name' => 'Animal Raising: Dogs',
                'slug' => 'animal_raising_dogs',
                'description' => 'Health and safety inspections for dog raising activities.',
                'requires_business_details' => false,
            ],
        ];

        foreach ($categories as $category) {
            DB::table('inspection_categories')->updateOrInsert(
                ['slug' => $category['slug']],
                [...$category, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $applicationTypes = [
            [
                'name' => 'New Application',
                'slug' => 'new_application',
                'description' => 'Initial barangay inspection request before clearance issuance.',
            ],
            [
                'name' => 'Renewal',
                'slug' => 'renewal',
                'description' => 'Renewal request for an existing clearance or permit cycle.',
            ],
        ];

        foreach ($applicationTypes as $type) {
            DB::table('application_types')->updateOrInsert(
                ['slug' => $type['slug']],
                [...$type, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        $categoryIds = DB::table('inspection_categories')->pluck('id', 'slug');
        $typeIds = DB::table('application_types')->pluck('id', 'slug');

        // Whitelist: only Valid Government ID and Proof of Residency Clearance remain active.
        // Barangay ID is removed per request; proof_of_location is renamed to Proof of Residency Clearance.
        $coreRequirements = [
            ['documentType' => 'government_id', 'documentName' => 'Valid Government-Issued ID', 'requiresExpirationCheck' => false],
            ['documentType' => 'proof_of_location', 'documentName' => 'Proof of Residency Clearance', 'requiresExpirationCheck' => false],
        ];

        // Archived: cedula, vicinity_map, previous_clearance and other legacy types are intentionally excluded.
        $renewalRequirements = [];

        foreach ($categoryIds as $categoryId) {
            foreach ($coreRequirements as $requirement) {
                $this->upsertRequirement(
                    inspectionCategoryId: $categoryId,
                    applicationTypeId: $typeIds['new_application'],
                    subPath: null,
                    documentType: $requirement['documentType'],
                    documentName: $requirement['documentName'],
                    requiresExpirationCheck: $requirement['requiresExpirationCheck'],
                    now: $now
                );

                $this->upsertRequirement(
                    inspectionCategoryId: $categoryId,
                    applicationTypeId: $typeIds['renewal'],
                    subPath: null,
                    documentType: $requirement['documentType'],
                    documentName: $requirement['documentName'],
                    requiresExpirationCheck: $requirement['requiresExpirationCheck'],
                    now: $now
                );
            }

            foreach ($renewalRequirements as $requirement) {
                $this->upsertRequirement(
                    inspectionCategoryId: $categoryId,
                    applicationTypeId: $typeIds['renewal'],
                    subPath: null,
                    documentType: $requirement['documentType'],
                    documentName: $requirement['documentName'],
                    requiresExpirationCheck: $requirement['requiresExpirationCheck'],
                    now: $now
                );
            }
        }

        // Category-specific requirements are archived: resident application now only requires
        // government_id + proof_of_location. No extra category docs are seeded.
        // Existing category-specific rules will be archived by migration 2026_09_25_archive_other_documents.
        // $this->seedCategoryRequirements($categoryIds, $typeIds, $now);

        // Clean up any previously seeded non-whitelisted rules (archive them) when seeder re-runs.
        // Also renames proof_of_location display name to Proof of Residency Clearance.
        $whitelisted = ['government_id', 'proof_of_location'];
        DB::table('document_requirement_rules')->whereNotIn('document_type', $whitelisted)->delete();
        DB::table('document_requirement_rules')->where('document_type', 'proof_of_location')->update(['document_name' => 'Proof of Residency Clearance']);
    }

    private function seedCategoryRequirements(Collection $categoryIds, Collection $typeIds, mixed $now): void
    {
        // Food safety / business establishments — flagged Commercial (requires_business_details).
        if (isset($categoryIds['business_establishments'])) {
            $foodDocs = [
                ['documentType' => 'dti_registration', 'documentName' => 'DTI Business Name Registration'],
                ['documentType' => 'sec_registration', 'documentName' => 'SEC Registration & Articles of Incorporation'],
                ['documentType' => 'health_certificate', 'documentName' => 'Notarized Health Certificates'],
                ['documentType' => 'establishment_photo', 'documentName' => 'Photos of the Establishment'],
            ];

            foreach ($foodDocs as $doc) {
                $this->upsertRequirement(
                    inspectionCategoryId: $categoryIds['business_establishments'],
                    applicationTypeId: $typeIds['new_application'],
                    subPath: null,
                    documentType: $doc['documentType'],
                    documentName: $doc['documentName'],
                    requiresExpirationCheck: false,
                    now: $now
                );
            }

            $this->upsertRequirement(
                inspectionCategoryId: $categoryIds['business_establishments'],
                applicationTypeId: $typeIds['renewal'],
                subPath: null,
                documentType: 'business_permit',
                documentName: 'Business Permit',
                requiresExpirationCheck: true,
                now: $now
            );
        }

        // Dog raising — two paths.
        if (isset($categoryIds['animal_raising_dogs'])) {
            $householdDocs = [
                ['documentType' => 'pet_registration_form', 'documentName' => 'Barangay Pet Registration Form'],
                ['documentType' => 'rabies_certificate', 'documentName' => 'Anti-Rabies Vaccination Certificate'],
            ];

            $kennelDocs = [
                ['documentType' => 'dti_registration', 'documentName' => 'DTI or SEC Registration'],
                ['documentType' => 'bai_registration', 'documentName' => 'BAI Registration (RA 8485)'],
                ['documentType' => 'hoa_clearance', 'documentName' => 'HOA Clearance'],
                ['documentType' => 'neighbor_waiver', 'documentName' => "Neighbor's Consent Waiver"],
            ];

            foreach ($householdDocs as $doc) {
                $this->upsertRequirement(
                    inspectionCategoryId: $categoryIds['animal_raising_dogs'],
                    applicationTypeId: $typeIds['new_application'],
                    subPath: 'household',
                    documentType: $doc['documentType'],
                    documentName: $doc['documentName'],
                    requiresExpirationCheck: false,
                    now: $now
                );
            }

            foreach ($kennelDocs as $doc) {
                $this->upsertRequirement(
                    inspectionCategoryId: $categoryIds['animal_raising_dogs'],
                    applicationTypeId: $typeIds['new_application'],
                    subPath: 'commercial_kennel',
                    documentType: $doc['documentType'],
                    documentName: $doc['documentName'],
                    requiresExpirationCheck: false,
                    now: $now
                );
            }
        }

        // Piggery & poultry — backyard micro-scale only.
        foreach (['piggery', 'poultry'] as $animalCategory) {
            if (! isset($categoryIds[$animalCategory])) {
                continue;
            }

            $backyardDocs = [
                ['documentType' => 'zoning_assessment', 'documentName' => 'Zoning & Locational Assessment'],
                ['documentType' => 'waste_management_plan', 'documentName' => 'Waste Management / Sanitation Plan'],
                ['documentType' => 'neighbor_waiver', 'documentName' => 'Signed No-Objection Waiver'],
            ];

            foreach ($backyardDocs as $doc) {
                $this->upsertRequirement(
                    inspectionCategoryId: $categoryIds[$animalCategory],
                    applicationTypeId: $typeIds['new_application'],
                    subPath: 'backyard_micro_scale',
                    documentType: $doc['documentType'],
                    documentName: $doc['documentName'],
                    requiresExpirationCheck: false,
                    now: $now
                );
            }
        }
    }

    private function upsertRequirement(
        int $inspectionCategoryId,
        int $applicationTypeId,
        ?string $subPath,
        string $documentType,
        string $documentName,
        bool $requiresExpirationCheck,
        mixed $now
    ): void {
        DB::table('document_requirement_rules')->updateOrInsert(
            [
                'inspection_category_id' => $inspectionCategoryId,
                'application_type_id' => $applicationTypeId,
                'sub_path' => $subPath,
                'document_type' => $documentType,
            ],
            [
                'document_name' => $documentName,
                'is_required' => true,
                'requires_expiration_check' => $requiresExpirationCheck,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }
}
