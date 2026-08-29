<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Document\UploadDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Jobs\ProcessDocumentOcr;
use App\Models\Document;
use App\Models\DocumentExtraction;
use App\Models\InspectionRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Ocr\DocumentFieldExtractor;
use App\Services\Ocr\DocumentTypes;
use App\Services\Ocr\DocumentValidator;
use App\Services\Ocr\OcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use thiagoalessio\TesseractOCR\TesseractNotFoundException;

class DocumentController extends BaseApiController
{
    public function __construct(
        private readonly OcrService $ocrService,
        private readonly DocumentValidator $validator,
    ) {}

    public function index(Request $request, InspectionRequest $inspectionRequest): JsonResponse
    {
        if (! $this->canAccess($request->user(), $inspectionRequest)) {
            return $this->error('You are not authorized to view documents for this inspection request.', 403);
        }

        $documents = $inspectionRequest->documents()
            ->with(['uploader.role', 'extraction.reviewer.role'])
            ->orderByDesc('created_at')
            ->get();

        return $this->success([
            'documents' => DocumentResource::collection($documents),
        ], 'Documents retrieved successfully');
    }

    public function upload(UploadDocumentRequest $request, InspectionRequest $inspectionRequest): JsonResponse
    {
        $user = $request->user();

        if ($user->role?->slug === 'resident' && $inspectionRequest->resident_id !== $user->id) {
            return $this->error('You can only upload documents to your own inspection requests.', 403);
        }

        $file = $request->file('file');
        $path = $file->store('documents/'.$inspectionRequest->id, config('ocr.storage_disk', 'public'));

        $document = Document::query()->create([
            'documentable_type' => InspectionRequest::class,
            'documentable_id' => $inspectionRequest->id,
            'uploaded_by' => $request->user()->id,
            'document_type' => $request->validated('document_type'),
            'file_path' => $path,
            'file_name' => $file->hashName(),
            'original_name' => $this->sanitizeName($file->getClientOriginalName()),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status' => 'processing',
        ]);

        $document->load('uploader.role');

        AuditLogger::log(
            $request->user(),
            'Documents',
            'Uploaded',
            "Uploaded {$document->original_name}",
            $document,
            $request,
            newValues: ['document_type' => $document->document_type, 'file_name' => $document->original_name],
        );

        ProcessDocumentOcr::dispatch($document->id)->afterResponse();

        return $this->success(
            new DocumentResource($document),
            'Document uploaded successfully',
            201
        );
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        $document->load([
            'uploader.role',
            'extraction.reviewer.role',
            'documentable',
        ]);

        AuditLogger::log(
            $request->user(),
            'Documents',
            'Viewed',
            "Viewed document {$document->original_name}",
            $document,
            $request,
            newValues: ['document_type' => $document->document_type],
            event: 'document.viewed',
        );

        return $this->success(
            new DocumentResource($document),
            'Document retrieved successfully'
        );
    }

    public function download(Request $request, Document $document): BinaryFileResponse
    {
        return $this->streamDocument($request, $document);
    }

    public function downloadFromRequest(Request $request, InspectionRequest $inspectionRequest, Document $document): BinaryFileResponse
    {
        if ($document->documentable_type !== InspectionRequest::class || $document->documentable_id !== $inspectionRequest->id) {
            abort(404, 'Document not found on this request');
        }

        if (! $this->canAccess($request->user(), $inspectionRequest)) {
            abort(403, 'You are not authorized to download this document.');
        }

        return $this->streamDocument($request, $document);
    }

    public function ocrResult(Request $request, Document $document): JsonResponse
    {
        $document->load(['extraction.reviewer.role', 'uploader.role']);

        return $this->success(
            new DocumentResource($document),
            'OCR result retrieved successfully'
        );
    }

    public function processOcr(Request $request, Document $document): JsonResponse
    {
        // Re-run automatically when a previous attempt failed.
        $force = $document->extraction && $document->extraction->ocr_status === DocumentExtraction::OCR_STATUS_FAILED;

        if ($document->extraction && ! $force) {
            $document->load('extraction.reviewer.role', 'uploader.role');

            return $this->success(
                new DocumentResource($document),
                'Document already processed'
            );
        }

        try {
            $extraction = $this->ocrService->process($document, $force);
        } catch (TesseractNotFoundException) {
            return $this->error(
                'Tesseract OCR is not installed or the configured binary path is incorrect.',
                500
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error('OCR processing failed: '.$e->getMessage(), 500);
        }

        AuditLogger::log(
            $request->user(),
            'Documents',
            'OCR Processed',
            "OCR processed document {$document->original_name}",
            $document,
            $request,
            newValues: [
                'classification' => $extraction->classification,
                'confidence_score' => $extraction->confidence_score,
                'ocr_status' => $extraction->ocr_status,
                'is_expired' => $extraction->is_expired,
                'missing_requirements' => $extraction->missing_requirements,
            ],
            event: 'document.ocr_processed',
        );

        $document->load('extraction.reviewer.role', 'uploader.role');

        return $this->success(
            new DocumentResource($document),
            'Document processed successfully'
        );
    }

    public function reprocessOcr(Request $request, Document $document): JsonResponse
    {
        try {
            $extraction = $this->ocrService->process($document, force: true);
        } catch (TesseractNotFoundException) {
            return $this->error(
                'Tesseract OCR is not installed or the configured binary path is incorrect.',
                500
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error('OCR processing failed: '.$e->getMessage(), 500);
        }

        AuditLogger::log(
            $request->user(),
            'Documents',
            'OCR Reprocessed',
            "Re-processed OCR for document {$document->original_name}",
            $document,
            $request,
            newValues: [
                'classification' => $extraction->classification,
                'confidence_score' => $extraction->confidence_score,
                'ocr_status' => $extraction->ocr_status,
            ],
            event: 'document.ocr_reprocessed',
        );

        $document->load('extraction.reviewer.role', 'uploader.role');

        return $this->success(
            new DocumentResource($document),
            'Document reprocessed successfully'
        );
    }

    public function updateExtraction(Request $request, Document $document): JsonResponse
    {
        if (! $document->extraction) {
            return $this->error('Document has not been OCR-processed yet.', 409);
        }

        $validated = $request->validate([
            'classification' => ['nullable', 'string', 'max:100', 'in:'.implode(',', DocumentTypes::ALL)],
            'fields' => ['nullable', 'array'],
            'fields.*.value' => ['nullable'],
            'fields.*.confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $extraction = $document->extraction;
        $data = $extraction->extracted_data ?? [];
        $data['fields'] = $data['fields'] ?? [];

        if ($validated['fields'] ?? false) {
            foreach ($validated['fields'] as $field => $overrides) {
                if (! is_string($field)) {
                    continue;
                }

                $current = $data['fields'][$field] ?? ['value' => null, 'confidence' => 0.0];

                $value = array_key_exists('value', $overrides) ? $overrides['value'] : ($current['value'] ?? null);
                $confidence = isset($overrides['confidence']) ? (float) $overrides['confidence'] : (float) ($current['confidence'] ?? 0.0);

                $data['fields'][$field] = [
                    'value' => $value === '' ? null : $value,
                    'confidence' => max(0.0, min(1.0, $confidence)),
                ];
            }
        }

        $classification = $validated['classification'] ?? $extraction->classification;
        $data['classification'] = $classification;
        $data['classification_label'] = DocumentTypes::label($classification);

        $lowConfidence = (new DocumentFieldExtractor)->lowConfidenceFields($data['fields']);
        $data['low_confidence_fields'] = $lowConfidence;
        $data['validation'] = $this->validator->validate($document, $classification, $data['fields']);

        $hasReviewItems = $lowConfidence !== []
            || $classification === DocumentTypes::UNKNOWN
            || ($data['validation']['is_valid'] ?? true) === false;

        $extraction->update([
            'classification' => $classification,
            'extracted_data' => $data,
            'business_name' => $this->fieldValue($data['fields'], 'business_name'),
            'owner_name' => $this->fieldValue($data['fields'], 'owner_name'),
            'permit_number' => $this->fieldValue($data['fields'], 'permit_number'),
            'issuing_authority' => $this->fieldValue($data['fields'], 'issuing_authority'),
            'issuing_office' => $this->fieldValue($data['fields'], 'issuing_office'),
            'date_issued' => $this->fieldValue($data['fields'], 'date_issued'),
            'expiration_date' => $this->fieldValue($data['fields'], 'expiration_date'),
            'issue_date' => $this->fieldValue($data['fields'], 'issue_date'),
            'establishment_name' => $this->fieldValue($data['fields'], 'establishment_name') ?? $this->fieldValue($data['fields'], 'business_name'),
            'certificate_number' => $this->fieldValue($data['fields'], 'certificate_number'),
            'low_confidence_fields' => $lowConfidence,
            'ocr_status' => $hasReviewItems ? DocumentExtraction::OCR_STATUS_NEEDS_REVIEW : DocumentExtraction::OCR_STATUS_COMPLETED,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $this->syncDocumentStatus($document, $hasReviewItems);

        AuditLogger::log(
            $request->user(),
            'Documents',
            'Extraction Updated',
            "Updated OCR extraction for document {$document->original_name}",
            $document,
            $request,
            newValues: ['classification' => $classification, 'fields_updated' => array_keys($validated['fields'] ?? [])],
            event: 'document.extraction_updated',
        );

        $document->load('extraction.reviewer.role', 'uploader.role');

        return $this->success(
            new DocumentResource($document),
            'Extraction updated successfully'
        );
    }

    public function verify(Request $request, Document $document): JsonResponse
    {
        $validated = $request->validate([
            'classification' => ['nullable', 'string', 'max:100', 'in:'.implode(',', DocumentTypes::ALL)],
            'extracted_data' => ['nullable', 'array'],
            'confidence_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_expired' => ['nullable', 'boolean'],
            'missing_requirements' => ['nullable', 'array'],
            'status' => ['required', 'string', 'in:verified,rejected'],
        ]);

        if ($document->extraction) {
            $document->extraction->update([
                'classification' => $validated['classification'] ?? $document->extraction->classification,
                'extracted_data' => $validated['extracted_data'] ?? $document->extraction->extracted_data,
                'confidence_score' => $validated['confidence_score'] ?? $document->extraction->confidence_score,
                'is_expired' => $validated['is_expired'] ?? $document->extraction->is_expired,
                'missing_requirements' => $validated['missing_requirements'] ?? $document->extraction->missing_requirements,
                'ocr_status' => $validated['status'] === 'verified' ? DocumentExtraction::OCR_STATUS_COMPLETED : $document->extraction->ocr_status,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);
        }

        $document->update([
            'status' => $validated['status'],
        ]);

        AuditLogger::log(
            $request->user(),
            'Documents',
            $validated['status'] === 'verified' ? 'Verified' : 'Rejected',
            ucfirst($validated['status'])." document {$document->original_name}",
            $document,
            $request,
            newValues: [
                'status' => $validated['status'],
                'classification' => $validated['classification'] ?? null,
                'is_expired' => $validated['is_expired'] ?? null,
            ],
            event: 'document.verified',
        );

        $document->load('extraction.reviewer.role', 'uploader.role');

        return $this->success(
            new DocumentResource($document),
            'Document '.$validated['status'].' successfully'
        );
    }

    private function fieldValue(array $fields, string $field): ?string
    {
        $value = $fields[$field]['value'] ?? null;

        return $value === null ? null : (string) $value;
    }

    private function syncDocumentStatus(Document $document, bool $needsReview): void
    {
        if ($needsReview) {
            $document->update(['status' => 'needs_review']);

            return;
        }

        if (in_array($document->status, ['pending', 'processing', 'needs_review', 'failed'], true)) {
            $document->update(['status' => 'processed']);
        }
    }

    private function sanitizeName(string $name): string
    {
        $name = str_replace(['/', '\\', "\0", '<', '>'], '', trim($name));

        return $name === '' ? 'document' : $name;
    }

    private function streamDocument(Request $request, Document $document): BinaryFileResponse
    {
        $disk = config('ocr.storage_disk', 'public');

        if (! Storage::disk($disk)->exists($document->file_path)) {
            abort(404, 'File not found');
        }

        AuditLogger::log(
            $request->user(),
            'Documents',
            'Viewed',
            "Downloaded document {$document->original_name}",
            $document,
            $request,
            newValues: ['document_type' => $document->document_type, 'file_name' => $document->original_name],
            event: 'document.downloaded',
        );

        return response()->file(
            Storage::disk($disk)->path($document->file_path),
            [
                'Content-Disposition' => 'inline; filename="'.basename($document->original_name ?? $document->file_path).'"',
            ]
        );
    }

    private function canAccess(User $user, InspectionRequest $inspectionRequest): bool
    {
        if (in_array($user->role?->slug, ['administrator', 'barangay_staff', 'inspector'], true)) {
            return true;
        }

        return $inspectionRequest->resident_id === $user->id;
    }
}
