<?php

namespace Tests\Feature;

use App\Models\ApplicationType;
use App\Models\Establishment;
use App\Models\Inspection;
use App\Models\InspectionCategory;
use App\Models\InspectionRequest;
use App\Models\InspectionSchedule;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\InspectionTaxonomySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InspectionScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $barangayStaff;

    protected User $inspector;

    protected Establishment $establishment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, InspectionTaxonomySeeder::class]);

        $this->admin = $this->makeUser('administrator', 'admin@example.com');
        $this->barangayStaff = $this->makeUser('barangay_staff', 'staff@example.com');
        $this->inspector = $this->makeUser('inspector', 'inspector@example.com');
        $this->establishment = Establishment::query()->create([
            'name' => 'North Market',
            'business_type' => 'Retail',
            'owner_name' => 'Market Owner',
            'address' => 'Barangay 178',
            'registration_number' => 'B178-MKT-001',
            'status' => 'active',
        ]);
    }

    public function test_guest_cannot_access_inspection_schedules(): void
    {
        $this->getJson('/api/v1/inspections/schedules')->assertStatus(401);
    }

    public function test_barangay_staff_can_create_schedule_and_linked_inspection(): void
    {
        $response = $this->actingAs($this->barangayStaff, 'sanctum')
            ->postJson('/api/v1/inspections/schedules', [
                'establishment_id' => $this->establishment->id,
                'inspector_id' => $this->inspector->id,
                'scheduled_date' => now()->addDay()->toDateString(),
                'scheduled_time' => '09:30',
                'status' => 'scheduled',
                'notes' => 'Routine inspection',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.establishment.name', 'North Market')
            ->assertJsonPath('data.inspector.name', $this->inspector->name);

        $scheduleId = $response->json('data.id');

        $this->assertDatabaseHas('inspection_schedules', [
            'id' => $scheduleId,
            'scheduled_by' => $this->barangayStaff->id,
            'status' => 'scheduled',
        ]);

        $this->assertDatabaseHas('inspections', [
            'inspection_schedule_id' => $scheduleId,
            'establishment_id' => $this->establishment->id,
            'inspector_id' => $this->inspector->id,
            'status' => 'scheduled',
        ]);
    }

    public function test_inspector_can_list_but_not_create_schedule(): void
    {
        $this->actingAs($this->inspector, 'sanctum')
            ->getJson('/api/v1/inspections/schedules')
            ->assertStatus(200);

        $this->actingAs($this->inspector, 'sanctum')
            ->postJson('/api/v1/inspections/schedules', [])
            ->assertStatus(403);
    }

    public function test_schedule_filters_by_status(): void
    {
        InspectionSchedule::query()->create([
            'establishment_id' => $this->establishment->id,
            'inspector_id' => $this->inspector->id,
            'scheduled_by' => $this->admin->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);

        InspectionSchedule::query()->create([
            'establishment_id' => $this->establishment->id,
            'inspector_id' => $this->inspector->id,
            'scheduled_by' => $this->admin->id,
            'scheduled_date' => now()->subDay()->toDateString(),
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/inspections/schedules?status=scheduled');

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonFragment(['status' => 'scheduled']);
    }

    public function test_admin_can_archive_schedule_and_linked_inspection(): void
    {
        $schedule = InspectionSchedule::query()->create([
            'establishment_id' => $this->establishment->id,
            'inspector_id' => $this->inspector->id,
            'scheduled_by' => $this->admin->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);

        Inspection::query()->create([
            'inspection_schedule_id' => $schedule->id,
            'establishment_id' => $this->establishment->id,
            'inspector_id' => $this->inspector->id,
            'inspection_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);

        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/v1/inspections/schedules/{$schedule->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('inspection_schedules', ['id' => $schedule->id]);
        $this->assertSoftDeleted('inspections', ['inspection_schedule_id' => $schedule->id]);
    }

    public function test_staff_can_schedule_request_without_establishment(): void
    {
        $resident = $this->makeUser('resident', 'scheduleresident@example.com');

        $request = InspectionRequest::query()->create([
            'request_number' => 'REQ-'.strtoupper(substr(uniqid(), -6)),
            'resident_id' => $resident->id,
            'inspection_category_id' => InspectionCategory::where('slug', 'piggery')->firstOrFail()->id,
            'application_type_id' => ApplicationType::where('slug', 'new_application')->firstOrFail()->id,
            'applicant_name' => 'Juan Dela Cruz',
            'applicant_address' => 'Barangay 178',
            'contact_number' => '09171234567',
            'email' => 'juan@example.com',
            'business_name' => 'Piggery Farm',
            'status' => 'approved_for_inspection',
        ]);

        $response = $this->actingAs($this->barangayStaff, 'sanctum')
            ->postJson('/api/v1/inspections/schedules', [
                'inspector_id' => $this->inspector->id,
                'scheduled_date' => now()->addDay()->toDateString(),
                'scheduled_time' => '09:30',
                'status' => 'scheduled',
                'inspection_request_id' => $request->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.establishment', null)
            ->assertJsonPath('data.inspection_request_id', $request->id);

        $scheduleId = $response->json('data.id');

        $this->assertDatabaseHas('inspection_schedules', [
            'id' => $scheduleId,
            'establishment_id' => null,
        ]);

        $this->assertDatabaseHas('inspections', [
            'inspection_schedule_id' => $scheduleId,
            'establishment_id' => null,
            'inspector_id' => $this->inspector->id,
        ]);
    }

    public function test_establishment_required_when_scheduling_without_request(): void
    {
        $this->actingAs($this->barangayStaff, 'sanctum')
            ->postJson('/api/v1/inspections/schedules', [
                'inspector_id' => $this->inspector->id,
                'scheduled_date' => now()->addDay()->toDateString(),
                'status' => 'scheduled',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['establishment_id']);
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
