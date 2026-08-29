<?php

namespace Database\Seeders;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class ChecklistSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@barangay178.gov.ph')->first();

        $templates = [
            // Category-specific compliance checklists. The establishment
            // category determines which checklist template is applicable
            // during inspection.
            [
                'name' => 'Food Establishment Checklist',
                'description' => 'Health and sanitation requirements for food establishments',
                'category' => 'food_establishment',
                'items' => [
                    ['category' => 'Food Handling', 'title' => 'Food handlers follow proper food handling and preparation practices'],
                    ['category' => 'Food Handling', 'title' => 'Food handlers possess valid health certificates'],
                    ['category' => 'Sanitation', 'title' => 'Kitchen and storage areas are clean and sanitized'],
                    ['category' => 'Sanitation', 'title' => 'Refrigeration and storage temperatures are properly maintained'],
                    ['category' => 'Water Supply', 'title' => 'Safe and potable water supply is available'],
                    ['category' => 'Water Supply', 'title' => 'Water storage tanks are clean, covered, and regularly maintained'],
                    ['category' => 'Waste Disposal', 'title' => 'Proper waste segregation and disposal are implemented'],
                    ['category' => 'Waste Disposal', 'title' => 'Waste containers are covered, labeled, and regularly emptied'],
                    ['category' => 'Pest Control', 'title' => 'Effective pest control measures are in place'],
                    ['category' => 'Pest Control', 'title' => 'No evidence of pest infestation on premises'],
                    ['category' => 'Health Certificates', 'title' => 'Health certificates of food handlers are current and displayed'],
                    ['category' => 'Sanitary Facilities', 'title' => 'Restrooms and handwashing facilities with soap are available'],
                ],
            ],
            [
                'name' => 'Piggery Checklist',
                'description' => 'Health, sanitation, and nuisance requirements for piggery operations',
                'category' => 'piggery',
                'items' => [
                    ['category' => 'Animal Housing', 'title' => 'Animal housing is structurally sound and adequately sized for the number of animals'],
                    ['category' => 'Animal Housing', 'title' => 'Housing is cleaned and maintained to prevent disease'],
                    ['category' => 'Waste / Manure Management', 'title' => 'Manure is collected, stored, and disposed of properly'],
                    ['category' => 'Waste / Manure Management', 'title' => 'Waste management plan is implemented and effective'],
                    ['category' => 'Drainage', 'title' => 'Drainage systems are clear and properly directed away from neighbors and waterways'],
                    ['category' => 'Drainage', 'title' => 'No stagnant water or waste pooling on the property'],
                    ['category' => 'Odor Control', 'title' => 'Odor control measures are in place to minimize nuisance to neighbors'],
                    ['category' => 'Sanitation', 'title' => 'Premises are kept clean and free from excessive flies and rodents'],
                ],
            ],
            [
                'name' => 'Poultry Checklist',
                'description' => 'Health, sanitation, and nuisance requirements for poultry operations',
                'category' => 'poultry',
                'items' => [
                    ['category' => 'Poultry Housing', 'title' => 'Poultry housing is secure, clean, and adequately sized'],
                    ['category' => 'Poultry Housing', 'title' => 'Housing provides proper protection from weather and predators'],
                    ['category' => 'Waste Management', 'title' => 'Poultry waste/litter is collected and disposed of properly'],
                    ['category' => 'Waste Management', 'title' => 'Litter is managed to prevent odor and pest buildup'],
                    ['category' => 'Ventilation', 'title' => 'Housing is adequately ventilated'],
                    ['category' => 'Ventilation', 'title' => 'Ventilation minimizes ammonia and moisture buildup'],
                    ['category' => 'Sanitation', 'title' => 'Premises are kept clean and regularly sanitized'],
                    ['category' => 'Pest / Vector Control', 'title' => 'Effective pest and vector control measures are in place'],
                ],
            ],
            [
                'name' => 'Dog Raising / Kennel Checklist',
                'description' => 'Health, sanitation, and animal welfare requirements for dog raising and kennel operations',
                'category' => 'dog_raising_kennel',
                'items' => [
                    ['category' => 'Kennel Sanitation', 'title' => 'Kennel facility is clean and regularly sanitized'],
                    ['category' => 'Kennel Sanitation', 'title' => 'Kennels and pens are free from waste buildup'],
                    ['category' => 'Animal Housing', 'title' => 'Housing is secure, adequately sized, and protects animals from weather'],
                    ['category' => 'Animal Housing', 'title' => 'Housing provides adequate space, shelter, and clean water'],
                    ['category' => 'Waste Disposal', 'title' => 'Animal waste is properly collected and disposed of'],
                    ['category' => 'Waste Disposal', 'title' => 'Waste disposal does not cause nuisance to neighboring properties'],
                    ['category' => 'Number of Animals', 'title' => 'Number of animals is appropriate for the facility size'],
                    ['category' => 'Vaccination / Rabies Documents', 'title' => 'Anti-rabies vaccination certificates are complete and current'],
                    ['category' => 'Vaccination / Rabies Documents', 'title' => 'Animal registration and BAI documentation (if applicable) are present'],
                    ['category' => 'Odor and Sanitation Concerns', 'title' => 'Odor control measures are in place to minimize nuisance'],
                    ['category' => 'Odor and Sanitation Concerns', 'title' => 'No sanitation or odor concerns that affect neighboring properties'],
                ],
            ],
            // Core cross-cutting checklists retained for all categories.
            [
                'name' => 'Health and Sanitation Checklist',
                'description' => 'Standard health and sanitation requirements for establishments',
                'category' => 'health_sanitation',
                'items' => [
                    ['category' => 'Cleanliness', 'title' => 'Premises are clean and free from pests'],
                    ['category' => 'Cleanliness', 'title' => 'Food preparation areas are sanitized'],
                    ['category' => 'Waste Disposal', 'title' => 'Proper waste segregation is implemented'],
                    ['category' => 'Waste Disposal', 'title' => 'Waste containers are covered and labeled'],
                    ['category' => 'Sanitary Facilities', 'title' => 'Restrooms are clean and functional'],
                    ['category' => 'Sanitary Facilities', 'title' => 'Handwashing facilities with soap are available'],
                    ['category' => 'Water Safety', 'title' => 'Potable water supply is available'],
                    ['category' => 'Water Safety', 'title' => 'Water storage is clean and covered'],
                ],
            ],
            [
                'name' => 'Fire Safety Checklist',
                'description' => 'Fire safety compliance requirements',
                'category' => 'fire_safety',
                'items' => [
                    ['category' => 'Fire Extinguishers', 'title' => 'Fire extinguishers are present and accessible'],
                    ['category' => 'Fire Extinguishers', 'title' => 'Fire extinguishers are inspected and tagged'],
                    ['category' => 'Emergency Exits', 'title' => 'Emergency exits are clearly marked'],
                    ['category' => 'Emergency Exits', 'title' => 'Emergency exits are unobstructed'],
                    ['category' => 'Safety Signs', 'title' => 'Fire exit signs are visible'],
                    ['category' => 'Safety Signs', 'title' => 'No smoking signs are posted where required'],
                ],
            ],
            [
                'name' => 'Workplace Safety Checklist',
                'description' => 'Workplace safety and hazard prevention requirements',
                'category' => 'workplace_safety',
                'items' => [
                    ['category' => 'Equipment Safety', 'title' => 'Equipment is properly maintained'],
                    ['category' => 'Equipment Safety', 'title' => 'Safety guards are in place on machinery'],
                    ['category' => 'Hazard Prevention', 'title' => 'Hazardous materials are properly stored'],
                    ['category' => 'Hazard Prevention', 'title' => 'Spill containment measures are in place'],
                    ['category' => 'Safety Procedures', 'title' => 'First aid kit is available and stocked'],
                    ['category' => 'Safety Procedures', 'title' => 'Emergency contact numbers are displayed'],
                ],
            ],
        ];

        foreach ($templates as $index => $template) {
            $checklist = Checklist::query()->updateOrCreate(
                ['name' => $template['name']],
                [
                    'description' => $template['description'],
                    'category' => $template['category'],
                    'version' => 1,
                    'is_active' => true,
                    'created_by' => $admin?->id,
                ]
            );

            foreach ($template['items'] as $itemIndex => $item) {
                ChecklistItem::query()->updateOrCreate(
                    [
                        'checklist_id' => $checklist->id,
                        'title' => $item['title'],
                    ],
                    [
                        'category' => $item['category'],
                        'description' => null,
                        'sort_order' => $itemIndex + 1,
                        'is_required' => true,
                    ]
                );
            }
        }
    }
}