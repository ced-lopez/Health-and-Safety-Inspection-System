<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_admin_can_update_user(): void
    {
        $adminRole = Role::query()->where('slug', 'administrator')->first();
        $admin = User::query()->create([
            'role_id' => $adminRole->id,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $targetRole = Role::query()->where('slug', 'inspector')->first();
        $target = User::query()->create([
            'role_id' => $targetRole->id,
            'name' => 'Target User',
            'email' => 'target@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->putJson('/api/v1/admin/users/'.$target->id, [
            'role' => 'barangay_staff',
            'is_active' => false,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['is_active' => false]);
    }

    public function test_admin_can_update_email_and_password(): void
    {
        $adminRole = Role::query()->where('slug', 'administrator')->first();
        $admin = User::query()->create([
            'role_id' => $adminRole->id,
            'name' => 'Admin',
            'email' => 'admin2@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $targetRole = Role::query()->where('slug', 'inspector')->first();
        $target = User::query()->create([
            'role_id' => $targetRole->id,
            'name' => 'Target User',
            'email' => 'target@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $token = $admin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->putJson('/api/v1/admin/users/'.$target->id, [
            'email' => 'new.target@example.com',
            'password' => 'NewPassword123!',
            'role' => 'inspector',
            'is_active' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['email' => 'new.target@example.com']);

        $target->refresh();
        $this->assertEquals('new.target@example.com', $target->email);
        $this->assertTrue(Hash::check('NewPassword123!', $target->password));
    }

    public function test_admin_can_manage_resident_accounts(): void
    {
        $adminRole = Role::query()->where('slug', 'administrator')->first();
        $admin = User::query()->create([
            'role_id' => $adminRole->id,
            'name' => 'Admin',
            'email' => 'admin3@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $residentRole = Role::query()->where('slug', 'resident')->first();
        $resident = User::query()->create([
            'role_id' => $residentRole->id,
            'name' => 'Resident User',
            'email' => 'resident@example.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $token = $admin->createToken('test-token')->plainTextToken;

        $listResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/admin/users');

        $listResponse->assertStatus(200)
            ->assertJsonFragment(['email' => 'resident@example.com']);

        $updateResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->putJson('/api/v1/admin/users/'.$resident->id, [
            'email' => 'resident.updated@example.com',
            'role' => 'resident',
            'is_active' => false,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonFragment(['email' => 'resident.updated@example.com']);
    }
}
