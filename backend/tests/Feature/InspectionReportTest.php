<?php

namespace Tests\Feature;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Establishment;
use App\Models\Inspection;
use App\Models\InspectionResult;
use App\Models\InspectionSchedule;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InspectionReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $healthOfficer;
    protected User $inspector;
    protected InspectionSchedule $schedule;
    protected Inspection $inspection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $admin = $this->makeUser('administrator', 'admin@example.com');
        $this->healthOfficer = $this->makeUser('health_officer', 'health@example.com');
        $this->inspector = $this->makeUser('inspector', 'inspector@example.com');

        $establishment = Establishment::query()->create([
            'name' => 'Report Store',
            'business_type' => 'Retail',
            'owner_name' => 'Owner',
            'address' => 'Barangay 178',
            'registration_number' => 'B178-RPT-001',
            'status' => 'active',
        ]);

        $this->schedule = InspectionSchedule::query()->create([
            'establishment_id' => $establishment->id,
            'inspector_id' => $this->inspector->id,
            'scheduled_by' => $admin->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        $this->inspection = Inspection::query()->create([
            'inspection_schedule_id' => $this->schedule->id,
            'establishment_id' => $establishment->id,
            'inspector_id' => $this->inspector->id,
            'inspection_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        $checklist = Checklist::query()->create([
            'name' => 'Fire Safety Checklist',
            'category' => 'fire_safety',
            'version' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $compliantItem = ChecklistItem::query()->create([
            'checklist_id' => $checklist->id,
            'category' => 'Fire Extinguishers',
            'title' => 'Extinguishers are accessible',
            'sort_order' => 1,
            'is_required' => true,
        ]);

        $correctionItem = ChecklistItem::query()->create([
            'checklist_id' => $checklist->id,
            'category' => 'Emergency Exits',
            'title' => 'Exits are unobstructed',
            'sort_order' => 2,
            'is_required' => true,
        ]);

        InspectionResult::query()->create([
            'inspection_id' => $this->inspection->id,
            'checklist_item_id' => $compliantItem->id,
            'compliance_status' => 'compliant',
            'remarks' => 'Good condition.',
            'assessed_by' => $this->inspector->id,
        ]);

        InspectionResult::query()->create([
            'inspection_id' => $this->inspection->id,
            'checklist_item_id' => $correctionItem->id,
            'compliance_status' => 'needs_correction',
            'remarks' => 'Boxes near rear exit.',
            'assessed_by' => $this->inspector->id,
        ]);
    }

    public function test_authenticated_user_can_generate_inspection_report(): void
    {
        $this->actingAs($this->inspector, 'sanctum')
            ->getJson("/api/v1/inspections/schedules/{$this->schedule->id}/report")
            ->assertStatus(200)
            ->assertJsonPath('data.establishment.name', 'Report Store')
            ->assertJsonPath('data.summary.total_items', 2)
            ->assertJsonPath('data.summary.compliant', 1)
            ->assertJsonPath('data.summary.needs_correction', 1)
            ->assertJsonPath('data.results.0.checklist_name', 'Fire Safety Checklist');
    }

    public function test_health_officer_can_update_report_assessment_and_recommendations(): void
    {
        $this->actingAs($this->healthOfficer, 'sanctum')
            ->putJson("/api/v1/inspections/schedules/{$this->schedule->id}/report", [
                'overall_assessment' => 'Generally compliant with minor corrections.',
                'recommendations' => 'Clear emergency exit path within 24 hours.',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.overall_assessment', 'Generally compliant with minor corrections.')
            ->assertJsonPath('data.recommendations', 'Clear emergency exit path within 24 hours.');

        $this->assertDatabaseHas('inspections', [
            'id' => $this->inspection->id,
            'overall_assessment' => 'Generally compliant with minor corrections.',
            'recommendations' => 'Clear emergency exit path within 24 hours.',
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
