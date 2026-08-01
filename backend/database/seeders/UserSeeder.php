<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::query()->where('slug', 'administrator')->first();
        $inspectorRole = Role::query()->where('slug', 'inspector')->first();
        $barangayStaffRole = Role::query()->where('slug', 'barangay_staff')->first();

        $users = [
            [
                'role_id' => $adminRole?->id,
                'name' => 'Maria Santos',
                'email' => 'admin@barangay178.gov.ph',
                'phone' => '09171234567',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'role_id' => $inspectorRole?->id,
                'name' => 'Juan Dela Cruz',
                'email' => 'inspector@barangay178.gov.ph',
                'phone' => '09181234567',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
            [
                'role_id' => $barangayStaffRole?->id,
                'name' => 'Ana Reyes',
                'email' => 'staff@barangay178.gov.ph',
                'phone' => '09191234567',
                'password' => Hash::make('password'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $user) {
            User::query()->updateOrCreate(['email' => $user['email']], $user);
        }
    }
}
