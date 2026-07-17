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
