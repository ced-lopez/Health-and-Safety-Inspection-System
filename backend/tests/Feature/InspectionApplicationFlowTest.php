<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentOcr;
use App\Models\ApplicationType;
use App\Models\Establishment;
use App\Models\InspectionCategory;
use App\Models\InspectionRequest;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\InspectionTaxonomySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InspectionApplicationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $resident;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, InspectionTaxonomySeeder::class]);

        $role = Role::where('slug', 'resident')->firstOrFail();

        $this->resident = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Flow Resident',
            'email' => 'flow@example.com',
            'password' => bcrypt('Password123!'),
            'is_active' => true,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'inspection_category_id' => InspectionCategory::where('slug', 'piggery')->firstOrFail()->id,
            'application_type_id' => ApplicationType::where('slug', 'new_application')->firstOrFail()->id,
            'sub_path' => 'backyard_micro_scale',
            'declared_animal_count' => 3,
            'applicant_name' => 'Juan Dela Cruz',
            'applicant_address' => 'Barangay 178, Caloocan City',
            'contact_number' => '09171234567',
            'email' => 'juan@example.com',
        ], $overrides);
    }

    public function test_commercial_piggery_submission_is_blocked(): void
    {
        $response = $this->actingAs($this->resident, 'sanctum')
            ->postJson('/api/v1/inspection-requests', $this->payload(['sub_path' => 'commercial']));

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('inspection_requests', 0);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'inspection_request.blocked',
            'module' => 'Inspection Requests',
            'action' => 'Blocked',
        ]);
    }

    public function test_backyard_piggery_with_count_above_five_is_blocked(): void
    {
        $response = $this->actingAs($this->resident, 'sanctum')
            ->postJson('/api/v1/inspection-requests', $this->payload(['declared_animal_count' => 6]));

        $response->assertStatus(422);

        $this->assertDatabaseCount('inspection_requests', 0);
    }

    public function test_backyard_piggery_with_valid_count_is_accepted(): void
    {
        $response = $this->actingAs($this->resident, 'sanctum')
            ->postJson('/api/v1/inspection-requests', $this->payload());

        $response->assertStatus(201)
            ->assertJsonPath('data.sub_path', 'backyard_micro_scale')
            ->assertJsonPath('data.declared_animal_count', 3);

        $this->assertDatabaseHas('inspection_requests', [
            'sub_path' => 'backyard_micro_scale',
            'declared_animal_count' => 3,
            'status' => 'submitted',
        ]);
    }

    public function test_dog_raising_requires_declared_path(): void
    {
        $response = $this->actingAs($this->resident, 'sanctum')
            ->postJson('/api/v1/inspection-requests', $this->payload([
                'inspection_category_id' => InspectionCategory::where('slug', 'animal_raising_dogs')->firstOrFail()->id,
                'sub_path' => null,
            ]));

        $response->assertStatus(422);
        $this->assertDatabaseCount('inspection_requests', 0);
    }

    public function test_dog_raising_commercial_kennel_is_accepted(): void
    {
        $response = $this->actingAs($this->resident, 'sanctum')
            ->postJson('/api/v1/inspection-requests', $this->payload([
                'inspection_category_id' => InspectionCategory::where('slug', 'animal_raising_dogs')->firstOrFail()->id,
                'sub_path' => 'commercial_kennel',
            ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.sub_path', 'commercial_kennel');
    }

    public function test_food_safety_category_does_not_require_sub_path(): void
    {
        $response = $this->actingAs($this->resident, 'sanctum')
            ->postJson('/api/v1/inspection-requests', $this->payload([
                'inspection_category_id' => InspectionCategory::where('slug', 'business_establishments')->firstOrFail()->id,
                'sub_path' => null,
                'declared_animal_count' => null,
            ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.sub_path', null)
            ->assertJsonPath('data.declared_animal_count', null);
    }

    public function test_document_requirements_are_sub_path_scoped(): void
    {
        // Resident upload now whitelisted to only government_id + proof_of_location (Proof of Residency Clearance).
        // Barangay ID and other documents (cedula, vicinity_map, pet_registration_form, etc.) are archived.
        $piggeryId = InspectionCategory::where('slug', 'piggery')->firstOrFail()->id;
        $dogId = InspectionCategory::where('slug', 'animal_raising_dogs')->firstOrFail()->id;
        $businessId = InspectionCategory::where('slug', 'business_establishments')->firstOrFail()->id;
        $appTypeId = ApplicationType::where('slug', 'new_application')->firstOrFail()->id;

        $whitelist = ['government_id', 'proof_of_location'];

        $dogHousehold = $this->actingAs($this->resident, 'sanctum')
            ->getJson('/api/v1/inspection-requests/document-requirements?'.http_build_query([
                'inspection_category_id' => $dogId,
                'application_type_id' => $appTypeId,
                'sub_path' => 'household',
            ]))
            ->assertStatus(200)
            ->json('data.requirements');

        $dogTypes = array_column($dogHousehold, 'document_type');
        $this->assertEqualsCanonicalizing($whitelist, $dogTypes);
        $this->assertNotContains('pet_registration_form', $dogTypes);
        $this->assertNotContains('rabies_certificate', $dogTypes);
        $this->assertNotContains('bai_registration', $dogTypes);
        $this->assertNotContains('cedula', $dogTypes);

        $piggery = $this->actingAs($this->resident, 'sanctum')
            ->getJson('/api/v1/inspection-requests/document-requirements?'.http_build_query([
                'inspection_category_id' => $piggeryId,
                'application_type_id' => $appTypeId,
                'sub_path' => 'backyard_micro_scale',
            ]))
            ->assertStatus(200)
            ->json('data.requirements');

        $piggeryTypes = array_column($piggery, 'document_type');
        $this->assertEqualsCanonicalizing($whitelist, $piggeryTypes);
        $this->assertNotContains('zoning_assessment', $piggeryTypes);
        $this->assertNotContains('waste_management_plan', $piggeryTypes);

        $business = $this->actingAs($this->resident, 'sanctum')
            ->getJson('/api/v1/inspection-requests/document-requirements?'.http_build_query([
                'inspection_category_id' => $businessId,
                'application_type_id' => $appTypeId,
            ]))
            ->assertStatus(200)
            ->json('data.requirements');

        $businessTypes = array_column($business, 'document_type');
        $this->assertEqualsCanonicalizing($whitelist, $businessTypes);

        $coreRequired = array_filter($piggeryTypes, fn ($type) => in_array($type, $whitelist, true));
        $this->assertCount(2, $coreRequired);
    }

    public function test_blocked_attempt_is_logged_without_creating_request(): void
    {
        $before = DB::table('inspection_requests')->count();

        $this->actingAs($this->resident, 'sanctum')
            ->postJson('/api/v1/inspection-requests', $this->payload(['sub_path' => 'commercial']))
            ->assertStatus(422);

        $this->assertSame($before, DB::table('inspection_requests')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('event', 'inspection_request.blocked')->count());
    }

    public function test_resident_can_upload_typed_document_to_own_request(): void
    {
        Bus::fake([ProcessDocumentOcr::class]);

        $request = $this->actingAs($this->resident, 'sanctum')
            ->postJson('/api/v1/inspection-requests', $this->payload())
            ->assertStatus(201)
            ->json('data');

        $response = $this->actingAs($this->resident, 'sanctum')
            ->postJson("/api/v1/inspection-requests/{$request['id']}/documents", [
                'document_type' => 'government_id',
                'file' => UploadedFile::fake()->image('id.png'),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.document_type', 'government_id');

        $this->assertDatabaseHas('documents', [
            'documentable_id' => $request['id'],
            'document_type' => 'government_id',
            'uploaded_by' => $this->resident->id,
        ]);

        $requirements = $this->actingAs($this->resident, 'sanctum')
            ->getJson("/api/v1/inspection-requests/{$request['id']}/documents")
            ->assertStatus(200)
            ->json('data.documents');

        $this->assertCount(1, $requirements);
    }

    public function test_resident_cannot_upload_to_another_residents_request(): void
    {
        Bus::fake([ProcessDocumentOcr::class]);

        $owner = $this->createResident('owner@example.com');
        $request = $this->actingAs($owner, 'sanctum')
            ->postJson('/api/v1/inspection-requests', $this->payload())
            ->assertStatus(201)
            ->json('data');

        $this->actingAs($this->resident, 'sanctum')
            ->postJson("/api/v1/inspection-requests/{$request['id']}/documents", [
                'document_type' => 'government_id',
                'file' => UploadedFile::fake()->image('id.png'),
            ])
            ->assertStatus(403);

        $this->actingAs($this->resident, 'sanctum')
            ->getJson("/api/v1/inspection-requests/{$request['id']}/documents")
            ->assertStatus(403);

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_wizard_request_never_creates_establishment(): void
    {
        // Now autolinked: wizard auto-creates establishment from request data
        $response = $this->actingAs($this->resident, 'sanctum')
            ->postJson('/api/v1/inspection-requests', $this->payload());

        $response->assertStatus(201);
        $request = InspectionRequest::query()->findOrFail($response->json('data.id'));

        $this->assertNotNull($request->establishment_id);
        $this->assertDatabaseCount('establishments', 1);
        $this->assertDatabaseHas('establishments', [
            'id' => $request->establishment_id,
            'resident_id' => $this->resident->id,
            'ownership_status' => 'linked',
        ]);

        $staff = User::query()->create([
            'role_id' => Role::where('slug', 'barangay_staff')->firstOrFail()->id,
            'name' => 'Approver',
            'email' => 'approver@example.com',
            'password' => bcrypt('Password123!'),
            'is_active' => true,
        ]);

        $this->actingAs($staff, 'sanctum')
            ->putJson("/api/v1/inspection-requests/{$request->id}/review", [
                'status' => 'approved_for_inspection',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved_for_inspection');

        $request->refresh();
        $this->assertNotNull($request->establishment_id);
        $this->assertDatabaseCount('establishments', 1);
    }

    public function test_repeated_applications_do_not_create_establishments(): void
    {
        // Repeated applications with same business_name/address reuse the same establishment (no duplicate)
        $first = $this->actingAs($this->resident, 'sanctum')
            ->postJson('/api/v1/inspection-requests', $this->payload([
                'business_name' => 'Dela Cruz Piggery',
            ]))
            ->assertStatus(201)
            ->json('data');

        $second = $this->actingAs($this->resident, 'sanctum')
            ->postJson('/api/v1/inspection-requests', $this->payload([
                'business_name' => 'Dela Cruz Piggery',
            ]))
            ->assertStatus(201)
            ->json('data');

        $this->assertSame($first['establishment']['id'], $second['establishment']['id']);
        $this->assertDatabaseCount('establishments', 1);
    }

    public function test_explicit_establishment_is_not_auto_created(): void
    {
        $establishment = Establishment::query()->create([
            'name' => 'Existing Piggery',
            'business_type' => 'Piggery',
            'owner_name' => 'Juan Dela Cruz',
            'address' => 'Barangay 178, Caloocan City',
            'registration_number' => 'B178-EST-EXPLICIT',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->resident, 'sanctum')
            ->postJson('/api/v1/inspection-requests', $this->payload([
                'establishment_id' => $establishment->id,
            ]));

        $response->assertStatus(201)
            ->assertJsonPath('data.establishment.id', $establishment->id);

        $this->assertDatabaseCount('establishments', 1);
    }

    public function test_resident_can_link_own_establishment(): void
    {
        $establishment = Establishment::query()->create([
            'name' => 'My Piggery',
            'business_type' => 'Piggery',
            'owner_name' => 'Juan Dela Cruz',
            'address' => 'Barangay 178, Caloocan City',
            'registration_number' => 'B178-EST-OWNED',
            'status' => 'active',
            'resident_id' => $this->resident->id,
            'ownership_status' => 'linked',
        ]);

        $this->actingAs($this->resident, 'sanctum')
            ->postJson('/api/v1/inspection-requests', $this->payload([
                'establishment_id' => $establishment->id,
            ]))
            ->assertStatus(201);
    }

    public function test_resident_cannot_link_another_residents_establishment(): void
    {
        $owner = $this->createResident('ownerest@example.com');

        $establishment = Establishment::query()->create([
            'name' => 'Other Resident Piggery',
            'business_type' => 'Piggery',
            'owner_name' => 'Other Owner',
            'address' => 'Barangay 178, Caloocan City',
            'registration_number' => 'B178-EST-OTHER',
            'status' => 'active',
            'resident_id' => $owner->id,
            'ownership_status' => 'linked',
        ]);

        $this->actingAs($this->resident, 'sanctum')
            ->postJson('/api/v1/inspection-requests', $this->payload([
                'establishment_id' => $establishment->id,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['establishment_id']);

        $this->assertDatabaseCount('inspection_requests', 0);
    }

    public function test_resident_cannot_link_another_residents_pending_establishment(): void
    {
        $owner = $this->createResident('ownerpending@example.com');

        $establishment = Establishment::query()->create([
            'name' => 'Pending Piggery',
            'business_type' => 'Piggery',
            'owner_name' => 'Pending Owner',
            'address' => 'Barangay 178, Caloocan City',
            'registration_number' => 'B178-EST-PENDING',
            'status' => 'active',
            'resident_id' => $owner->id,
            'ownership_status' => 'pending',
        ]);

        $this->actingAs($this->resident, 'sanctum')
            ->postJson('/api/v1/inspection-requests', $this->payload([
                'establishment_id' => $establishment->id,
            ]))
            ->assertStatus(422);
    }

    private function createResident(string $email): User
    {
        $role = Role::where('slug', 'resident')->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => 'Other Resident',
            'email' => $email,
            'password' => bcrypt('Password123!'),
            'is_active' => true,
        ]);
    }
}
