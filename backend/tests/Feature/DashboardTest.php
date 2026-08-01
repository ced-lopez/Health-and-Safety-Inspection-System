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
use App\Models\Violation;
use Database\Seeders\InspectionTaxonomySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $response = $this->getJson('/api/v1/dashboard');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_access_dashboard_with_correct_metrics(): void
    {
        $staffRole = Role::query()->where('slug', 'barangay_staff')->first();
        $user = User::query()->create([
            'role_id' => $staffRole->id,
            'name' => 'Dashboard User',
            'email' => 'dash@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        // Create 2 active establishments and 1 inactive establishment
        Establishment::query()->create([
            'name' => 'Active Est A',
            'business_type' => 'Retail',
            'owner_name' => 'Owner A',
            'address' => 'Addr A',
            'status' => 'active',
        ]);
        Establishment::query()->create([
            'name' => 'Active Est B',
            'business_type' => 'Retail',
            'owner_name' => 'Owner B',
            'address' => 'Addr B',
            'status' => 'active',
        ]);
        Establishment::query()->create([
            'name' => 'Inactive Est',
            'business_type' => 'Retail',
            'owner_name' => 'Owner C',
            'address' => 'Addr C',
            'status' => 'inactive',
        ]);

        // Create 1 upcoming scheduled inspection and 1 past scheduled inspection
        $estA = Establishment::query()->where('name', 'Active Est A')->first();
        $inspectorRole = Role::query()->where('slug', 'inspector')->first();
        $inspector = User::query()->create([
            'role_id' => $inspectorRole->id,
            'name' => 'Inspector A',
            'email' => 'inspector.a@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        InspectionSchedule::query()->create([
            'establishment_id' => $estA->id,
            'inspector_id' => $inspector->id,
            'scheduled_by' => $user->id,
            'scheduled_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);

        InspectionSchedule::query()->create([
            'establishment_id' => $estA->id,
            'inspector_id' => $inspector->id,
            'scheduled_by' => $user->id,
            'scheduled_date' => now()->subDay()->toDateString(),
            'status' => 'scheduled',
        ]);

        Inspection::query()->create([
            'establishment_id' => $estA->id,
            'inspector_id' => $inspector->id,
            'inspection_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);

        // Create 1 completed inspection YTD
        $inspectionCompleted = Inspection::query()->create([
            'establishment_id' => $estA->id,
            'inspector_id' => $inspector->id,
            'inspection_date' => now()->toDateString(),
            'status' => 'completed',
        ]);

        // Create 1 open violation and 1 resolved violation
        Violation::query()->create([
            'inspection_id' => $inspectionCompleted->id,
            'establishment_id' => $estA->id,
            'reported_by' => $inspector->id,
            'title' => 'Open Violation',
            'description' => 'Descr',
            'severity' => 'minor',
            'status' => 'open',
        ]);

        Violation::query()->create([
            'inspection_id' => $inspectionCompleted->id,
            'establishment_id' => $estA->id,
            'reported_by' => $inspector->id,
            'title' => 'Resolved Violation',
            'description' => 'Descr',
            'severity' => 'minor',
            'status' => 'resolved',
        ]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'stats' => [
                        'pending_requests',
                        'assigned_inspectors',
                        'completed_inspections',
                        'active_violations',
                        'expiring_clearances',
                        'active_establishments',
                    ],
                    'chart' => [
                        'year',
                        'monthly',
                        'violations',
                    ],
                    'recent_activities',
                ],
            ])
            ->assertJsonFragment([
                'stats' => [
                    'pending_requests' => 0,
                    'assigned_inspectors' => 0,
                    'completed_inspections' => 1,
                    'active_violations' => 1,
                    'expiring_clearances' => 0,
                    'active_establishments' => 2,
                ],
            ]);
    }

    public function test_resident_dashboard_is_scoped_to_own_requests(): void
    {
        $this->seed(InspectionTaxonomySeeder::class);

        $residentRole = Role::query()->where('slug', 'resident')->first();
        $otherRole = Role::query()->where('slug', 'barangay_staff')->first();

        $resident = User::query()->create([
            'role_id' => $residentRole->id,
            'name' => 'Resident A',
            'email' => 'resident.a@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $other = User::query()->create([
            'role_id' => $otherRole->id,
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $category = InspectionCategory::query()->where('slug', 'business_establishments')->firstOrFail();
        $appType = ApplicationType::query()->where('slug', 'new_application')->firstOrFail();

        $makeRequest = function (int $residentId, string $business, string $status) use ($category, $appType) {
            return InspectionRequest::query()->create([
                'request_number' => 'REQ-'.strtoupper(substr(uniqid(), -6)),
                'resident_id' => $residentId,
                'inspection_category_id' => $category->id,
                'application_type_id' => $appType->id,
                'applicant_name' => 'Applicant',
                'applicant_address' => 'Barangay 178',
                'contact_number' => '09171234567',
                'email' => 'applicant@example.com',
                'business_name' => $business,
                'status' => $status,
                'submitted_at' => now(),
            ]);
        };

        $makeRequest($resident->id, 'My Store', 'submitted');
        $makeRequest($resident->id, 'My Farm', 'inspection_completed');
        $makeRequest($other->id, 'Their Store', 'submitted');
        $makeRequest($other->id, 'Their Farm', 'inspection_completed');

        $response = $this->actingAs($resident, 'sanctum')->getJson('/api/v1/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'stats' => [
                        'total_requests',
                        'pending',
                        'in_progress',
                        'completed',
                    ],
                    'active_applications',
                ],
            ]);

        $this->assertSame(2, $response->json('data.stats.total_requests'));
        $this->assertSame(1, $response->json('data.stats.pending'));
        $this->assertSame(1, $response->json('data.stats.completed'));

        $businesses = collect($response->json('data.active_applications'))->pluck('business_name')->all();
        $this->assertNotContains('Their Store', $businesses);
        $this->assertNotContains('Their Farm', $businesses);
        $this->assertContains('My Store', $businesses);
    }
}
