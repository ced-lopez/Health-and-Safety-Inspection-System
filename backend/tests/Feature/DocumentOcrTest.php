<?php

namespace Tests\Feature;

use App\Models\ApplicationType;
use App\Models\Document;
use App\Models\InspectionCategory;
use App\Models\InspectionRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Ocr\DocumentClassifier;
use App\Services\Ocr\DocumentFieldExtractor;
use App\Services\Ocr\OcrService;
use Database\Seeders\InspectionTaxonomySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $otherRequest = $this->request;
        $unrelated = Document::query()->create([
            'documentable_type' => InspectionRequest::class,
            'documentable_id' => $otherRequest->id + 999,
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
            ->assertJsonPath('data.status', 'processed');

        $this->assertDatabaseHas('document_extractions', [
            'document_id' => $document->id,
            'classification' => 'business_permit',
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

    public function test_pdf_document_is_handled_without_crashing(): void
    {
        Storage::fake('public');
        $document = $this->makeDocument('application/pdf', 'permit.pdf');
        $this->bindFakeOcrService();

        $this->actingAs($this->staff, 'sanctum')
            ->postJson("/api/v1/documents/{$document->id}/process-ocr")
            ->assertStatus(200);

        $extraction = $document->extraction;
        $this->assertNull($extraction->classification);
        $this->assertStringContainsString('PDF', $extraction->extracted_data['warning'] ?? '');
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

    private function bindFakeOcrService(): object
    {
        $fake = new class(app(DocumentClassifier::class), app(DocumentFieldExtractor::class)) extends OcrService
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

    private function makeDocument(string $mime = 'image/png', string $name = 'permit.png'): Document
    {
        $data = $mime === 'application/pdf'
            ? '%PDF-1.4 test fixture'
            : $this->makePng();

        $path = "documents/{$this->request->id}/{$name}";
        Storage::disk('public')->put($path, $data);

        return Document::query()->create([
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
