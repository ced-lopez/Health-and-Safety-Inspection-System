<?php

namespace Tests\Feature;

use App\Models\ApplicationType;
use App\Models\Clearance;
use App\Models\Establishment;
use App\Models\Inspection;
use App\Models\InspectionCategory;
use App\Models\InspectionRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\Violation;
use Database\Seeders\InspectionTaxonomySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InspectionRequestStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected User $resident;

    protected User $barangayStaff;

    protected User $inspector;

    protected Establishment $establishment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, InspectionTaxonomySeeder::class]);

        $residentRole = Role::where('slug', 'resident')->firstOrFail();

        $this->resident = User::query()->create([
            'role_id' => $residentRole->id,
            'name' => 'Transition Resident',
            'email' => 'transition@example.com',
            'password' => bcrypt('Password123!'),
            'is_active' => true,
        ]);

        $this->barangayStaff = $this->makeUser('barangay_staff', 'staff@example.com');
        $this->inspector = $this->makeUser('inspector', 'inspector@example.com');

        $this->establishment = Establishment::query()->create([
            'name' => 'Transition Store',
            'business_type' => 'Retail',
            'owner_name' => 'Owner',
            'address' => 'Barangay 178',
            'registration_number' => 'B178-TRN-001',
            'status' => 'active',
        ]);
    }

    private function makeCompletedRequest(): InspectionRequest
    {
        $inspectionRequest = InspectionRequest::query()->create([
            'request_number' => 'REQ-'.now()->format('Ymd').'-'.random_int(100, 999),
            'resident_id' => $this->resident->id,
            'inspection_category_id' => InspectionCategory::where('slug', 'piggery')->firstOrFail()->id,
            'application_type_id' => ApplicationType::where('slug', 'new_application')->firstOrFail()->id,
            'sub_path' => 'backyard_micro_scale',
            'declared_animal_count' => 2,
            'establishment_id' => $this->establishment->id,
            'applicant_name' => 'Maria Santos',
            'applicant_address' => 'Barangay 178, Caloocan City',
            'contact_number' => '09171234567',
            'email' => 'maria@example.com',
            'business_name' => 'Transition Store',
            'status' => 'inspection_completed',
        ]);

        Inspection::query()->create([
            'inspection_request_id' => $inspectionRequest->id,
            'establishment_id' => $this->establishment->id,
            'inspector_id' => $this->inspector->id,
            'inspection_date' => now()->toDateString(),
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $inspectionRequest;
    }

    public function test_violation_for_completed_request_moves_request_to_violation_notice_issued(): void
    {
        $inspectionRequest = $this->makeCompletedRequest();
        $inspection = Inspection::where('inspection_request_id', $inspectionRequest->id)->firstOrFail();

        $response = $this->actingAs($this->inspector, 'sanctum')
            ->postJson('/api/v1/violations', [
                'inspection_id' => $inspection->id,
                'assigned_to' => $this->barangayStaff->id,
                'title' => 'No fire extinguisher',
                'description' => 'Fire extinguisher missing near exit.',
                'severity' => 'major',
                'status' => 'open',
                'correction_deadline' => now()->addDays(7)->toDateString(),
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('inspection_requests', [
            'id' => $inspectionRequest->id,
            'status' => 'violation_notice_issued',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->resident->id,
            'type' => 'App\Notifications\ViolationNotice',
        ]);
    }

    public function test_active_clearance_moves_completed_request_to_clearance_approved(): void
    {
        $inspectionRequest = $this->makeCompletedRequest();
        $inspection = Inspection::where('inspection_request_id', $inspectionRequest->id)->firstOrFail();

        $response = $this->actingAs($this->barangayStaff, 'sanctum')
            ->postJson('/api/v1/certifications', [
                'document_kind' => 'clearance',
                'establishment_id' => $this->establishment->id,
                'inspection_id' => $inspection->id,
                'document_type' => 'Barangay Safety Clearance',
                'purpose' => 'Business permit renewal',
                'issue_date' => now()->toDateString(),
                'expiration_date' => now()->addYear()->toDateString(),
                'status' => 'active',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('inspection_requests', [
            'id' => $inspectionRequest->id,
            'status' => 'clearance_approved',
        ]);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->resident->id,
            'type' => 'App\Notifications\ClearanceApproved',
        ]);
    }

    public function test_pending_clearance_does_not_advance_request_status(): void
    {
        $inspectionRequest = $this->makeCompletedRequest();
        $inspection = Inspection::where('inspection_request_id', $inspectionRequest->id)->firstOrFail();

        $this->actingAs($this->barangayStaff, 'sanctum')
            ->postJson('/api/v1/certifications', [
                'document_kind' => 'clearance',
                'establishment_id' => $this->establishment->id,
                'inspection_id' => $inspection->id,
                'document_type' => 'Barangay Safety Clearance',
                'purpose' => 'Business permit renewal',
                'issue_date' => now()->toDateString(),
                'expiration_date' => now()->addYear()->toDateString(),
                'status' => 'pending',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('inspection_requests', [
            'id' => $inspectionRequest->id,
            'status' => 'inspection_completed',
        ]);
    }

    public function test_approve_pending_clearance_moves_request_to_clearance_approved(): void
    {
        $inspectionRequest = $this->makeCompletedRequest();
        $inspection = Inspection::where('inspection_request_id', $inspectionRequest->id)->firstOrFail();

        $clearance = Clearance::query()->create([
            'establishment_id' => $this->establishment->id,
            'inspection_id' => $inspection->id,
            'issued_by' => $this->barangayStaff->id,
            'clearance_number' => 'CLR-APPROVE-001',
            'clearance_type' => 'Barangay Safety Clearance',
            'purpose' => 'Business permit renewal',
            'issue_date' => now()->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
            'status' => 'pending',
        ]);

        $this->actingAs($this->barangayStaff, 'sanctum')
            ->postJson("/api/v1/certifications/clearance/{$clearance->id}/approve")
            ->assertStatus(200);

        $this->assertDatabaseHas('inspection_requests', [
            'id' => $inspectionRequest->id,
            'status' => 'clearance_approved',
        ]);
    }

    public function test_violation_for_unlinked_inspection_leaves_request_untouched(): void
    {
        $unlinked = Inspection::query()->create([
            'establishment_id' => $this->establishment->id,
            'inspector_id' => $this->inspector->id,
            'inspection_date' => now()->toDateString(),
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->actingAs($this->inspector, 'sanctum')
            ->postJson('/api/v1/violations', [
                'inspection_id' => $unlinked->id,
                'assigned_to' => $this->barangayStaff->id,
                'title' => 'Blocked emergency exit',
                'description' => 'Rear exit blocked.',
                'severity' => 'major',
                'status' => 'open',
                'correction_deadline' => now()->addDays(7)->toDateString(),
            ])
            ->assertStatus(201);

        $this->assertDatabaseCount('inspection_requests', 0);
    }

    public function test_violation_on_closed_request_does_not_downgrade_to_violation_notice_issued(): void
    {
        $inspectionRequest = $this->makeCompletedRequest();
        $inspection = Inspection::where('inspection_request_id', $inspectionRequest->id)->firstOrFail();

        $inspectionRequest->update(['status' => 'clearance_approved']);

        $violation = Violation::query()->create([
            'inspection_id' => $inspection->id,
            'establishment_id' => $this->establishment->id,
            'reported_by' => $this->inspector->id,
            'assigned_to' => $this->barangayStaff->id,
            'title' => 'Late violation',
            'description' => 'Found after clearance.',
            'severity' => 'major',
            'status' => 'open',
        ]);

        $this->actingAs($this->barangayStaff, 'sanctum')
            ->putJson("/api/v1/violations/{$violation->id}", [
                'inspection_id' => $inspection->id,
                'assigned_to' => $this->barangayStaff->id,
                'title' => $violation->title,
                'description' => $violation->description,
                'severity' => 'major',
                'status' => 'open',
                'correction_deadline' => now()->addDays(7)->toDateString(),
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('inspection_requests', [
            'id' => $inspectionRequest->id,
            'status' => 'clearance_approved',
        ]);
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