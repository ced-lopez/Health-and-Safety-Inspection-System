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
                'name' => 'Barangay Staff',
                'slug' => 'barangay_staff',
                'description' => 'Reviews applications, assigns inspectors, manages clearances',
            ],
            [
                'name' => 'Inspector',
                'slug' => 'inspector',
                'description' => 'Conducts field inspections and compliance checks',
            ],
            [
                'name' => 'Resident',
                'slug' => 'resident',
                'description' => 'Registered resident who submits inspection requests',
            ],
        ];

        foreach ($roles as $role) {
            Role::query()->updateOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
