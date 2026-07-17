<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstablishmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_cannot_access_establishments(): void
    {
        $this->getJson('/api/v1/establishments')->assertStatus(401);
        $this->postJson('/api/v1/establishments', [])->assertStatus(401);
    }

    public function test_staff_cannot_access_establishments(): void
    {
        $staffRole = Role::query()->where('slug', 'staff')->first();
        $user = User::query()->create([
            'role_id' => $staffRole->id,
            'name' => 'Staff User',
            'email' => 'staff@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/establishments')
            ->assertStatus(403);
    }

    public function test_inspector_can_list_but_not_create_establishments(): void
    {
        $inspectorRole = Role::query()->where('slug', 'inspector')->first();
        $user = User::query()->create([
            'role_id' => $inspectorRole->id,
            'name' => 'Inspector User',
            'email' => 'inspector@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        // Assert read allowed
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/establishments')
            ->assertStatus(200);

        // Assert write blocked
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/establishments', [
            'name' => 'Test Est',
            'business_type' => 'Retail',
            'owner_name' => 'Owner',
            'address' => 'Addr',
            'registration_number' => 'B178-TST-001',
            'status' => 'active',
        ])->assertStatus(403);
    }

    public function test_health_officer_can_create_but_not_delete_establishments(): void
    {
        $healthOfficerRole = Role::query()->where('slug', 'health_officer')->first();
        $user = User::query()->create([
            'role_id' => $healthOfficerRole->id,
            'name' => 'Health Officer User',
            'email' => 'health@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        // Create establishment
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/establishments', [
            'name' => 'Valid Est',
            'business_type' => 'Food',
            'owner_name' => 'Jane Owner',
            'address' => '456 St.',
            'registration_number' => 'B178-VAL-001',
            'status' => 'active',
        ]);

        $response->assertStatus(201);
        $establishmentId = $response->json('data.id');

        // Delete establishment should be blocked (only administrator can delete)
        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/establishments/{$establishmentId}")
            ->assertStatus(403);
    }

    public function test_administrator_can_delete_establishments(): void
    {
        $adminRole = Role::query()->where('slug', 'administrator')->first();
        $user = User::query()->create([
            'role_id' => $adminRole->id,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $establishment = Establishment::query()->create([
            'name' => 'Admin Test Est',
            'business_type' => 'Service',
            'owner_name' => 'Owner Name',
            'address' => '123 St',
            'registration_number' => 'B178-ADM-001',
            'status' => 'pending',
        ]);

        $this->actingAs($user, 'sanctum')->deleteJson("/api/v1/establishments/{$establishment->id}")
            ->assertStatus(200);

        // Verify soft-deleted
        $this->assertSoftDeleted('establishments', [
            'id' => $establishment->id,
        ]);
    }

    public function test_establishment_unique_registration_number_validation(): void
    {
        $adminRole = Role::query()->where('slug', 'administrator')->first();
        $user = User::query()->create([
            'role_id' => $adminRole->id,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        Establishment::query()->create([
            'name' => 'Existing Est',
            'business_type' => 'Store',
            'owner_name' => 'Owner A',
            'address' => 'Addr A',
            'registration_number' => 'REG-1234',
            'status' => 'active',
        ]);

        // Attempting to create duplicate registration number should fail
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/establishments', [
            'name' => 'New Est',
            'business_type' => 'Store',
            'owner_name' => 'Owner B',
            'address' => 'Addr B',
            'registration_number' => 'REG-1234',
            'status' => 'active',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['registration_number']);
    }
}
