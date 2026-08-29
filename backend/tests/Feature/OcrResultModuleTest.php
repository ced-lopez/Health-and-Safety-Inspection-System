<?php

namespace Tests\Feature;

use App\Models\ApplicationType;
use App\Models\Document;
use App\Models\DocumentExtraction;
use App\Models\DocumentExtractionVersion;
use App\Models\InspectionCategory;
use App\Models\InspectionRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Ocr\DocumentClassifier;
use App\Services\Ocr\DocumentFieldExtractor;
use App\Services\Ocr\DocumentValidator;
use App\Services\Ocr\OcrService;
use Database\Seeders\InspectionTaxonomySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OcrResultModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    protected User $admin;

    protected User $inspector;

    protected User $resident;

    protected InspectionRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, InspectionTaxonomySeeder::class]);

        $this->staff = $this->makeUser('barangay_staff', 'ocrstaff@example.com');
        $this->admin = $this->makeUser('administrator', 'ocradmin@example.com');
        $this->inspector = $this->makeUser('inspector', 'ocrinspector@example.com');
        $this->resident = $this->makeUser('resident', 'ocrresident@example.com');

        $category = InspectionCategory::where('slug', 'business_establishments')->firstOrFail();
        $appType = ApplicationType::where('slug', 'new_application')->firstOrFail();

        $this->request = InspectionRequest::query()->create([
            'request_number' => 'REQ-OCR-0001',
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
        ]);
    }

    public function test_guest_cannot_list_ocr_results(): void
    {
        Storage::fake('public');
        $this->makeProcessedDocument();

        $this->getJson('/api/v1/admin/ocr-results')->assertStatus(401);
    }

    public function test_inspector_cannot_access_ocr_results(): void
    {
        Storage::fake('public');
        $this->makeProcessedDocument();

        $this->actingAs($this->inspector, 'sanctum')
            ->getJson('/api/v1/admin/ocr-results')
            ->assertStatus(403);
    }

    public function test_staff_can_list_ocr_results_with_context(): void
    {
        Storage::fake('public');
        $this->makeProcessedDocument();

        $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/admin/ocr-results')
            ->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.results.0.classification', 'dti_registration')
            ->assertJsonPath('data.results.0.applicant', 'Juan Dela Cruz')
            ->assertJsonPath('data.results.0.business_name', 'Sari-Sari Store')
            ->assertJsonPath('data.results.0.request_number', 'REQ-OCR-0001');
    }

    public function test_ocr_results_include_unprocessed_documents(): void
    {
        Storage::fake('public');
        $this->makeProcessedDocument('permit.png', 'completed', 'dti_registration', 96);

        $path = "documents/{$this->request->id}/not-yet-ocr.png";
        Storage::disk('public')->put($path, $this->makePng());
        Document::query()->create([
            'documentable_type' => InspectionRequest::class,
            'documentable_id' => $this->request->id,
            'uploaded_by' => $this->resident->id,
            'document_type' => 'business_permit',
            'file_path' => $path,
            'file_name' => 'not-yet-ocr.png',
            'original_name' => 'not-yet-ocr.png',
            'mime_type' => 'image/png',
            'file_size' => 100,
            'status' => 'pending',
        ]);

        $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/admin/ocr-results')
            ->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.results.1.extraction', null)
            ->assertJsonPath('data.results.1.status', 'pending');
    }

    public function test_staff_can_search_ocr_results_by_file_name(): void
    {
        Storage::fake('public');
        $this->makeProcessedDocument('permit.png', 'completed', 'dti_registration', 96);
        $other = $this->makeProcessedDocument('cedula.png', 'completed', 'cedula', 91);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/admin/ocr-results?search=permit')
            ->assertStatus(200);

        $this->assertSame(1, $response->json('data.pagination.total'));

        $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/admin/ocr-results?search=cedula')
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_staff_can_filter_ocr_results_by_status_and_confidence(): void
    {
        Storage::fake('public');
        $this->makeProcessedDocument('permit.png', 'completed', 'dti_registration', 96, 'verified');
        $this->makeProcessedDocument('cedula.png', 'needs_review', 'cedula', 60);

        $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/admin/ocr-results?ocr_status=completed&verification_status=verified')
            ->assertJsonPath('data.pagination.total', 1);

        $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/admin/ocr-results?confidence=low')
            ->assertJsonPath('data.pagination.total', 1);

        $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/admin/ocr-results?confidence=high')
            ->assertJsonPath('data.pagination.total', 1);

        $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/admin/ocr-results?classification=dti_registration')
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_stats_are_computed_from_real_records(): void
    {
        Storage::fake('public');
        $this->makeProcessedDocument('permit.png', 'completed', 'dti_registration', 96, 'verified');
        $this->makeProcessedDocument('cedula.png', 'needs_review', 'cedula', 60);

        $this->actingAs($this->staff, 'sanctum')
            ->getJson('/api/v1/admin/ocr-results/stats')
            ->assertStatus(200)
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.by_status.completed', 1)
            ->assertJsonPath('data.summary.by_status.needs_review', 1)
            ->assertJsonPath('data.summary.by_verification.verified', 1)
            ->assertJsonPath('data.quality.high_percentage', 50)
            ->assertJsonPath('data.quality.low_percentage', 50);
    }

    public function test_stats_break_down_counts_by_document_type(): void
    {
        Storage::fake('public');
        $this->makeProcessedDocument('permit.png', 'completed', 'dti_registration', 90);
        $this->makeProcessedDocument('id.png', 'completed', 'government_id', 95);

        $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/v1/admin/ocr-results/stats')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.by_type');
    }

    public function test_staff_can_view_ocr_result_detail(): void
    {
        Storage::fake('public');
        $document = $this->makeProcessedDocument();

        $this->actingAs($this->staff, 'sanctum')
            ->getJson("/api/v1/admin/ocr-results/{$document->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.extraction.ocr_status', 'completed')
            ->assertJsonPath('data.extraction.fields.business_name.value', 'Sari-Sari Store');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ocr_result.viewed',
            'auditable_id' => $document->id,
        ]);
    }

    public function test_staff_can_correct_fields_and_audit_trail_is_recorded(): void
    {
        Storage::fake('public');
        $document = $this->makeProcessedDocument();
        $extraction = $document->extraction;

        $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/v1/admin/ocr-results/{$document->id}/fields", [
                'fields' => [
                    'business_name' => ['value' => 'Sari-Sari Store (Corrected)', 'confidence' => 0.98],
                ],
                'reason' => 'spelling',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.extraction.fields.business_name.value', 'Sari-Sari Store (Corrected)');

        $this->assertDatabaseHas('document_extraction_corrections', [
            'document_extraction_id' => $extraction->id,
            'field_name' => 'business_name',
            'original_value' => 'Sari-Sari Store',
            'corrected_value' => 'Sari-Sari Store (Corrected)',
            'corrected_by' => $this->staff->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ocr_result.field_corrected',
            'auditable_id' => $document->id,
        ]);
    }

    public function test_correcting_low_confidence_field_clears_review_flag(): void
    {
        Storage::fake('public');
        $document = $this->makeProcessedDocument('permit.png', 'needs_review', 'dti_registration', 60);

        $this->actingAs($this->staff, 'sanctum')
            ->patchJson("/api/v1/admin/ocr-results/{$document->id}/fields", [
                'fields' => [
                    'business_name' => ['value' => 'Sari-Sari Store', 'confidence' => 0.95],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.extraction.ocr_status', 'completed')
            ->assertJsonPath('data.status', 'processed');
    }

    public function test_staff_can_verify_ocr_result(): void
    {
        Storage::fake('public');
        $document = $this->makeProcessedDocument();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/admin/ocr-results/{$document->id}/verify")
            ->assertStatus(200)
            ->assertJsonPath('data.extraction.verification_status', 'verified')
            ->assertJsonPath('data.status', 'verified');

        $this->assertDatabaseHas('document_extractions', [
            'id' => $document->extraction->id,
            'verification_status' => 'verified',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ocr_result.verified',
            'auditable_id' => $document->id,
        ]);
    }

    public function test_reject_requires_reason(): void
    {
        Storage::fake('public');
        $document = $this->makeProcessedDocument();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/admin/ocr-results/{$document->id}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_staff_can_reject_ocr_result_with_reason(): void
    {
        Storage::fake('public');
        $document = $this->makeProcessedDocument();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/admin/ocr-results/{$document->id}/reject", [
                'reason' => 'OCR information does not match the uploaded document',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.extraction.verification_status', 'rejected')
            ->assertJsonPath('data.extraction.reject_reason', 'OCR information does not match the uploaded document')
            ->assertJsonPath('data.status', 'rejected');
    }

    public function test_staff_can_request_reupload(): void
    {
        Storage::fake('public');
        $document = $this->makeProcessedDocument();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/admin/ocr-results/{$document->id}/request-reupload")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'needs_reupload');

        $this->assertNotNull($document->fresh()->extraction->requested_reupload_at);
    }

    public function test_column_can_track_reupload(): void
    {
        Storage::fake('public');
        $document = $this->makeProcessedDocument();

        $this->assertNull($document->extraction->requested_reupload_at);
    }

    public function test_reprocess_archives_previous_attempt_as_version(): void
    {
        Storage::fake('public');
        $document = $this->makeProcessedDocument();
        $fake = $this->bindFakeOcrService();
        $fake->setOcrText("BUSINESS PERMIT\nBusiness Name: Second Read\nPermit No: 2025-045678\nValid Until: 2999-01-01");

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/admin/ocr-results/{$document->id}/reprocess")
            ->assertStatus(200)
            ->assertJsonPath('data.extraction.fields.business_name.value', 'Second Read');

        $this->assertDatabaseHas('document_extraction_versions', [
            'document_id' => $document->id,
            'attempt' => 1,
        ]);
        $this->assertDatabaseCount('document_extraction_versions', 1);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'ocr_result.reprocessed',
            'auditable_id' => $document->id,
        ]);
    }

    public function test_history_returns_archived_versions_and_current(): void
    {
        Storage::fake('public');
        $document = $this->makeProcessedDocument();

        DocumentExtractionVersion::query()->create([
            'document_id' => $document->id,
            'attempt' => 1,
            'ocr_status' => 'completed',
            'classification' => 'business_permit',
            'classification_confidence' => 0.8,
            'confidence_score' => 80,
            'ocr_text' => 'first attempt',
            'processed_at' => now(),
        ]);

        $this->actingAs($this->staff, 'sanctum')
            ->getJson("/api/v1/admin/ocr-results/{$document->id}/history")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.versions')
            ->assertJsonPath('data.versions.0.attempt', 1)
            ->assertJsonPath('data.current.is_current', true)
            ->assertJsonPath('data.current.classification', 'dti_registration');
    }

    public function test_resident_cannot_access_ocr_results(): void
    {
        Storage::fake('public');
        $this->makeProcessedDocument();

        $this->actingAs($this->resident, 'sanctum')
            ->getJson('/api/v1/admin/ocr-results')
            ->assertStatus(403);
    }

    /**
     * @param  'pending'|'completed'|'needs_review'|'failed'  $ocrStatus
     */
    private function makeProcessedDocument(
        string $name = 'permit.png',
        string $ocrStatus = 'completed',
        string $classification = 'dti_registration',
        float $confidence = 91,
        string $verificationStatus = 'pending',
    ): Document {
        $data = $this->makePng();
        $path = "documents/{$this->request->id}/{$name}";
        Storage::disk('public')->put($path, $data);

        $document = Document::query()->create([
            'documentable_type' => InspectionRequest::class,
            'documentable_id' => $this->request->id,
            'uploaded_by' => $this->resident->id,
            'document_type' => $classification,
            'file_path' => $path,
            'file_name' => $name,
            'original_name' => $name,
            'mime_type' => 'image/png',
            'file_size' => strlen($data),
            'status' => $ocrStatus === 'completed' ? 'processed' : 'needs_review',
        ]);

        $fields = [
            'business_name' => ['value' => 'Sari-Sari Store', 'confidence' => 0.95],
            'owner_name' => ['value' => 'Juan Dela Cruz', 'confidence' => 0.94],
            'permit_number' => ['value' => '2025-045678', 'confidence' => 0.9],
            'date_issued' => ['value' => '2025-01-15', 'confidence' => 0.92],
            'expiration_date' => ['value' => '2027-01-15', 'confidence' => 0.88],
        ];

        $lowConfidence = $confidence < 75
            ? ['business_name' => ['value' => 'Sari-Sari Store', 'confidence' => $confidence / 100]]
            : [];

        DocumentExtraction::query()->create([
            'document_id' => $document->id,
            'ocr_status' => $ocrStatus,
            'verification_status' => $verificationStatus,
            'classification' => $classification,
            'classification_confidence' => 0.9,
            'extracted_data' => [
                'fields' => $fields,
                'classification' => $classification,
                'classification_label' => 'DTI Business Name Registration',
                'classification_confidence' => 0.9,
                'low_confidence_fields' => $lowConfidence,
                'validation' => ['is_valid' => true, 'warnings' => []],
            ],
            'ocr_text' => "BUSINESS PERMIT\nBusiness Name: Sari-Sari Store\nPermit No: 2025-045678",
            'ocr_raw_text' => "BUSINESS PERMIT\nBusiness Name: Sari-Sari Store\nPermit No: 2025-045678",
            'business_name' => 'Sari-Sari Store',
            'owner_name' => 'Juan Dela Cruz',
            'permit_number' => '2025-045678',
            'date_issued' => '2025-01-15',
            'expiration_date' => '2027-01-15',
            'is_expired' => false,
            'low_confidence_fields' => $lowConfidence,
            'confidence_score' => $confidence,
            'ocr_engine' => 'tesseract',
            'ocr_language' => 'eng',
            'ocr_passes' => 1,
            'processing_time_ms' => 2410,
            'ai_processed_at' => now(),
        ]);

        return $document->fresh();
    }

    private function bindFakeOcrService(): object
    {
        $fake = new class(app(DocumentClassifier::class), app(DocumentFieldExtractor::class), app(DocumentValidator::class)) extends OcrService
        {
            public string $ocrText = "CITY OF CALOOCAN\nBUSINESS PERMIT\nBusiness Name: Sari-Sari Store\nOwner's Name: Juan Dela Cruz\nPermit No: 2025-045678\nDate Issued: 2025-01-15\nValid Until: 2999-01-01";

            public function setOcrText(string $text): void
            {
                $this->ocrText = $text;
            }

            protected function runTesseract(string $imagePath): string
            {
                return $this->ocrText;
            }
        };

        $this->app->instance(OcrService::class, $fake);

        return $fake;
    }

    private function makeUser(string $roleSlug, string $email): User
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        return User::query()->create([
            'role_id' => $role->id,
            'name' => ucfirst(str_replace('_', ' ', $roleSlug)),
            'email' => $email,
            'password' => bcrypt('Password123!'),
            'is_active' => true,
        ]);
    }

    private function makePng(): string
    {
        $png = imagecreatetruecolor(300, 80);
        $white = imagecolorallocate($png, 255, 255, 255);
        $black = imagecolorallocate($png, 0, 0, 0);
        imagefilledrectangle($png, 0, 0, 300, 80, $white);
        imagestring($png, 5, 10, 30, 'TEST PERMIT', $black);

        ob_start();
        imagepng($png);
        $data = (string) ob_get_clean();

        return $data;
    }
}
