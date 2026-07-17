<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Administrator',
                'slug' => 'administrator',
                'description' => 'Full system access and user management',
            ],
            [
                'name' => 'Health Officer',
                'slug' => 'health_officer',
                'description' => 'Oversees health inspections and certifications',
            ],
            [
                'name' => 'Inspector',
                'slug' => 'inspector',
                'description' => 'Conducts field inspections and compliance checks',
            ],
            [
                'name' => 'Staff',
                'slug' => 'staff',
                'description' => 'Barangay staff with limited access',
            ],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
