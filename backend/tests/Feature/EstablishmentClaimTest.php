<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstablishmentClaimTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $staff;

    protected User $residentA;

    protected User $residentB;

    protected Establishment $unclaimed;

    protected Establishment $linked;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = $this->makeUser('administrator', 'claimadmin@example.com');
        $this->staff = $this->makeUser('barangay_staff', 'claimstaff@example.com');
        $this->residentA = $this->makeUser('resident', 'claimresA@example.com');
        $this->residentB = $this->makeUser('resident', 'claimresB@example.com');

        $this->unclaimed = Establishment::query()->create([
            'name' => 'Unclaimed Grocery',
            'business_type' => 'Grocery',
            'owner_name' => 'Claim Owner',
            'address' => 'Barangay 178',
            'registration_number' => 'B178-CLM-001',
            'status' => 'active',
            'ownership_status' => 'unclaimed',
        ]);

        $this->linked = Establishment::query()->create([
            'name' => 'Linked Sari-Sari',
            'business_type' => 'Retail',
            'owner_name' => 'Linked Owner',
            'address' => 'Barangay 178',
            'registration_number' => 'B178-CLM-002',
            'status' => 'active',
            'resident_id' => $this->residentB->id,
            'ownership_status' => 'linked',
        ]);
    }

    public function test_resident_can_claim_unclaimed_establishment(): void
    {
        $response = $this->actingAs($this->residentA, 'sanctum')
            ->postJson("/api/v1/my/establishments/{$this->unclaimed->id}/claim");

        $response->assertStatus(202)
            ->assertJsonPath('data.ownership_status', 'pending');

        $this->assertDatabaseHas('establishments', [
            'id' => $this->unclaimed->id,
            'resident_id' => $this->residentA->id,
            'ownership_status' => 'pending',
        ]);
    }

    public function test_resident_cannot_claim_linked_establishment(): void
    {
        $this->actingAs($this->residentA, 'sanctum')
            ->postJson("/api/v1/my/establishments/{$this->linked->id}/claim")
            ->assertStatus(422);
    }

    public function test_resident_cannot_claim_already_pending_establishment(): void
    {
        $this->unclaimed->update([
            'resident_id' => $this->residentB->id,
            'ownership_status' => 'pending',
        ]);

        $this->actingAs($this->residentA, 'sanctum')
            ->postJson("/api/v1/my/establishments/{$this->unclaimed->id}/claim")
            ->assertStatus(422);
    }

    public function test_second_resident_claim_is_rejected_atomically(): void
    {
        $this->actingAs($this->residentA, 'sanctum')
            ->postJson("/api/v1/my/establishments/{$this->unclaimed->id}/claim")
            ->assertStatus(202);

        $this->actingAs($this->residentB, 'sanctum')
            ->postJson("/api/v1/my/establishments/{$this->unclaimed->id}/claim")
            ->assertStatus(422);

        $this->assertDatabaseHas('establishments', [
            'id' => $this->unclaimed->id,
            'resident_id' => $this->residentA->id,
            'ownership_status' => 'pending',
        ]);
    }

    public function test_unclaimed_list_excludes_linked_and_pending(): void
    {
        $this->unclaimed->update([
            'resident_id' => $this->residentB->id,
            'ownership_status' => 'pending',
        ]);

        $response = $this->actingAs($this->residentA, 'sanctum')
            ->getJson('/api/v1/my/establishments/unclaimed');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($this->unclaimed->id, $ids);
        $this->assertNotContains($this->linked->id, $ids);
    }

    public function test_staff_can_list_pending_claims(): void
    {
        $this->unclaimed->update([
            'resident_id' => $this->residentA->id,
            'ownership_status' => 'pending',
        ]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/establishment-claims');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertSame($this->unclaimed->id, $response->json('data.0.id'));
    }

    public function test_staff_can_approve_pending_claim(): void
    {
        $this->unclaimed->update([
            'resident_id' => $this->residentA->id,
            'ownership_status' => 'pending',
        ]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/establishment-claims/{$this->unclaimed->id}/approve");

        $response->assertStatus(200)->assertJsonPath('data.ownership_status', 'linked');
        $this->assertDatabaseHas('establishments', [
            'id' => $this->unclaimed->id,
            'resident_id' => $this->residentA->id,
            'ownership_status' => 'linked',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'establishment_claim.approved',
            'auditable_id' => $this->unclaimed->id,
        ]);
    }

    public function test_staff_can_reject_pending_claim(): void
    {
        $this->unclaimed->update([
            'resident_id' => $this->residentA->id,
            'ownership_status' => 'pending',
        ]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/establishment-claims/{$this->unclaimed->id}/reject");

        $response->assertStatus(200)->assertJsonPath('data.ownership_status', 'unclaimed');
        $this->assertDatabaseHas('establishments', [
            'id' => $this->unclaimed->id,
            'resident_id' => null,
            'ownership_status' => 'unclaimed',
        ]);
    }

    public function test_approve_rejects_establishment_without_pending_claim(): void
    {
        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/establishment-claims/{$this->unclaimed->id}/approve")
            ->assertStatus(422);
    }

    public function test_resident_cannot_approve_claims(): void
    {
        $this->unclaimed->update([
            'resident_id' => $this->residentA->id,
            'ownership_status' => 'pending',
        ]);

        $this->actingAs($this->residentA, 'sanctum')
            ->postJson("/api/v1/establishment-claims/{$this->unclaimed->id}/approve")
            ->assertStatus(403);

        $this->actingAs($this->residentA, 'sanctum')
            ->getJson('/api/v1/establishment-claims')
            ->assertStatus(403);
    }

    public function test_resident_can_list_own_establishments(): void
    {
        $this->unclaimed->update([
            'resident_id' => $this->residentA->id,
            'ownership_status' => 'pending',
        ]);

        $response = $this->actingAs($this->residentA, 'sanctum')
            ->getJson('/api/v1/my/establishments');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->unclaimed->id, $ids);
        $this->assertNotContains($this->linked->id, $ids);
    }

    private function makeUser(string $roleSlug, string $email): User
    {
        $role = Role::query()->where('slug', $roleSlug)->first();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => ucfirst(str_replace('_', ' ', $roleSlug)),
            'email' => $email,
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
    }
}
