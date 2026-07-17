<?php

namespace Database\Seeders;

use App\Models\Establishment;
use Illuminate\Database\Seeder;

class EstablishmentSeeder extends Seeder
{
    public function run(): void
    {
        $establishments = [
            [
                'name' => 'Sunrise Mini Mart',
                'business_type' => 'Retail Store',
                'owner_name' => 'Roberto Garcia',
                'address' => '123 M.H. Del Pilar St., Barangay 178, North Caloocan City',
                'contact_number' => '09201234567',
                'email' => 'sunrise.mmart@email.com',
                'registration_number' => 'B178-RET-001',
                'status' => 'active',
            ],
            [
                'name' => 'Green Valley Restaurant',
                'business_type' => 'Food Establishment',
                'owner_name' => 'Elena Mendoza',
                'address' => '45 Katipunan Ave., Barangay 178, North Caloocan City',
                'contact_number' => '09211234567',
                'email' => 'greenvalley.restaurant@email.com',
                'registration_number' => 'B178-FNB-002',
                'status' => 'active',
            ],
            [
                'name' => 'North Caloocan Pharmacy',
                'business_type' => 'Pharmacy',
                'owner_name' => 'Dr. Miguel Torres',
                'address' => '78 Quirino Highway, Barangay 178, North Caloocan City',
                'contact_number' => '09221234567',
                'email' => 'nc.pharmacy@email.com',
                'registration_number' => 'B178-PHR-003',
                'status' => 'active',
            ],
            [
                'name' => '178 Auto Repair Shop',
                'business_type' => 'Automotive Service',
                'owner_name' => 'Carlos Villanueva',
                'address' => '12 Industrial Road, Barangay 178, North Caloocan City',
                'contact_number' => '09231234567',
                'registration_number' => 'B178-AUT-004',
                'status' => 'pending',
            ],
            [
                'name' => 'Barangay 178 Daycare Center',
                'business_type' => 'Educational Facility',
                'owner_name' => 'Barangay 178 LGU',
                'address' => 'Barangay Hall Compound, Barangay 178, North Caloocan City',
                'contact_number' => '09241234567',
                'registration_number' => 'B178-EDU-005',
                'status' => 'active',
            ],
        ];

        foreach ($establishments as $establishment) {
            Establishment::query()->updateOrCreate(
                ['registration_number' => $establishment['registration_number']],
                $establishment
            );
        }
    }
}
