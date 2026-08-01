<?php

namespace Tests\Feature;

use App\Models\ApplicationType;
use App\Models\Establishment;
use App\Models\Inspection;
use App\Models\InspectionAssignment;
use App\Models\InspectionCategory;
use App\Models\InspectionRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ApplicationSubmitted;
use App\Notifications\InspectionCompleted;
use App\Notifications\InspectionSubmittedForReview;
use App\Notifications\InspectorAssigned;
use App\Notifications\MissingRequirements;
use App\Notifications\NewApplicationSubmitted;
use App\Notifications\NewInspectionAssignment;
use App\Notifications\ViolationFiled;
use Database\Seeders\InspectionTaxonomySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $staff;

    protected User $inspector;

    protected User $resident;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, InspectionTaxonomySeeder::class]);

        $this->admin = $this->makeUser('administrator', 'admind@example.com');
        $this->staff = $this->makeUser('barangay_staff', 'staffd@example.com');
        $this->inspector = $this->makeUser('inspector', 'inspectord@example.com');
        $this->resident = $this->makeUser('resident', 'residentd@example.com');
    }

    public function test_application_submission_notifies_resident_and_staff(): void
    {
        $response = $this->actingAs($this->resident, 'sanctum')
            ->postJson('/api/v1/inspection-requests', $this->requestPayload());

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'type' => ApplicationSubmitted::class,
            'notifiable_id' => $this->resident->id,
        ]);

        foreach ([$this->admin->id, $this->staff->id] as $staffId) {
            $this->assertDatabaseHas('notifications', [
                'type' => NewApplicationSubmitted::class,
                'notifiable_id' => $staffId,
            ]);
        }

        $this->assertDatabaseMissing('notifications', [
            'type' => NewApplicationSubmitted::class,
            'notifiable_id' => $this->inspector->id,
        ]);

        $notification = $this->resident->notifications()
            ->where('type', ApplicationSubmitted::class)
            ->firstOrFail();
        $this->assertStringContainsString($notification->data['request_number'], (string) $response->json('data.request_number'));
    }

    public function test_assign_notifies_resident_and_inspector(): void
    {
        $request = $this->makeRequest(['status' => 'approved_for_inspection']);

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/inspection-requests/{$request->id}/assign", [
                'inspector_id' => $this->inspector->id,
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'type' => InspectorAssigned::class,
            'notifiable_id' => $this->resident->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => NewInspectionAssignment::class,
            'notifiable_id' => $this->inspector->id,
        ]);
    }

    public function test_incomplete_requirements_notifies_resident(): void
    {
        $request = $this->makeRequest(['status' => 'submitted']);

        $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/v1/inspection-requests/{$request->id}/review", [
                'status' => 'requirements_incomplete',
                'remarks' => 'Missing business permit',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'type' => MissingRequirements::class,
            'notifiable_id' => $this->resident->id,
        ]);
    }

    public function test_inspection_submit_notifies_resident_and_staff(): void
    {
        $request = $this->makeRequest(['status' => 'assigned']);

        $assignment = InspectionAssignment::query()->create([
            'inspection_request_id' => $request->id,
            'inspector_id' => $this->inspector->id,
            'assigned_by' => $this->staff->id,
            'status' => 'in_progress',
            'assigned_at' => now(),
        ]);

        $this->actingAs($this->inspector, 'sanctum')
            ->putJson("/api/v1/inspection-assignments/{$assignment->id}/submit", [
                'notes' => 'Inspection done.',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'type' => InspectionCompleted::class,
            'notifiable_id' => $this->resident->id,
        ]);

        foreach ([$this->admin->id, $this->staff->id] as $staffId) {
            $this->assertDatabaseHas('notifications', [
                'type' => InspectionSubmittedForReview::class,
                'notifiable_id' => $staffId,
            ]);
        }
    }

    public function test_violation_filed_notifies_staff(): void
    {
        $establishment = Establishment::query()->create([
            'name' => 'Notif Store',
            'business_type' => 'Retail',
            'owner_name' => 'Owner',
            'address' => 'Barangay 178',
            'registration_number' => 'B178-NOTIF-001',
            'status' => 'active',
        ]);

        $inspection = Inspection::query()->create([
            'establishment_id' => $establishment->id,
            'inspector_id' => $this->inspector->id,
            'inspection_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        $this->actingAs($this->inspector, 'sanctum')
            ->postJson('/api/v1/violations', [
                'inspection_id' => $inspection->id,
                'assigned_to' => $this->staff->id,
                'title' => 'Blocked exit',
                'description' => 'Exit blocked.',
                'severity' => 'major',
                'status' => 'open',
                'correction_deadline' => now()->addDays(3)->toDateString(),
            ])
            ->assertStatus(201);

        foreach ([$this->admin->id, $this->staff->id] as $staffId) {
            $this->assertDatabaseHas('notifications', [
                'type' => ViolationFiled::class,
                'notifiable_id' => $staffId,
            ]);
        }

        $this->assertDatabaseMissing('notifications', [
            'type' => ViolationFiled::class,
            'notifiable_id' => $this->resident->id,
        ]);
    }

    protected function makeRequest(array $overrides = []): InspectionRequest
    {
        $category = InspectionCategory::query()->where('slug', 'business_establishments')->firstOrFail();
        $appType = ApplicationType::query()->where('slug', 'new_application')->firstOrFail();

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

    protected function requestPayload(): array
    {
        $category = InspectionCategory::query()->where('slug', 'business_establishments')->firstOrFail();
        $appType = ApplicationType::query()->where('slug', 'new_application')->firstOrFail();

        return [
            'inspection_category_id' => $category->id,
            'application_type_id' => $appType->id,
            'applicant_name' => 'Juan Dela Cruz',
            'applicant_address' => 'Barangay 178',
            'contact_number' => '09171234567',
            'email' => 'juan@example.com',
            'business_name' => 'Sari-Sari Store',
        ];
    }

    protected function makeUser(string $roleSlug, string $email): User
    {
        $role = Role::query()->where('slug', $roleSlug)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => ucfirst(str_replace('_', ' ', $roleSlug)),
            'email' => $email,
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
    }
}
