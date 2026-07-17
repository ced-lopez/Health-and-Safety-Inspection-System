<?php

namespace Tests\Feature;

use App\Models\Certification;
use App\Models\Establishment;
use App\Models\Inspection;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificationClearanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $healthOfficer;
    protected User $inspector;
    protected Establishment $establishment;
    protected Inspection $inspection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = $this->makeUser('administrator', 'admin@example.com');
        $this->healthOfficer = $this->makeUser('health_officer', 'health@example.com');
        $this->inspector = $this->makeUser('inspector', 'inspector@example.com');

        $this->establishment = Establishment::query()->create([
            'name' => 'Certified Store',
            'business_type' => 'Retail',
            'owner_name' => 'Owner',
            'address' => 'Barangay 178',
            'registration_number' => 'B178-CER-001',
            'status' => 'active',
        ]);

        $this->inspection = Inspection::query()->create([
            'establishment_id' => $this->establishment->id,
            'inspector_id' => $this->inspector->id,
            'inspection_date' => now()->toDateString(),
            'status' => 'completed',
        ]);
    }

    public function test_inspector_cannot_issue_certification(): void
    {
        $this->actingAs($this->inspector, 'sanctum')
            ->postJson('/api/v1/certifications', [])
            ->assertStatus(403);
    }

    public function test_health_officer_can_issue_certificate_with_qr_code(): void
    {
        $response = $this->actingAs($this->healthOfficer, 'sanctum')
            ->postJson('/api/v1/certifications', [
                'document_kind' => 'certification',
                'establishment_id' => $this->establishment->id,
                'inspection_id' => $this->inspection->id,
                'document_type' => 'Safety Compliance Certificate',
                'issue_date' => now()->toDateString(),
                'expiration_date' => now()->addYear()->toDateString(),
                'status' => 'active',
                'notes' => 'Approved after inspection review.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.document_kind', 'certification')
            ->assertJsonPath('data.document_type', 'Safety Compliance Certificate')
            ->assertJsonPath('data.qr_code.is_active', true);

        $this->assertDatabaseHas('certifications', [
            'establishment_id' => $this->establishment->id,
            'issued_by' => $this->healthOfficer->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseCount('qr_codes', 1);
    }

    public function test_admin_can_issue_clearance_and_update_status(): void
    {
        $create = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/certifications', [
                'document_kind' => 'clearance',
                'establishment_id' => $this->establishment->id,
                'inspection_id' => $this->inspection->id,
                'document_type' => 'Barangay Safety Clearance',
                'purpose' => 'Business permit renewal',
                'issue_date' => now()->toDateString(),
                'expiration_date' => now()->addMonths(6)->toDateString(),
                'status' => 'pending',
            ]);

        $clearanceId = $create->json('data.id');

        $this->actingAs($this->admin, 'sanctum')
            ->putJson("/api/v1/certifications/clearance/{$clearanceId}", [
                'establishment_id' => $this->establishment->id,
                'inspection_id' => $this->inspection->id,
                'document_type' => 'Barangay Safety Clearance',
                'purpose' => 'Business permit renewal',
                'issue_date' => now()->toDateString(),
                'expiration_date' => now()->addMonths(6)->toDateString(),
                'status' => 'active',
                'notes' => 'Released after review.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.purpose', 'Business permit renewal');
    }

    public function test_public_qr_verification_returns_document(): void
    {
        $certification = Certification::query()->create([
            'establishment_id' => $this->establishment->id,
            'inspection_id' => $this->inspection->id,
            'issued_by' => $this->admin->id,
            'certificate_number' => 'CERT-TEST-0001',
            'certificate_type' => 'Safety Compliance Certificate',
            'issue_date' => now()->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
            'status' => 'active',
        ]);

        $certification->qrCode()->create([
            'code' => 'CERT-VERIFY-123',
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/verify/CERT-VERIFY-123')
            ->assertStatus(200)
            ->assertJsonPath('data.document.number', 'CERT-TEST-0001')
            ->assertJsonPath('data.qr_code.verification_count', 1);
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
