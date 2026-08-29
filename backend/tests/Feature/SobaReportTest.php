<?php

namespace Tests\Feature;

use App\Models\ApplicationType;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Clearance;
use App\Models\Establishment;
use App\Models\Inspection;
use App\Models\InspectionCategory;
use App\Models\InspectionRequest;
use App\Models\InspectionResult;
use App\Models\Role;
use App\Models\User;
use App\Models\Violation;
use Database\Seeders\InspectionTaxonomySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SobaReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    protected Establishment $establishment;

    protected User $inspector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, InspectionTaxonomySeeder::class]);
        $this->staff = $this->makeUser('barangay_staff', 'staff@example.com');
        $this->inspector = $this->makeUser('inspector', 'inspector@example.com');

        $this->establishment = Establishment::query()->create([
            'name' => 'SOBA Store',
            'business_type' => 'Retail',
            'owner_name' => 'Owner',
            'address' => 'Barangay 178',
            'registration_number' => 'B178-SOBA-001',
            'status' => 'active',
        ]);
    }

    public function test_guest_cannot_access_soba_report(): void
    {
        $this->getJson('/api/v1/reports/soba')->assertStatus(401);
    }

    public function test_soba_returns_semester_period_and_aggregates(): void
    {
        $residentRole = Role::where('slug', 'resident')->firstOrFail();

        $resident = User::query()->create([
            'role_id' => $residentRole->id,
            'name' => 'Soba Resident',
            'email' => 'soba@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $piggery = InspectionCategory::where('slug', 'piggery')->firstOrFail();
        $newApplication = ApplicationType::where('slug', 'new_application')->firstOrFail();

        $request = InspectionRequest::query()->create([
            'request_number' => 'REQ-SOBA-001',
            'resident_id' => $resident->id,
            'inspection_category_id' => $piggery->id,
            'application_type_id' => $newApplication->id,
            'sub_path' => 'backyard_micro_scale',
            'declared_animal_count' => 2,
            'establishment_id' => $this->establishment->id,
            'applicant_name' => 'Maria Santos',
            'applicant_address' => 'Barangay 178, Caloocan City',
            'contact_number' => '09171234567',
            'email' => 'maria@example.com',
            'business_name' => 'SOBA Store',
            'status' => 'clearance_approved',
        ]);
        $request->created_at = now()->firstOfYear()->addMonths(1)->startOfMonth();
        $request->save();

        $inspection = Inspection::query()->create([
            'inspection_request_id' => $request->id,
            'establishment_id' => $this->establishment->id,
            'inspector_id' => $this->inspector->id,
            'inspection_date' => now()->firstOfYear()->addMonths(2)->toDateString(),
            'status' => 'completed',
            'completed_at' => now()->firstOfYear()->addMonths(2)->startOfMonth(),
        ]);
        $inspection->created_at = now()->firstOfYear()->addMonths(2)->startOfMonth();
        $inspection->save();

        $checklist = Checklist::query()->create([
            'name' => 'Piggery Checklist',
            'category' => 'piggery',
            'version' => 1,
            'is_active' => true,
            'created_by' => $this->inspector->id,
        ]);

        $itemOne = ChecklistItem::query()->create([
            'checklist_id' => $checklist->id,
            'category' => 'Sanitation',
            'title' => 'Waste management is adequate',
            'sort_order' => 1,
            'is_required' => true,
        ]);

        $itemTwo = ChecklistItem::query()->create([
            'checklist_id' => $checklist->id,
            'category' => 'Structural',
            'title' => 'Proper housing and shelter',
            'sort_order' => 2,
            'is_required' => true,
        ]);

        $resultOne = InspectionResult::query()->create([
            'inspection_id' => $inspection->id,
            'checklist_item_id' => $itemOne->id,
            'compliance_status' => 'compliant',
            'assessed_by' => $this->inspector->id,
        ]);
        $resultOne->created_at = now()->firstOfYear()->addMonths(2)->startOfMonth();
        $resultOne->save();

        $resultTwo = InspectionResult::query()->create([
            'inspection_id' => $inspection->id,
            'checklist_item_id' => $itemTwo->id,
            'compliance_status' => 'non_compliant',
            'assessed_by' => $this->inspector->id,
        ]);
        $resultTwo->created_at = now()->firstOfYear()->addMonths(2)->startOfMonth();
        $resultTwo->save();

        $violation = Violation::query()->create([
            'inspection_id' => $inspection->id,
            'establishment_id' => $this->establishment->id,
            'reported_by' => $this->inspector->id,
            'assigned_to' => $this->staff->id,
            'title' => 'No fire exit',
            'description' => 'Fire exit blocked.',
            'severity' => 'major',
            'status' => 'open',
        ]);
        $violation->created_at = now()->firstOfYear()->addMonths(2)->startOfMonth();
        $violation->save();

        $clearance = Clearance::query()->create([
            'establishment_id' => $this->establishment->id,
            'inspection_id' => $inspection->id,
            'issued_by' => $this->staff->id,
            'clearance_number' => 'CLR-SOBA-001',
            'clearance_type' => 'Barangay Safety Clearance',
            'issue_date' => now()->firstOfYear()->addMonths(3)->toDateString(),
            'expiration_date' => now()->addYear()->toDateString(),
            'status' => 'active',
        ]);
        $clearance->created_at = now()->firstOfYear()->addMonths(3)->startOfMonth();
        $clearance->save();

        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/reports/soba?year='.now()->year.'&semester=1')
            ->assertStatus(200);

        $response->assertJsonPath('success', true)
            ->assertJsonPath('data.period.label', '1st Semester '.now()->year)
            ->assertJsonPath('data.requests.total', 1)
            ->assertJsonPath('data.requests.by_category.0.category', 'Piggery')
            ->assertJsonPath('data.requests.by_category.0.total', 1)
            ->assertJsonPath('data.inspections.completed', 1)
            ->assertJsonPath('data.violations.total', 1)
            ->assertJsonPath('data.violations.by_severity.major', 1)
            ->assertJsonPath('data.compliance.total_checks', 2)
            ->assertJsonPath('data.compliance.compliant', 1)
            ->assertJsonPath('data.compliance.compliance_rate', 50)
            ->assertJsonPath('data.clearances.issued', 1)
            ->assertJsonPath('data.clearances.by_status.active', 1);
    }

    public function test_soba_rejects_invalid_semester(): void
    {
        $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/reports/soba?semester=3')
            ->assertStatus(422);
    }

    public function test_soba_returns_empty_totals_when_no_data(): void
    {
        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/reports/soba?year='.now()->year.'&semester=1')
            ->assertStatus(200);

        $response->assertJsonPath('data.requests.total', 0)
            ->assertJsonPath('data.inspections.completed', 0)
            ->assertJsonPath('data.compliance.total_checks', 0)
            ->assertJsonPath('data.clearances.issued', 0);
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