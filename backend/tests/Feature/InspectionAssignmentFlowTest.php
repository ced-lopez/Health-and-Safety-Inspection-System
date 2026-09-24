<?php

namespace Tests\Feature;

use App\Models\ApplicationType;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Establishment;
use App\Models\Inspection;
use App\Models\InspectionAssignment;
use App\Models\InspectionCategory;
use App\Models\InspectionRequest;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\InspectionTaxonomySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InspectionAssignmentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $resident;

    protected User $inspector;

    protected ChecklistItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, InspectionTaxonomySeeder::class]);

        $this->admin = $this->makeUser('administrator', 'assignadmin@example.com');
        $this->resident = $this->makeUser('resident', 'assignresident@example.com');
        $this->inspector = $this->makeUser('inspector', 'assigninspector@example.com');

        $checklist = Checklist::query()->create([
            'name' => 'Health Checklist',
            'category' => 'health_sanitation',
            'version' => 1,
            'is_active' => true,
            'created_by' => $this->admin->id,
        ]);

        $this->item = ChecklistItem::query()->create([
            'checklist_id' => $checklist->id,
            'category' => 'Cleanliness',
            'title' => 'Premises are clean',
            'sort_order' => 1,
            'is_required' => true,
        ]);
    }

    protected function makeWizardRequest(array $overrides = []): InspectionRequest
    {
        return InspectionRequest::query()->create(array_merge([
            'request_number' => 'REQ-'.strtoupper(substr(uniqid(), -6)),
            'resident_id' => $this->resident->id,
            'inspection_category_id' => InspectionCategory::where('slug', 'piggery')->firstOrFail()->id,
            'application_type_id' => ApplicationType::where('slug', 'new_application')->firstOrFail()->id,
            'applicant_name' => 'Juan Dela Cruz',
            'applicant_address' => 'Barangay 178',
            'contact_number' => '09171234567',
            'email' => 'juan@example.com',
            'business_name' => 'Piggery Farm',
            'status' => 'approved_for_inspection',
            'submitted_at' => now(),
        ], $overrides));
    }

    protected function makeAssignment(InspectionRequest $request, ?User $inspector = null): InspectionAssignment
    {
        return InspectionAssignment::query()->create([
            'inspection_request_id' => $request->id,
            'inspector_id' => ($inspector ?? $this->inspector)->id,
            'assigned_by' => $this->admin->id,
            'status' => 'assigned',
            'assigned_at' => now(),
            'server_version' => 1,
        ]);
    }

    public function test_inspector_can_start_assignment(): void
    {
        $assignment = $this->makeAssignment($this->makeWizardRequest());

        $response = $this->actingAs($this->inspector, 'sanctum')
            ->putJson("/api/v1/inspection-assignments/{$assignment->id}/start");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'in_progress');

        $this->assertDatabaseHas('inspection_assignments', [
            'id' => $assignment->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_other_inspector_cannot_start_assignment(): void
    {
        $other = $this->makeUser('inspector', 'assignotherstart@example.com');
        $assignment = $this->makeAssignment($this->makeWizardRequest());

        $this->actingAs($other, 'sanctum')
            ->putJson("/api/v1/inspection-assignments/{$assignment->id}/start")
            ->assertStatus(403);

        $this->assertDatabaseHas('inspection_assignments', [
            'id' => $assignment->id,
            'status' => 'assigned',
        ]);
    }

    public function test_other_inspector_cannot_submit_assignment(): void
    {
        $other = $this->makeUser('inspector', 'assignothersubmit@example.com');
        $assignment = $this->makeAssignment($this->makeWizardRequest());
        $assignment->update(['status' => 'in_progress']);

        $this->actingAs($other, 'sanctum')
            ->putJson("/api/v1/inspection-assignments/{$assignment->id}/submit", [
                'notes' => 'Done',
            ])
            ->assertStatus(403);

        $this->assertDatabaseHas('inspection_assignments', [
            'id' => $assignment->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_submit_before_start_returns_422(): void
    {
        $assignment = $this->makeAssignment($this->makeWizardRequest());

        $this->actingAs($this->inspector, 'sanctum')
            ->putJson("/api/v1/inspection-assignments/{$assignment->id}/submit", [
                'notes' => 'Not started',
            ])
            ->assertStatus(422);
    }

    public function test_submit_finalizes_inspection_and_request(): void
    {
        $establishment = Establishment::query()->create([
            'name' => 'Clearance Test Store',
            'business_type' => 'Retail',
            'owner_name' => 'Juan Dela Cruz',
            'address' => 'Barangay 178',
            'registration_number' => 'B178-CLR-001',
            'status' => 'active',
        ]);
        $request = $this->makeWizardRequest(['establishment_id' => $establishment->id]);
        $assignment = $this->makeAssignment($request);

        $this->actingAs($this->inspector, 'sanctum')
            ->putJson("/api/v1/inspection-assignments/{$assignment->id}/start")
            ->assertStatus(200);

        $this->actingAs($this->inspector, 'sanctum')
            ->putJson("/api/v1/inspection-assignments/{$assignment->id}/submit", [
                'notes' => 'All good',
                'outcome' => 'compliant',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('inspection_assignments', [
            'id' => $assignment->id,
            'status' => 'submitted',
        ]);

        $this->assertDatabaseHas('inspection_requests', [
            'id' => $request->id,
            'status' => 'inspection_completed',
        ]);

        $inspection = Inspection::query()
            ->where('inspection_request_id', $request->id)
            ->firstOrFail();

        $this->assertSame('completed', $inspection->status);
        $this->assertNotNull($inspection->completed_at);
    }

    public function test_or_numbered_clearance_payment_automatically_issues_qr_clearance(): void
    {
        $establishment = Establishment::query()->create([
            'name' => 'QR Clearance Test Store',
            'business_type' => 'Retail',
            'owner_name' => 'Juan Dela Cruz',
            'address' => 'Barangay 178',
            'registration_number' => 'B178-QR-001',
            'status' => 'active',
        ]);
        $request = $this->makeWizardRequest(['establishment_id' => $establishment->id]);
        $assignment = $this->makeAssignment($request);
        $staff = $this->makeUser('barangay_staff', 'paymentstaff@example.com');

        $this->actingAs($this->inspector, 'sanctum')
            ->putJson("/api/v1/inspection-assignments/{$assignment->id}/start");
        $this->actingAs($this->inspector, 'sanctum')
            ->putJson("/api/v1/inspection-assignments/{$assignment->id}/submit", ['outcome' => 'compliant']);

        $payment = Payment::query()->where('inspection_request_id', $request->id)->firstOrFail();
        $this->assertSame('pending', $payment->status);

        $this->actingAs($staff, 'sanctum')
            ->patchJson("/api/v1/payments/{$payment->id}/confirm", [
                'amount' => 150,
            ])
            ->assertOk()
            ->assertJsonPath('data.or_number', 'OR-2026-000001');

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'paid', 'reference_number' => 'OR-2026-000001']);
        $this->assertDatabaseHas('clearances', ['inspection_id' => $payment->inspection_id, 'status' => 'active']);
        $this->assertDatabaseHas('inspection_requests', ['id' => $request->id, 'status' => 'clearance_approved']);
    }

    public function test_inspector_can_load_checklist_for_request_without_establishment(): void
    {
        $assignment = $this->makeAssignment($this->makeWizardRequest());

        $response = $this->actingAs($this->inspector, 'sanctum')
            ->getJson("/api/v1/inspection-assignments/{$assignment->id}/checklist");

        $response->assertStatus(200);
        $response->assertJsonPath('data.checklists.0.items.0.title', 'Premises are clean');

        $inspectionId = $response->json('data.inspection.id');
        $this->assertNotNull($inspectionId);
        $this->assertDatabaseHas('inspections', [
            'id' => $inspectionId,
            'inspection_request_id' => $assignment->inspection_request_id,
            'establishment_id' => null,
        ]);
    }

    public function test_inspector_can_load_report_for_request_without_establishment(): void
    {
        $assignment = $this->makeAssignment($this->makeWizardRequest());

        $response = $this->actingAs($this->inspector, 'sanctum')
            ->getJson("/api/v1/inspection-assignments/{$assignment->id}/report");

        $response->assertStatus(200);
        $response->assertJsonPath('data.establishment', null);
        $response->assertJsonPath('data.summary.total_items', 0);
        $this->assertNotNull($response->json('data.id'));
    }

    public function test_inspector_can_save_checklist_for_request_without_establishment(): void
    {
        $assignment = $this->makeAssignment($this->makeWizardRequest());

        $response = $this->actingAs($this->inspector, 'sanctum')
            ->postJson("/api/v1/inspection-assignments/{$assignment->id}/checklist", [
                'results' => [
                    [
                        'checklist_item_id' => $this->item->id,
                        'compliance_status' => 'compliant',
                        'remarks' => 'Looks good',
                    ],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('inspection_results', [
            'checklist_item_id' => $this->item->id,
            'compliance_status' => 'compliant',
        ]);
    }

    public function test_inspector_can_load_checklist_for_request_with_establishment(): void
    {
        $establishment = Establishment::query()->create([
            'name' => 'Checklist Store',
            'business_type' => 'Retail',
            'owner_name' => 'Owner',
            'address' => 'Barangay 178',
            'registration_number' => 'B178-ASG-001',
            'status' => 'active',
        ]);

        $request = $this->makeWizardRequest(['establishment_id' => $establishment->id]);
        $assignment = $this->makeAssignment($request);

        $response = $this->actingAs($this->inspector, 'sanctum')
            ->getJson("/api/v1/inspection-assignments/{$assignment->id}/checklist");

        $response->assertStatus(200);
        $this->assertDatabaseHas('inspections', [
            'establishment_id' => $establishment->id,
            'inspector_id' => $this->inspector->id,
        ]);
    }

    public function test_other_inspector_cannot_access_assignment(): void
    {
        $other = $this->makeUser('inspector', 'assignother@example.com');
        $assignment = $this->makeAssignment($this->makeWizardRequest());

        $this->actingAs($other, 'sanctum')
            ->getJson("/api/v1/inspection-assignments/{$assignment->id}/checklist")
            ->assertStatus(403);
    }

    public function test_checklist_reuses_existing_request_inspection(): void
    {
        $request = $this->makeWizardRequest();
        $assignment = $this->makeAssignment($request);

        $this->actingAs($this->inspector, 'sanctum')
            ->getJson("/api/v1/inspection-assignments/{$assignment->id}/checklist")
            ->assertStatus(200);

        $inspectionCount = Inspection::query()
            ->where('inspection_request_id', $request->id)
            ->count();

        $this->actingAs($this->inspector, 'sanctum')
            ->getJson("/api/v1/inspection-assignments/{$assignment->id}/checklist")
            ->assertStatus(200);

        $this->assertSame(1, $inspectionCount);
        $this->assertSame(1, Inspection::query()
            ->where('inspection_request_id', $request->id)
            ->count());
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
