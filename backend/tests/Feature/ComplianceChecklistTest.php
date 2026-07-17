<?php

namespace Tests\Feature;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Establishment;
use App\Models\InspectionSchedule;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceChecklistTest extends TestCase
{
    use RefreshDatabase;

    protected User $inspector;
    protected InspectionSchedule $schedule;
    protected ChecklistItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $admin = $this->makeUser('administrator', 'admin@example.com');
        $this->inspector = $this->makeUser('inspector', 'inspector@example.com');

        $establishment = Establishment::query()->create([
            'name' => 'Checklist Store',
            'business_type' => 'Retail',
            'owner_name' => 'Owner',
            'address' => 'Barangay 178',
            'registration_number' => 'B178-CHK-001',
            'status' => 'active',
        ]);

        $this->schedule = InspectionSchedule::query()->create([
            'establishment_id' => $establishment->id,
            'inspector_id' => $this->inspector->id,
            'scheduled_by' => $admin->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);

        $checklist = Checklist::query()->create([
            'name' => 'Health Checklist',
            'category' => 'health_sanitation',
            'version' => 1,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->item = ChecklistItem::query()->create([
            'checklist_id' => $checklist->id,
            'category' => 'Cleanliness',
            'title' => 'Premises are clean',
            'sort_order' => 1,
            'is_required' => true,
        ]);
    }

    public function test_inspector_can_retrieve_compliance_checklist(): void
    {
        $this->actingAs($this->inspector, 'sanctum')
            ->getJson("/api/v1/inspections/schedules/{$this->schedule->id}/checklist")
            ->assertStatus(200)
            ->assertJsonPath('data.checklists.0.name', 'Health Checklist')
            ->assertJsonPath('data.checklists.0.items.0.title', 'Premises are clean');
    }

    public function test_inspector_can_save_compliance_result(): void
    {
        $response = $this->actingAs($this->inspector, 'sanctum')
            ->postJson("/api/v1/inspections/schedules/{$this->schedule->id}/checklist", [
                'results' => [
                    [
                        'checklist_item_id' => $this->item->id,
                        'compliance_status' => 'needs_correction',
                        'remarks' => 'Needs better waste bin labeling.',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.compliance_status', 'needs_correction');

        $this->assertDatabaseHas('inspection_results', [
            'checklist_item_id' => $this->item->id,
            'compliance_status' => 'needs_correction',
            'assessed_by' => $this->inspector->id,
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
