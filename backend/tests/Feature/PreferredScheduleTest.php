<?php

namespace Tests\Feature;

use App\Models\ApplicationType;
use App\Models\Establishment;
use App\Models\InspectionCategory;
use App\Models\InspectionRequest;
use App\Models\InspectionSchedule;
use App\Models\Role;
use App\Models\User;
use App\Notifications\PreferredScheduleSubmitted;
use Database\Seeders\InspectionTaxonomySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PreferredScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $barangayStaff;

    protected User $inspector;

    protected User $resident;

    protected Establishment $establishment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, InspectionTaxonomySeeder::class]);

        $this->admin = $this->makeUser('administrator', 'admin@example.com');
        $this->barangayStaff = $this->makeUser('barangay_staff', 'staff@example.com');
        $this->inspector = $this->makeUser('inspector', 'inspector@example.com');
        $this->resident = $this->makeUser('resident', 'resident@example.com');

        $this->establishment = Establishment::query()->create([
            'name' => 'North Market',
            'business_type' => 'Retail',
            'owner_name' => 'Market Owner',
            'address' => 'Barangay 178',
            'registration_number' => 'B178-MKT-001',
            'status' => 'active',
        ]);
    }

    public function test_resident_can_propose_preferred_schedule_on_own_request(): void
    {
        Notification::fake();

        $request = $this->makeRequest();
        $preferredAt = now()->addDays(2)->startOfMinute();

        $this->actingAs($this->resident, 'sanctum')
            ->putJson("/api/v1/inspection-requests/{$request->id}/preferred-schedule", [
                'preferred_schedule_at' => $preferredAt->format('Y-m-d H:i'),
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.preferred_schedule_at', $preferredAt->toIso8601String());

        $this->assertDatabaseHas('inspection_requests', [
            'id' => $request->id,
            'preferred_schedule_at' => $preferredAt->format('Y-m-d H:i:00'),
        ]);

        Notification::assertSentTo($this->admin, PreferredScheduleSubmitted::class);
        Notification::assertSentTo($this->barangayStaff, PreferredScheduleSubmitted::class);
    }

    public function test_resident_cannot_propose_schedule_for_another_residents_request(): void
    {
        $other = $this->makeUser('resident', 'other@example.com');
        $request = $this->makeRequest();

        $this->actingAs($other, 'sanctum')
            ->putJson("/api/v1/inspection-requests/{$request->id}/preferred-schedule", [
                'preferred_schedule_at' => now()->addDays(2)->format('Y-m-d H:i'),
            ])
            ->assertStatus(403);
    }

    public function test_resident_cannot_propose_schedule_after_schedule_confirmed(): void
    {
        $request = $this->makeRequest();
        $confirmedAt = now()->addDays(2);

        InspectionSchedule::query()->create([
            'establishment_id' => $request->establishment_id,
            'inspector_id' => $this->inspector->id,
            'inspection_request_id' => $request->id,
            'scheduled_by' => $this->admin->id,
            'scheduled_date' => $confirmedAt->toDateString(),
            'scheduled_time' => $confirmedAt->format('H:i'),
            'scheduled_at' => $confirmedAt,
            'status' => 'scheduled',
        ]);

        $this->actingAs($this->resident, 'sanctum')
            ->putJson("/api/v1/inspection-requests/{$request->id}/preferred-schedule", [
                'preferred_schedule_at' => now()->addDays(3)->format('Y-m-d H:i'),
            ])
            ->assertStatus(422);
    }

    public function test_resident_can_clear_preferred_schedule(): void
    {
        $request = $this->makeRequest(['preferred_schedule_at' => now()->addDays(2)]);

        $this->actingAs($this->resident, 'sanctum')
            ->deleteJson("/api/v1/inspection-requests/{$request->id}/preferred-schedule")
            ->assertStatus(200)
            ->assertJsonPath('data.preferred_schedule_at', null);

        $this->assertDatabaseHas('inspection_requests', [
            'id' => $request->id,
            'preferred_schedule_at' => null,
        ]);
    }

    public function test_staff_can_confirm_preferred_schedule(): void
    {
        $request = $this->makeRequest(['preferred_schedule_at' => now()->addDays(2)]);

        $this->actingAs($this->barangayStaff, 'sanctum')
            ->postJson("/api/v1/inspection-requests/{$request->id}/confirm-schedule", [
                'inspector_id' => $this->inspector->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.schedule.inspection_request_id', $request->id)
            ->assertJsonPath('data.schedule.inspector_id', $this->inspector->id);

        $this->assertDatabaseHas('inspection_schedules', [
            'inspection_request_id' => $request->id,
            'inspector_id' => $this->inspector->id,
            'status' => 'scheduled',
        ]);

        $this->assertDatabaseHas('inspection_requests', [
            'id' => $request->id,
            'preferred_schedule_at' => null,
            'status' => 'assigned',
        ]);

        $this->assertDatabaseHas('inspection_assignments', [
            'inspection_request_id' => $request->id,
            'inspector_id' => $this->inspector->id,
        ]);
    }

    public function test_staff_can_confirm_schedule_with_override_datetime(): void
    {
        $request = $this->makeRequest(['preferred_schedule_at' => now()->addDays(2)]);

        $override = now()->addDays(5)->format('Y-m-d');
        $time = '14:00';

        $this->actingAs($this->barangayStaff, 'sanctum')
            ->postJson("/api/v1/inspection-requests/{$request->id}/confirm-schedule", [
                'inspector_id' => $this->inspector->id,
                'scheduled_date' => $override,
                'scheduled_time' => $time,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.schedule.scheduled_date', $override)
            ->assertJsonPath('data.schedule.scheduled_time', $time);

        $this->assertDatabaseHas('inspection_schedules', [
            'inspection_request_id' => $request->id,
            'scheduled_time' => $time,
        ]);
    }

    public function test_inspector_cannot_confirm_preferred_schedule(): void
    {
        $request = $this->makeRequest(['preferred_schedule_at' => now()->addDays(2)]);

        $this->actingAs($this->inspector, 'sanctum')
            ->postJson("/api/v1/inspection-requests/{$request->id}/confirm-schedule", [
                'inspector_id' => $this->inspector->id,
            ])
            ->assertStatus(403);
    }

    public function test_guest_cannot_access_preferred_schedule_endpoints(): void
    {
        $this->putJson('/api/v1/inspection-requests/1/preferred-schedule', [])
            ->assertStatus(401);
    }

    private function makeRequest(array $attributes = []): InspectionRequest
    {
        return InspectionRequest::query()->create(array_merge([
            'request_number' => 'REQ-'.strtoupper(substr(uniqid(), -6)),
            'resident_id' => $this->resident->id,
            'inspection_category_id' => InspectionCategory::query()->where('slug', 'piggery')->firstOrFail()->id,
            'application_type_id' => ApplicationType::query()->where('slug', 'new_application')->firstOrFail()->id,
            'establishment_id' => $this->establishment->id,
            'applicant_name' => 'Juan Dela Cruz',
            'applicant_address' => 'Barangay 178',
            'contact_number' => '09171234567',
            'email' => 'juan@example.com',
            'business_name' => 'Piggery Farm',
            'status' => 'approved_for_inspection',
        ], $attributes));
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