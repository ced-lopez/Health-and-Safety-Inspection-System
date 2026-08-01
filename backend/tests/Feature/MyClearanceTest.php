<?php

namespace Tests\Feature;

use App\Models\Clearance;
use App\Models\Establishment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyClearanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $residentA;

    protected User $residentB;

    protected Establishment $estA;

    protected Establishment $estB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = $this->makeUser('administrator', 'myclradmin@example.com');
        $this->residentA = $this->makeUser('resident', 'myclrresA@example.com');
        $this->residentB = $this->makeUser('resident', 'myclrresB@example.com');

        $this->estA = Establishment::query()->create([
            'name' => 'Resident A Store',
            'business_type' => 'Retail',
            'owner_name' => 'Owner A',
            'address' => 'Barangay 178',
            'registration_number' => 'B178-MYA-001',
            'status' => 'active',
            'resident_id' => $this->residentA->id,
            'ownership_status' => 'linked',
        ]);

        $this->estB = Establishment::query()->create([
            'name' => 'Resident B Store',
            'business_type' => 'Retail',
            'owner_name' => 'Owner B',
            'address' => 'Barangay 178',
            'registration_number' => 'B178-MYB-001',
            'status' => 'active',
            'resident_id' => $this->residentB->id,
            'ownership_status' => 'linked',
        ]);
    }

    protected function makeClearance(Establishment $establishment, array $overrides = []): Clearance
    {
        return Clearance::query()->create(array_merge([
            'establishment_id' => $establishment->id,
            'issued_by' => $this->admin->id,
            'clearance_number' => 'CLR-MY-'.strtoupper(substr(uniqid(), -6)),
            'clearance_type' => 'Health and Safety Clearance',
            'purpose' => 'Business permit renewal',
            'issue_date' => now()->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
            'status' => 'active',
        ], $overrides));
    }

    public function test_guest_cannot_list_my_clearances(): void
    {
        $this->getJson('/api/v1/my/clearances')->assertStatus(401);
    }

    public function test_resident_sees_only_clearances_of_linked_establishments(): void
    {
        $own = $this->makeClearance($this->estA, ['clearance_number' => 'CLR-MY-OWN-001']);
        $other = $this->makeClearance($this->estB, ['clearance_number' => 'CLR-MY-OTH-001']);

        $response = $this->actingAs($this->residentA, 'sanctum')
            ->getJson('/api/v1/my/clearances?per_page=50');

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.total', 1);

        $numbers = collect($response->json('data.documents'))->pluck('number')->all();
        $this->assertContains($own->clearance_number, $numbers);
        $this->assertNotContains($other->clearance_number, $numbers);
    }

    public function test_resident_with_no_linked_establishment_gets_empty_list(): void
    {
        $this->makeClearance($this->estA);

        $unlinked = $this->makeUser('resident', 'myclrresC@example.com');

        $this->actingAs($unlinked, 'sanctum')
            ->getJson('/api/v1/my/clearances')
            ->assertStatus(200)
            ->assertJsonPath('data.meta.total', 0)
            ->assertJsonCount(0, 'data.documents');
    }

    public function test_resident_can_download_pdf_of_own_clearance(): void
    {
        $clearance = $this->makeClearance($this->estA);
        $clearance->qrCode()->create(['code' => 'CLR-MYP-0001', 'is_active' => true]);

        $response = $this->actingAs($this->residentA, 'sanctum')
            ->get("/api/v1/my/clearances/{$clearance->id}/pdf");

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('%PDF', substr($response->getContent(), 0, 10));
    }

    public function test_resident_cannot_download_pdf_of_another_residents_clearance(): void
    {
        $other = $this->makeClearance($this->estB);

        $this->actingAs($this->residentA, 'sanctum')
            ->get("/api/v1/my/clearances/{$other->id}/pdf")
            ->assertStatus(403);
    }

    public function test_guest_cannot_download_my_clearance_pdf(): void
    {
        $clearance = $this->makeClearance($this->estA);

        $this->getJson("/api/v1/my/clearances/{$clearance->id}/pdf")->assertStatus(401);
    }

    public function test_clearances_of_unlinked_pending_claim_are_hidden(): void
    {
        $clearance = $this->makeClearance($this->estA);

        $this->estA->update([
            'resident_id' => $this->residentA->id,
            'ownership_status' => 'pending',
        ]);

        $this->actingAs($this->residentA, 'sanctum')
            ->getJson('/api/v1/my/clearances')
            ->assertStatus(200)
            ->assertJsonPath('data.meta.total', 0);

        $this->actingAs($this->residentA, 'sanctum')
            ->get("/api/v1/my/clearances/{$clearance->id}/pdf")
            ->assertStatus(403);
    }

    public function test_staff_existing_management_routes_are_unaffected(): void
    {
        $this->makeClearance($this->estA);
        $staff = $this->makeUser('barangay_staff', 'myclrstaff@example.com');

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/v1/certifications?document_kind=clearance&per_page=50')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['documents', 'meta']]);
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
