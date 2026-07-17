<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\Inspection;
use App\Models\InspectionSchedule;
use App\Models\Role;
use App\Models\User;
use App\Models\Violation;
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
        $staffRole = Role::query()->where('slug', 'staff')->first();
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
                        'scheduled_inspections',
                        'active_establishments',
                        'open_violations',
                        'completed_inspections',
                    ],
                    'recent_inspections',
                ],
            ])
            ->assertJsonFragment([
                'stats' => [
                    'scheduled_inspections' => 1,
                    'active_establishments' => 2,
                    'open_violations' => 1,
                    'completed_inspections' => 1,
                ]
            ]);
    }
}
