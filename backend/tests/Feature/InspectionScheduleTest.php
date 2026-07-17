<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\Inspection;
use App\Models\InspectionSchedule;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InspectionScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $healthOfficer;
    protected User $inspector;
    protected Establishment $establishment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = $this->makeUser('administrator', 'admin@example.com');
        $this->healthOfficer = $this->makeUser('health_officer', 'health@example.com');
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

    public function test_health_officer_can_create_schedule_and_linked_inspection(): void
    {
        $response = $this->actingAs($this->healthOfficer, 'sanctum')
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
            'scheduled_by' => $this->healthOfficer->id,
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
