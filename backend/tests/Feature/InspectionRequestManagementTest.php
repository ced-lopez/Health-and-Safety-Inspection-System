<?php

namespace Tests\Feature;

use App\Models\ApplicationType;
use App\Models\Document;
use App\Models\DocumentRequirementRule;
use App\Models\InspectionCategory;
use App\Models\InspectionRequest;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\InspectionTaxonomySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InspectionRequestManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    protected User $resident;

    protected User $inspector;

    protected InspectionRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, InspectionTaxonomySeeder::class]);

        $this->staff = $this->makeUser('barangay_staff', 'reqstaff@example.com');
        $this->resident = $this->makeUser('resident', 'reqresident@example.com');
        $this->inspector = $this->makeUser('inspector', 'reqinspector@example.com');

        $this->request = $this->makeRequest([
            'status' => 'submitted',
        ]);
    }

    protected function makeRequest(array $overrides = []): InspectionRequest
    {
        $category = InspectionCategory::where('slug', 'business_establishments')->firstOrFail();
        $appType = ApplicationType::where('slug', 'new_application')->firstOrFail();

        return InspectionRequest::query()->create(array_merge([
            'request_number' => 'REQ-'.strtoupper(substr(uniqid(), -6)),
            'resident_id' => $this->resident->id,
            'inspection_category_id' => $category->id,
            'application_type_id' => $appType->id,
            'applicant_name' => 'Juan Dela Cruz',
            'applicant_address' => 'Barangay 178',
            'contact_number' => '09171234567',
            'email' => 'juan@example.com',
            'business_name' => 'Sari-Sari Store',
            'status' => 'submitted',
            'submitted_at' => now(),
        ], $overrides));
    }

    public function test_guest_cannot_list_inspection_requests(): void
    {
        $this->getJson('/api/v1/inspection-requests')->assertStatus(401);
    }

    public function test_resident_only_sees_own_requests(): void
    {
        $other = $this->makeUser('resident', 'other@example.com');
        $this->makeRequest([
            'resident_id' => $other->id,
            'business_name' => 'Other Store',
        ]);

        $response = $this->actingAs($this->resident, 'sanctum')
            ->getJson('/api/v1/inspection-requests');

        $response->assertStatus(200);
        $items = $response->json('data.inspection_requests');
        $this->assertCount(1, $items);
        $this->assertSame($this->request->id, $items[0]['id']);
    }

    public function test_staff_can_search_requests(): void
    {
        $this->makeRequest([
            'business_name' => 'Piggery Farm',
            'inspection_category_id' => InspectionCategory::where('slug', 'piggery')->firstOrFail()->id,
        ]);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/inspection-requests?search=piggery');

        $response->assertStatus(200);
        $items = $response->json('data.inspection_requests');
        $this->assertCount(1, $items);
        $this->assertSame('Piggery Farm', $items[0]['business_name']);
    }

    public function test_staff_can_start_review(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/v1/inspection-requests/{$this->request->id}/review", [
                'status' => 'under_review',
            ]);

        $response->assertStatus(200)->assertJsonPath('data.status', 'under_review');
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'inspection_request.reviewed',
            'auditable_id' => $this->request->id,
        ]);
    }

    public function test_staff_can_return_incomplete_requirements(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/v1/inspection-requests/{$this->request->id}/review", [
                'status' => 'requirements_incomplete',
                'remarks' => 'Missing business permit',
            ]);

        $response->assertStatus(200)->assertJsonPath('data.status', 'requirements_incomplete');
        $this->assertSame('Missing business permit', $this->request->fresh()->remarks);
    }

    public function test_staff_can_approve_request_for_inspection(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/v1/inspection-requests/{$this->request->id}/review", [
                'status' => 'approved_for_inspection',
            ]);

        $response->assertStatus(200)->assertJsonPath('data.status', 'approved_for_inspection');
    }

    public function test_staff_can_reject_request(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/v1/inspection-requests/{$this->request->id}/review", [
                'status' => 'rejected',
                'remarks' => 'Duplicate application',
            ]);

        $response->assertStatus(200)->assertJsonPath('data.status', 'rejected');
    }

    public function test_review_rejects_unknown_status(): void
    {
        $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/v1/inspection-requests/{$this->request->id}/review", [
                'status' => 'approved',
            ])
            ->assertStatus(422);
    }

    public function test_resident_cannot_review_request(): void
    {
        $this->actingAs($this->resident, 'sanctum')
            ->putJson("/api/v1/inspection-requests/{$this->request->id}/review", [
                'status' => 'under_review',
            ])
            ->assertStatus(403);
    }

    public function test_requirements_endpoint_reports_missing_documents(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson("/api/v1/inspection-requests/{$this->request->id}/requirements");

        $response->assertStatus(200);
        $summary = $response->json('data.summary');
        $this->assertGreaterThan(0, $summary['required_count']);
        $this->assertSame(0, $summary['uploaded_count']);
        $this->assertFalse($summary['complete']);
    }

    public function test_requirements_endpoint_detects_uploaded_documents(): void
    {
        $requiredTypes = DocumentRequirementRule::query()
            ->where('inspection_category_id', $this->request->inspection_category_id)
            ->where('application_type_id', $this->request->application_type_id)
            ->where('is_required', true)
            ->get()
            ->pluck('document_type')
            ->all();

        foreach ($requiredTypes as $documentType) {
            $this->makeDocument($documentType);
        }

        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson("/api/v1/inspection-requests/{$this->request->id}/requirements");

        $response->assertStatus(200);
        $summary = $response->json('data.summary');
        $this->assertSame(count($requiredTypes), $summary['required_count']);
        $this->assertSame(count($requiredTypes), $summary['uploaded_count']);
        $this->assertTrue($summary['complete']);

        $items = collect($response->json('data.requirements'));
        $barangay = $items->firstWhere('document_type', 'barangay_id');
        $this->assertTrue($barangay['uploaded']);
    }

    public function test_staff_can_assign_inspector_to_approved_request(): void
    {
        $this->request->update(['status' => 'approved_for_inspection']);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/inspection-requests/{$this->request->id}/assign", [
                'inspector_id' => $this->inspector->id,
            ]);

        $response->assertStatus(200)->assertJsonPath('data.status', 'assigned');
        $this->assertDatabaseHas('inspection_assignments', [
            'inspection_request_id' => $this->request->id,
            'inspector_id' => $this->inspector->id,
            'status' => 'assigned',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'inspection_request.assigned',
            'auditable_id' => $this->request->id,
        ]);
    }

    public function test_assign_rejected_for_non_approved_request(): void
    {
        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/inspection-requests/{$this->request->id}/assign", [
                'inspector_id' => $this->inspector->id,
            ])
            ->assertStatus(422);
    }

    public function test_assign_rejects_inactive_inspector(): void
    {
        $this->request->update(['status' => 'approved_for_inspection']);
        $this->inspector->update(['is_active' => false]);

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/inspection-requests/{$this->request->id}/assign", [
                'inspector_id' => $this->inspector->id,
            ])
            ->assertStatus(404);
    }

    public function test_queue_returns_approved_and_assigned_requests(): void
    {
        $this->makeRequest(['status' => 'approved_for_inspection', 'business_name' => 'Queue Store']);
        $this->makeRequest(['status' => 'submitted', 'business_name' => 'Not In Queue']);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/inspection-requests/queue');

        $response->assertStatus(200);
        $items = $response->json('data.queue');
        $this->assertCount(1, $items);
        $this->assertSame('Queue Store', $items[0]['business_name']);
    }

    public function test_queue_accessible_only_by_staff(): void
    {
        $this->actingAs($this->resident, 'sanctum')
            ->getJson('/api/v1/inspection-requests/queue')
            ->assertStatus(403);
    }

    protected function makeUser(string $roleSlug, string $email): User
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        return User::query()->create([
            'name' => ucfirst(str_replace('_', ' ', $roleSlug)),
            'email' => $email,
            'password' => bcrypt('password'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    protected function makeDocument(string $documentType): Document
    {
        return Document::query()->create([
            'documentable_type' => InspectionRequest::class,
            'documentable_id' => $this->request->id,
            'uploaded_by' => $this->resident->id,
            'document_type' => $documentType,
            'file_path' => 'documents/test.png',
            'file_name' => 'test.png',
            'original_name' => 'test.png',
            'mime_type' => 'image/png',
            'file_size' => 1024,
            'status' => 'pending',
        ]);
    }
}
