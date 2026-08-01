<?php

namespace Tests\Feature;

use App\Models\Establishment;
use App\Models\Inspection;
use App\Models\Role;
use App\Models\User;
use App\Models\Violation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ViolationTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $barangayStaff;

    protected User $inspector;

    protected Inspection $inspection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $this->admin = $this->makeUser('administrator', 'admin@example.com');
        $this->barangayStaff = $this->makeUser('barangay_staff', 'staff@example.com');
        $this->inspector = $this->makeUser('inspector', 'inspector@example.com');

        $establishment = Establishment::query()->create([
            'name' => 'Violation Store',
            'business_type' => 'Retail',
            'owner_name' => 'Owner',
            'address' => 'Barangay 178',
            'registration_number' => 'B178-VIO-001',
            'status' => 'active',
        ]);

        $this->inspection = Inspection::query()->create([
            'establishment_id' => $establishment->id,
            'inspector_id' => $this->inspector->id,
            'inspection_date' => now()->toDateString(),
            'status' => 'completed',
        ]);
    }

    public function test_guest_cannot_access_violations(): void
    {
        $this->getJson('/api/v1/violations')->assertStatus(401);
    }

    public function test_inspector_can_create_violation(): void
    {
        $response = $this->actingAs($this->inspector, 'sanctum')
            ->postJson('/api/v1/violations', [
                'inspection_id' => $this->inspection->id,
                'assigned_to' => $this->barangayStaff->id,
                'title' => 'Blocked emergency exit',
                'description' => 'Rear emergency exit is blocked by boxes.',
                'severity' => 'major',
                'status' => 'open',
                'correction_deadline' => now()->addDays(3)->toDateString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Blocked emergency exit')
            ->assertJsonPath('data.establishment.name', 'Violation Store');

        $this->assertDatabaseHas('violations', [
            'inspection_id' => $this->inspection->id,
            'reported_by' => $this->inspector->id,
            'severity' => 'major',
            'status' => 'open',
        ]);
    }

    public function test_update_to_resolved_sets_resolver(): void
    {
        $violation = $this->makeViolation();

        $this->actingAs($this->barangayStaff, 'sanctum')
            ->putJson("/api/v1/violations/{$violation->id}", [
                'inspection_id' => $this->inspection->id,
                'assigned_to' => $this->barangayStaff->id,
                'title' => $violation->title,
                'description' => $violation->description,
                'severity' => 'minor',
                'status' => 'resolved',
                'correction_deadline' => now()->toDateString(),
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'resolved');

        $this->assertDatabaseHas('violations', [
            'id' => $violation->id,
            'resolved_by' => $this->barangayStaff->id,
        ]);

        $this->assertNotNull($violation->refresh()->resolved_at);
    }

    public function test_violation_filters_by_status(): void
    {
        $this->makeViolation(['status' => 'open', 'title' => 'Open Case']);
        $this->makeViolation(['status' => 'resolved', 'title' => 'Resolved Case']);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/violations?status=open')
            ->assertStatus(200)
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonFragment(['title' => 'Open Case']);
    }

    public function test_user_can_upload_corrective_evidence(): void
    {
        Storage::fake('public');
        $violation = $this->makeViolation();

        $response = $this->actingAs($this->inspector, 'sanctum')
            ->postJson("/api/v1/violations/{$violation->id}/evidence", [
                'evidence_type' => 'corrective',
                'description' => 'Exit cleared.',
                'files' => [
                    UploadedFile::fake()->create('cleared-exit.pdf', 12, 'application/pdf'),
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.0.evidence_type', 'corrective');

        $this->assertDatabaseHas('violation_evidence', [
            'violation_id' => $violation->id,
            'uploaded_by' => $this->inspector->id,
            'evidence_type' => 'corrective',
        ]);
    }

    private function makeViolation(array $overrides = []): Violation
    {
        return Violation::query()->create(array_merge([
            'inspection_id' => $this->inspection->id,
            'establishment_id' => $this->inspection->establishment_id,
            'reported_by' => $this->inspector->id,
            'assigned_to' => $this->barangayStaff->id,
            'title' => 'Improper waste disposal',
            'description' => 'Waste bins are not labeled.',
            'severity' => 'minor',
            'status' => 'open',
            'correction_deadline' => now()->addDays(7)->toDateString(),
        ], $overrides));
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
