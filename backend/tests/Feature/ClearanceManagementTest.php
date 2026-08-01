<?php

namespace Tests\Feature;

use App\Models\Clearance;
use App\Models\Establishment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearanceManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $barangayStaff;

    protected Establishment $establishment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = $this->makeUser('administrator', 'clradmin@example.com');
        $this->barangayStaff = $this->makeUser('barangay_staff', 'clrstaff@example.com');

        $this->establishment = Establishment::query()->create([
            'name' => 'Clearance Store',
            'business_type' => 'Retail',
            'owner_name' => 'Maria Santos',
            'address' => 'Barangay 178',
            'registration_number' => 'B178-CLR-001',
            'status' => 'active',
        ]);
    }

    protected function makeClearance(array $overrides = []): Clearance
    {
        return Clearance::query()->create(array_merge([
            'establishment_id' => $this->establishment->id,
            'issued_by' => $this->admin->id,
            'clearance_number' => 'CLR-TEST-'.strtoupper(substr(uniqid(), -6)),
            'clearance_type' => 'Health and Safety Clearance',
            'purpose' => 'Business permit renewal',
            'issue_date' => now()->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
            'status' => 'active',
        ], $overrides));
    }

    public function test_guest_cannot_download_clearance_pdf(): void
    {
        $clearance = $this->makeClearance();

        $this->getJson("/api/v1/certifications/clearance/{$clearance->id}/pdf")->assertStatus(401);
    }

    public function test_staff_can_download_clearance_pdf(): void
    {
        $clearance = $this->makeClearance();
        $clearance->qrCode()->create(['code' => 'CLR-PDF-0001', 'is_active' => true]);

        $response = $this->actingAs($this->barangayStaff, 'sanctum')
            ->get("/api/v1/certifications/clearance/{$clearance->id}/pdf");

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('%PDF', substr($response->getContent(), 0, 10));
    }

    public function test_admin_can_approve_pending_clearance(): void
    {
        $clearance = $this->makeClearance(['status' => 'pending']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/certifications/clearance/{$clearance->id}/approve");

        $response->assertStatus(200)->assertJsonPath('data.status', 'active');
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'clearance.approved',
            'auditable_id' => $clearance->id,
        ]);
    }

    public function test_approve_rejects_non_pending_clearance(): void
    {
        $clearance = $this->makeClearance(['status' => 'revoked']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/certifications/clearance/{$clearance->id}/approve")
            ->assertStatus(422);
    }

    public function test_staff_can_revoke_active_clearance_and_deactivate_qr(): void
    {
        $clearance = $this->makeClearance();
        $clearance->qrCode()->create(['code' => 'CLR-REV-0001', 'is_active' => true]);

        $response = $this->actingAs($this->barangayStaff, 'sanctum')
            ->postJson("/api/v1/certifications/clearance/{$clearance->id}/revoke");

        $response->assertStatus(200)->assertJsonPath('data.status', 'revoked');
        $this->assertFalse($clearance->qrCode->fresh()->is_active);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'clearance.revoked',
            'auditable_id' => $clearance->id,
        ]);
    }

    public function test_renew_creates_new_clearance_and_expires_old(): void
    {
        $clearance = $this->makeClearance(['status' => 'active']);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/v1/certifications/clearance/{$clearance->id}/renew");

        $response->assertStatus(201);
        $renewedId = $response->json('data.id');
        $this->assertNotSame($clearance->id, $renewedId);
        $this->assertSame('active', $response->json('data.status'));
        $this->assertSame('expired', $clearance->fresh()->status);

        $this->assertDatabaseHas('clearances', [
            'id' => $renewedId,
            'establishment_id' => $this->establishment->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'clearance.renewed',
            'auditable_id' => $renewedId,
        ]);
    }

    public function test_clearance_verification_after_revocation_fails(): void
    {
        $clearance = $this->makeClearance();
        $qr = $clearance->qrCode()->create(['code' => 'CLR-VER-0001', 'is_active' => true]);

        $this->actingAs($this->barangayStaff, 'sanctum')
            ->postJson("/api/v1/certifications/clearance/{$clearance->id}/revoke")
            ->assertStatus(200);

        $this->getJson('/api/v1/verify/CLR-VER-0001')->assertStatus(404);
    }

    public function test_expiration_command_marks_overdue_clearances(): void
    {
        $this->makeClearance(['expiration_date' => now()->subDay()->toDateString(), 'status' => 'active']);
        $this->makeClearance(['expiration_date' => now()->addMonth()->toDateString(), 'status' => 'active']);

        $this->artisan('documents:check-expiration')->assertSuccessful();

        $this->assertSame(1, Clearance::query()->where('status', 'expired')->count());
        $this->assertSame(1, Clearance::query()->where('status', 'active')->count());
    }

    public function test_resident_cannot_manage_documents(): void
    {
        $resident = $this->makeUser('resident', 'clrresident@example.com');
        $clearance = $this->makeClearance(['status' => 'pending']);

        $this->actingAs($resident, 'sanctum')
            ->postJson("/api/v1/certifications/clearance/{$clearance->id}/approve")
            ->assertStatus(403);
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
