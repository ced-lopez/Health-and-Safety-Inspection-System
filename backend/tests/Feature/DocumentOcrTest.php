<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentOcr;
use App\Models\ApplicationType;
use App\Models\Document;
use App\Models\DocumentExtraction;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentOcrTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;

    protected User $resident;

    protected InspectionRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, InspectionTaxonomySeeder::class]);

        $this->staff = $this->makeUser('barangay_staff', 'ocrstaff@example.com');
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

    public function test_guest_cannot_process_ocr(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument();

        $this->postJson("/api/v1/documents/{$document->id}/process-ocr")
            ->assertStatus(401);
    }

    public function test_staff_can_download_document_file(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument('image/png', 'id.png');

        $this->actingAs($this->staff, 'sanctum')
            ->get("/api/v1/documents/{$document->id}/download")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'image/png');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'document.downloaded',
            'auditable_id' => $document->id,
        ]);
    }

    public function test_download_returns_404_when_file_missing(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument();

        Storage::disk('public')->delete($document->file_path);

        $this->actingAs($this->staff, 'sanctum')
            ->get("/api/v1/documents/{$document->id}/download")
            ->assertStatus(404);
    }

    public function test_guest_cannot_download_document_file(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument();

        $this->getJson("/api/v1/documents/{$document->id}/download")
            ->assertStatus(401);
    }

    public function test_owner_resident_can_download_own_document_file(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument('image/png', 'id.png');

        $this->actingAs($this->resident, 'sanctum')
            ->get("/api/v1/inspection-requests/{$this->request->id}/documents/{$document->id}/download")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_other_resident_cannot_download_request_document_file(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument();
        $other = $this->makeUser('resident', 'otherresident@example.com');

        $this->actingAs($other, 'sanctum')
            ->get("/api/v1/inspection-requests/{$this->request->id}/documents/{$document->id}/download")
            ->assertStatus(403);
    }

    public function test_download_from_request_rejects_unrelated_document(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument();
        $unrelated = Document::query()->create([
            'documentable_type' => InspectionRequest::class,
            'documentable_id' => $this->request->id + 999,
            'uploaded_by' => $this->resident->id,
            'document_type' => 'business_permit',
            'file_path' => 'documents/unrelated.png',
            'file_name' => 'unrelated.png',
            'original_name' => 'unrelated.png',
            'mime_type' => 'image/png',
            'file_size' => 1024,
            'status' => 'pending',
        ]);

        $this->actingAs($this->staff, 'sanctum')
            ->get("/api/v1/inspection-requests/{$this->request->id}/documents/{$unrelated->id}/download")
            ->assertStatus(404);
    }

    public function test_staff_can_process_ocr_and_store_extraction(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument();
        $this->bindFakeOcrService();

        $response = $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/documents/{$document->id}/process-ocr");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'processed')
            ->assertJsonPath('data.extraction.classification', 'business_permit')
            ->assertJsonPath('data.extraction.ocr_status', 'completed')
            ->assertJsonPath('data.extraction.fields.business_name.value', 'Sari-Sari Store');

        $this->assertDatabaseHas('document_extractions', [
            'document_id' => $document->id,
            'classification' => 'business_permit',
            'ocr_status' => 'completed',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'document.ocr_processed',
            'auditable_id' => $document->id,
        ]);

        $extraction = $document->extraction;
        $this->assertSame('Sari-Sari Store', $extraction->business_name);
        $this->assertSame('Juan Dela Cruz', $extraction->owner_name);
        $this->assertSame('2025-01-15', $extraction->date_issued->toDateString());
        $this->assertFalse($extraction->is_expired);
        $this->assertGreaterThan(50, (float) $extraction->confidence_score);
        $this->assertStringContainsString('BUSINESS PERMIT', $extraction->ocr_text);
        $this->assertNotNull($extraction->processing_time_ms);
    }

    public function test_ocr_detects_missing_requirements_for_request(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument();
        $this->bindFakeOcrService();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/documents/{$document->id}/process-ocr")
            ->assertStatus(200);

        $missing = $document->extraction->missing_requirements ?? [];
        $types = array_column($missing, 'document_type');

        // Business permit is present, but Barangay ID is still missing for a new application.
        $this->assertNotContains('business_permit', $types);
        $this->assertContains('barangay_id', $types);
    }

    public function test_ocr_marks_expired_documents(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument();
        $fake = $this->bindFakeOcrService();
        $fake->setOcrText("BUSINESS PERMIT\nBusiness Name: Old Store\nValid Until: 2020-01-01");

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/documents/{$document->id}/process-ocr")
            ->assertStatus(200);

        $this->assertTrue($document->extraction->is_expired);
    }

    public function test_low_confidence_fields_flag_document_for_review(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument();
        $fake = $this->bindFakeOcrService();
        $fake->setOcrText("BUSINESS PERMIT\nBusiness Name: ABC FOOD H0USE\nValid Until: 2999-01-01");

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/documents/{$document->id}/process-ocr")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'needs_review')
            ->assertJsonPath('data.extraction.ocr_status', 'needs_staff_verification');

        $low = $document->extraction->low_confidence_fields ?? [];
        $fields = array_column($low, 'field');
        $this->assertContains('business_name', $fields);
    }

    public function test_unknown_document_is_flagged_for_review(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument();
        $fake = $this->bindFakeOcrService();
        $fake->setOcrText('random scribble text with no document meaning');

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/documents/{$document->id}/process-ocr")
            ->assertStatus(200)
            ->assertJsonPath('data.extraction.classification', 'unknown')
            ->assertJsonPath('data.extraction.ocr_status', 'needs_staff_verification')
            ->assertJsonPath('data.status', 'needs_review');
    }

    public function test_pdf_document_is_handled_without_crashing(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument('application/pdf', 'permit.pdf');
        $this->bindFakeOcrService();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/documents/{$document->id}/process-ocr")
            ->assertStatus(200)
            ->assertJsonPath('data.extraction.classification', 'unknown')
            ->assertJsonPath('data.extraction.ocr_status', 'needs_staff_verification');

        $extraction = $document->extraction;
        $this->assertSame('unknown', $extraction->classification);
        $this->assertStringContainsString('PDF', $extraction->extracted_data['warning'] ?? '');
    }

    public function test_image_document_can_be_processed(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument('image/png', 'id.png');
        $fake = $this->bindFakeOcrService();
        $fake->setOcrText(
            "REPUBLIC OF THE PHILIPPINES\nUNIFIED MULTIPURPOSE ID\nFull Name: JUAN DELA CRUZ\nID No: 1234-5678-9012\nDate of Birth: 1990-05-14"
        );

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/documents/{$document->id}/process-ocr")
            ->assertStatus(200)
            ->assertJsonPath('data.extraction.classification', 'government_id')
            ->assertJsonPath('data.extraction.fields.full_name.value', 'JUAN DELA CRUZ');
    }

    public function test_staff_can_fetch_ocr_result(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument();
        $fake = $this->bindFakeOcrService();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/documents/{$document->id}/process-ocr")
            ->assertStatus(200);

        $this->actingAs($this->staff, 'sanctum')
            ->getJson("/api/v1/documents/{$document->id}/ocr")
            ->assertStatus(200)
            ->assertJsonPath('data.extraction.classification', 'business_permit')
            ->assertJsonPath('data.extraction.ocr_text', $fake->ocrText);
    }

    public function test_staff_can_reprocess_document(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument();
        $fake = $this->bindFakeOcrService();
        $fake->setOcrText("BUSINESS PERMIT\nBusiness Name: First Read");

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/documents/{$document->id}/process-ocr")
            ->assertStatus(200)
            ->assertJsonPath('data.extraction.fields.business_name.value', 'First Read');

        $fake->setOcrText("BUSINESS PERMIT\nBusiness Name: Corrected Read\nValid Until: 2999-01-01");

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/documents/{$document->id}/reprocess")
            ->assertStatus(200)
            ->assertJsonPath('data.extraction.fields.business_name.value', 'Corrected Read');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'document.ocr_reprocessed',
            'auditable_id' => $document->id,
        ]);
    }

    public function test_staff_can_correct_extracted_fields(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument();
        $fake = $this->bindFakeOcrService();
        $fake->setOcrText("BUSINESS PERMIT\nPermit No: 2025-045678\nBusiness Name: Sari-Sari StOre");

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/documents/{$document->id}/process-ocr")
            ->assertStatus(200);

        $response = $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/v1/documents/{$document->id}/extraction", [
                'fields' => [
                    'business_name' => ['value' => 'Sari-Sari Store', 'confidence' => 0.98],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.extraction.fields.business_name.value', 'Sari-Sari Store')
            ->assertJsonPath('data.extraction.ocr_status', 'completed')
            ->assertJsonPath('data.status', 'processed');

        $this->assertDatabaseHas('document_extractions', [
            'document_id' => $document->id,
            'business_name' => 'Sari-Sari Store',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'document.extraction_updated',
            'auditable_id' => $document->id,
        ]);
    }

    public function test_staff_can_verify_document_and_logs_audit(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument();

        $this->actingAs($this->staff, 'sanctum')
            ->putJson("/api/v1/documents/{$document->id}/verify", [
                'status' => 'verified',
                'classification' => 'business_permit',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'verified');

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'document.verified',
            'auditable_id' => $document->id,
        ]);
    }

    public function test_upload_rejects_unsupported_file_type(): void
    {
        Storage::fake('public');

        $this->actingAs($this->resident, 'sanctum')
            ->postJson("/api/v1/inspection-requests/{$this->request->id}/documents", [
                'document_type' => 'government_id',
                'file' => UploadedFile::fake()->create('script.txt', 10),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_upload_rejects_oversized_file(): void
    {
        Storage::fake('public');
        config()->set('ocr.max_file_size_kb', 100);

        $this->actingAs($this->resident, 'sanctum')
            ->postJson("/api/v1/inspection-requests/{$this->request->id}/documents", [
                'document_type' => 'government_id',
                'file' => UploadedFile::fake()->image('big.png')->size(200),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('documents', 0);
    }

    public function test_upload_accepts_allowed_file_types(): void
    {
        Bus::fake([ProcessDocumentOcr::class]);
        Storage::fake('public');

        $this->actingAs($this->resident, 'sanctum')
            ->postJson("/api/v1/inspection-requests/{$this->request->id}/documents", [
                'document_type' => 'government_id',
                'file' => UploadedFile::fake()->image('id.jpg'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.document_type', 'government_id');

        $this->assertDatabaseCount('documents', 1);
    }

    public function test_upload_dispatches_automatic_ocr_job(): void
    {
        Bus::fake([ProcessDocumentOcr::class]);
        Storage::fake('public');

        $this->actingAs($this->resident, 'sanctum')
            ->postJson("/api/v1/inspection-requests/{$this->request->id}/documents", [
                'document_type' => 'government_id',
                'file' => UploadedFile::fake()->image('id.png'),
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'processing');

        Bus::assertDispatchedAfterResponse(ProcessDocumentOcr::class);

        $this->assertDatabaseHas('documents', [
            'status' => 'processing',
        ]);
    }

    public function test_automatic_ocr_job_processes_uploaded_document(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument();
        $this->bindFakeOcrService();

        (new ProcessDocumentOcr($document->id))->handle(app(OcrService::class));

        $this->assertDatabaseHas('document_extractions', [
            'document_id' => $document->id,
            'classification' => 'business_permit',
            'ocr_status' => 'completed',
        ]);

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => 'processed',
        ]);
    }

    public function test_automatic_ocr_job_skips_already_processed_document(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument(attributes: ['status' => 'processed']);
        DocumentExtraction::query()->create([
            'document_id' => $document->id,
            'classification' => 'business_permit',
            'ocr_status' => 'completed',
        ]);
        $this->bindFakeOcrService();

        (new ProcessDocumentOcr($document->id))->handle(app(OcrService::class));

        $this->assertSame(1, DocumentExtraction::query()
            ->where('document_id', $document->id)
            ->count());
    }

    public function test_resident_cannot_upload_to_other_requests(): void
    {
        Storage::fake('public');
        $other = $this->makeUser('resident', 'other@example.com');

        $this->actingAs($other, 'sanctum')
            ->postJson("/api/v1/inspection-requests/{$this->request->id}/documents", [
                'document_type' => 'government_id',
                'file' => UploadedFile::fake()->image('id.png'),
            ])
            ->assertStatus(403);
    }

    private function bindFakeOcrService(): object
    {
        $fake = new class(app(DocumentClassifier::class), app(DocumentFieldExtractor::class), app(DocumentValidator::class)) extends OcrService
        {
            public string $ocrText = "CITY OF CALOOCAN\nBUSINESS PERMIT\nBusiness Name: Sari-Sari Store\nOwner's Name: Juan Dela Cruz\nPermit No: 2025-045678\nDate Issued: 2025-01-15\nValid Until: 2999-01-01\nIssued by the Office of the Barangay Captain";

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

    private function makeDocument(string $mime = 'image/png', string $name = 'permit.png', array $attributes = []): Document
    {
        $data = $mime === 'application/pdf'
            ? '%PDF-1.4 test fixture'
            : $this->makePng();

        $path = "documents/{$this->request->id}/{$name}";
        Storage::disk('public')->put($path, $data);

        return Document::query()->create(array_merge([
            'documentable_type' => InspectionRequest::class,
            'documentable_id' => $this->request->id,
            'uploaded_by' => $this->resident->id,
            'document_type' => 'business_permit',
            'file_path' => $path,
            'file_name' => $name,
            'original_name' => $name,
            'mime_type' => $mime,
            'file_size' => strlen($data),
            'status' => 'pending',
        ], $attributes));
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
}
