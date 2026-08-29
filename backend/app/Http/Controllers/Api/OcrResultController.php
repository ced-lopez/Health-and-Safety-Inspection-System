<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\DocumentExtractionVersionResource;
use App\Http\Resources\OcrResultResource;
use App\Models\Document;
use App\Models\DocumentExtraction;
use App\Models\DocumentExtractionCorrection;
use App\Services\AuditLogger;
use App\Services\Ocr\DocumentFieldExtractor;
use App\Services\Ocr\DocumentTypes;
use App\Services\Ocr\DocumentValidator;
use App\Services\Ocr\OcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use thiagoalessio\TesseractOCR\TesseractNotFoundException;

/**
 * Dedicated OCR Results module for staff/admin: listing, dashboard statistics,
 * review detail, field corrections (with audit trail), verification workflow,
 * re-upload requests and reprocessing history.
 */
class OcrResultController extends BaseApiController
{
    public function __construct(
        private readonly OcrService $ocrService,
        private readonly DocumentValidator $validator,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Document::query()
            ->with(['extraction', 'uploader.role', 'documentable']);

        $search = trim((string) $request->input('search'));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like) {
                $q->whereRaw('LOWER(original_name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(file_name) LIKE ?', [$like])
                    ->orWhereHas('extraction', function ($e) use ($like) {
                        $e->whereRaw('LOWER(classification) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(business_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(owner_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(permit_number) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(certificate_number) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(establishment_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(issuing_authority) LIKE ?', [$like]);
                    })
                    ->orWhereHas('documentable', function ($d) use ($like) {
                        $d->whereRaw('LOWER(request_number) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(applicant_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(business_name) LIKE ?', [$like]);
                    });
            });
        }

        if ($request->filled('ocr_status')) {
            $query->whereHas('extraction', fn ($q) => $q->where('ocr_status', $request->input('ocr_status')));
        }

        if ($request->filled('verification_status')) {
            $query->whereHas('extraction', fn ($q) => $q->where('verification_status', $request->input('verification_status')));
        }

        if ($request->filled('classification')) {
            $query->whereHas('extraction', fn ($q) => $q->where('classification', $request->input('classification')));
        }

        if ($request->filled('confidence')) {
            $query->whereHas('extraction', fn ($q) => $this->confidenceScope($q, $request->input('confidence')));
        }

        if ($request->filled('document_type')) {
            $canonical = DocumentTypes::canonical((string) $request->input('document_type'));
            if ($canonical !== null) {
                $query->whereHas('extraction', fn ($q) => $q->where('classification', $canonical));
            }
        }

        if ($request->filled('date_from')) {
            $query->whereHas('extraction', fn ($q) => $q->whereDate('ai_processed_at', '>=', $request->input('date_from')));
        }

        if ($request->filled('date_to')) {
            $query->whereHas('extraction', fn ($q) => $q->whereDate('ai_processed_at', '<=', $request->input('date_to')));
        }

        $results = $query
            ->orderByDesc('created_at')
            ->paginate((int) ($request->input('per_page') ?? 15));

        return $this->success([
            'results' => OcrResultResource::collection($results),
            'pagination' => [
                'total' => $results->total(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'last_page' => $results->lastPage(),
            ],
        ], 'OCR results retrieved successfully');
    }

    public function stats(): JsonResponse
    {
        $base = DocumentExtraction::query();

        $total = (clone $base)->count();
        $avgConfidence = (clone $base)->avg('confidence_score');
        $avgTime = (clone $base)->avg('processing_time_ms');

        $byStatus = $this->countBy($base, 'ocr_status');
        $byVerification = $this->countBy($base, 'verification_status');

        $confidence = ['high' => 0, 'medium' => 0, 'low' => 0];
        foreach ((clone $base)->whereNotNull('confidence_score')->pluck('confidence_score') as $score) {
            $bucket = $score >= 90 ? 'high' : ($score >= 75 ? 'medium' : 'low');
            $confidence[$bucket]++;
        }

        $byType = DocumentExtraction::query()
            ->whereNotNull('classification')
            ->selectRaw('classification, count(*) as total')
            ->groupBy('classification')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'type' => $row->classification,
                'label' => DocumentTypes::label($row->classification),
                'count' => (int) $row->total,
            ])
            ->values();

        $needsReview = (int) (clone $base)->where('ocr_status', DocumentExtraction::OCR_STATUS_NEEDS_REVIEW)->count();
        $manuallyCorrected = DocumentExtractionCorrection::query()->distinct('document_id')->count('document_id');

        $totalConfidenceBuckets = array_sum($confidence);

        return $this->success([
            'summary' => [
                'total' => $total,
                'by_status' => $byStatus,
                'by_verification' => $byVerification,
                'by_confidence' => $confidence,
                'avg_confidence' => round((float) $avgConfidence, 2),
                'avg_processing_time_ms' => (int) round((float) $avgTime),
                'needs_review' => $needsReview,
                'manually_corrected' => $manuallyCorrected,
            ],
            'by_type' => $byType,
            'quality' => [
                'avg_confidence' => round((float) $avgConfidence, 2),
                'high_percentage' => $totalConfidenceBuckets > 0 ? round(($confidence['high'] / $totalConfidenceBuckets) * 100, 1) : 0,
                'medium_percentage' => $totalConfidenceBuckets > 0 ? round(($confidence['medium'] / $totalConfidenceBuckets) * 100, 1) : 0,
                'low_percentage' => $totalConfidenceBuckets > 0 ? round(($confidence['low'] / $totalConfidenceBuckets) * 100, 1) : 0,
                'needs_review' => $needsReview,
                'manually_corrected' => $manuallyCorrected,
            ],
        ], 'OCR statistics retrieved successfully');
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        $document->load([
            'extraction.reviewer.role',
            'extraction.corrections.corrector.role',
            'uploader.role',
            'documentable',
            'ocrVersions',
            'corrections.corrector.role',
        ]);

        AuditLogger::log(
            $request->user(),
            'OCR Results',
            'Viewed',
            "Viewed OCR result for {$document->original_name}",
            $document,
            $request,
            newValues: ['classification' => $document->extraction?->classification, 'ocr_status' => $document->extraction?->ocr_status],
            event: 'ocr_result.viewed',
        );

        return $this->success(
            new OcrResultResource($document),
            'OCR result retrieved successfully'
        );
    }

    public function updateFields(Request $request, Document $document): JsonResponse
    {
        if ($document->extraction === null) {
            return $this->error('Document has not been OCR-processed yet.', 409);
        }

        $validated = $request->validate([
            'fields' => ['required', 'array'],
            'fields.*.value' => ['nullable'],
            'fields.*.confidence' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $extraction = $document->extraction;
        $data = $extraction->extracted_data ?? [];
        $data['fields'] = $data['fields'] ?? [];
        $reason = $validated['reason'] ?? null;
        $now = now();

        foreach ($validated['fields'] as $field => $overrides) {
            if (! is_string($field)) {
                continue;
            }

            $current = $data['fields'][$field] ?? ['value' => null, 'confidence' => 0.0];
            $value = array_key_exists('value', $overrides) ? ($overrides['value'] === '' ? null : $overrides['value']) : ($current['value'] ?? null);
            $confidence = isset($overrides['confidence']) ? (float) $overrides['confidence'] : (float) ($current['confidence'] ?? 0.0);
            $confidence = max(0.0, min(1.0, $confidence));

            // Record an audit-trail entry whenever an extracted value changes.
            if ((string) $current['value'] !== (string) $value) {
                DocumentExtractionCorrection::query()->create([
                    'document_extraction_id' => $extraction->id,
                    'document_id' => $document->id,
                    'field_name' => $field,
                    'original_value' => $current['value'],
                    'corrected_value' => $value,
                    'reason' => $reason,
                    'corrected_by' => $request->user()->id,
                    'corrected_at' => $now,
                ]);
            }

            $data['fields'][$field] = ['value' => $value, 'confidence' => $confidence];
        }

        $classification = $data['classification'] ?? $extraction->classification;
        $data['classification_label'] = DocumentTypes::label($classification);

        $lowConfidence = (new DocumentFieldExtractor)->lowConfidenceFields($data['fields']);
        $data['low_confidence_fields'] = $lowConfidence;
        $data['validation'] = $this->validator->validate($document, $classification, $data['fields']);

        $hasReviewItems = $lowConfidence !== []
            || $classification === DocumentTypes::UNKNOWN
            || ($data['validation']['is_valid'] ?? true) === false;

        $extraction->update([
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
            'last_edited_by' => $request->user()->id,
            'last_edited_at' => $now,
        ]);

        $this->syncDocumentStatus($document, $hasReviewItems);

        AuditLogger::log(
            $request->user(),
            'OCR Results',
            'Field Corrected',
            "Corrected OCR fields for {$document->original_name}",
            $document,
            $request,
            newValues: ['fields_updated' => array_keys($validated['fields']),
                'classification' => $classification,
                'reason' => $reason],
            event: 'ocr_result.field_corrected',
        );

        $document->load([
            'extraction.reviewer.role',
            'extraction.corrections.corrector.role',
            'uploader.role',
            'documentable',
            'corrections.corrector.role',
        ]);

        return $this->success(
            new OcrResultResource($document),
            'OCR fields updated successfully'
        );
    }

    public function verify(Request $request, Document $document): JsonResponse
    {
        if ($document->extraction === null) {
            return $this->error('Document has not been OCR-processed yet.', 409);
        }

        $document->extraction->update([
            'verification_status' => DocumentExtraction::VERIFICATION_VERIFIED,
            'ocr_status' => DocumentExtraction::OCR_STATUS_COMPLETED,
            'reject_reason' => null,
            'requested_reupload_at' => null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $document->update(['status' => 'verified']);

        AuditLogger::log(
            $request->user(),
            'OCR Results',
            'Verified',
            "Verified OCR result for {$document->original_name}",
            $document,
            $request,
            newValues: ['status' => 'verified', 'classification' => $document->extraction->classification],
            event: 'ocr_result.verified',
        );

        $document->load(['extraction.reviewer.role', 'uploader.role', 'documentable', 'corrections.corrector.role']);

        return $this->success(
            new OcrResultResource($document),
            'OCR result verified successfully'
        );
    }

    public function reject(Request $request, Document $document): JsonResponse
    {
        if ($document->extraction === null) {
            return $this->error('Document has not been OCR-processed yet.', 409);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $document->extraction->update([
            'verification_status' => DocumentExtraction::VERIFICATION_REJECTED,
            'reject_reason' => $validated['reason'],
            'requested_reupload_at' => null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $document->update(['status' => 'rejected']);

        AuditLogger::log(
            $request->user(),
            'OCR Results',
            'Rejected',
            "Rejected OCR result for {$document->original_name}",
            $document,
            $request,
            newValues: ['status' => 'rejected', 'reason' => $validated['reason']],
            event: 'ocr_result.rejected',
        );

        $document->load(['extraction.reviewer.role', 'uploader.role', 'documentable']);

        return $this->success(
            new OcrResultResource($document),
            'OCR result rejected successfully'
        );
    }

    public function requestReupload(Request $request, Document $document): JsonResponse
    {
        if ($document->extraction === null) {
            return $this->error('Document has not been OCR-processed yet.', 409);
        }

        $document->extraction->update([
            'requested_reupload_at' => now(),
            'ocr_status' => DocumentExtraction::OCR_STATUS_NEEDS_REVIEW,
        ]);

        $document->update(['status' => 'needs_reupload']);

        AuditLogger::log(
            $request->user(),
            'OCR Results',
            'Re-upload Requested',
            "Requested re-upload for {$document->original_name}",
            $document,
            $request,
            newValues: ['status' => 'needs_reupload'],
            event: 'ocr_result.reupload_requested',
        );

        $document->load(['extraction.reviewer.role', 'uploader.role', 'documentable']);

        return $this->success(
            new OcrResultResource($document),
            'Re-upload requested successfully'
        );
    }

    public function reprocess(Request $request, Document $document): JsonResponse
    {
        try {
            $this->ocrService->process($document, force: true);
        } catch (TesseractNotFoundException) {
            return $this->error(
                'Tesseract OCR is not installed or the configured binary path is incorrect.',
                500
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error('OCR processing failed: '.$e->getMessage(), 500);
        }

        $document->load([
            'extraction.reviewer.role',
            'extraction.corrections.corrector.role',
            'uploader.role',
            'documentable',
            'ocrVersions',
            'corrections.corrector.role',
        ]);

        AuditLogger::log(
            $request->user(),
            'OCR Results',
            'Reprocessed',
            "Reprocessed OCR for {$document->original_name}",
            $document,
            $request,
            newValues: [
                'classification' => $document->extraction?->classification,
                'confidence_score' => $document->extraction?->confidence_score,
                'ocr_status' => $document->extraction?->ocr_status,
            ],
            event: 'ocr_result.reprocessed',
        );

        return $this->success(
            new OcrResultResource($document),
            'OCR reprocessed successfully'
        );
    }

    public function history(Request $request, Document $document): JsonResponse
    {
        $versions = $document->ocrVersions()->get();

        $current = $document->extraction ? [
            'attempt' => $versions->max('attempt') + 1,
            'is_current' => true,
            'ocr_status' => $document->extraction->ocr_status,
            'classification' => $document->extraction->classification,
            'classification_label' => $document->extraction->extracted_data['classification_label'] ?? null,
            'classification_confidence' => $document->extraction->classification_confidence,
            'confidence_score' => $document->extraction->confidence_score,
            'ocr_engine' => $document->extraction->ocr_engine,
            'ocr_language' => $document->extraction->ocr_language,
            'ocr_passes' => $document->extraction->ocr_passes,
            'processing_time_ms' => $document->extraction->processing_time_ms,
            'ocr_error' => $document->extraction->ocr_error,
            'processed_at' => ($document->extraction->ai_processed_at ?? $document->extraction->updated_at)?->toIso8601String(),
        ] : null;

        AuditLogger::log(
            $request->user(),
            'OCR Results',
            'Viewed History',
            "Viewed OCR history for {$document->original_name}",
            $document,
            $request,
            event: 'ocr_result.history_viewed',
        );

        return $this->success([
            'versions' => DocumentExtractionVersionResource::collection($versions),
            'current' => $current,
        ], 'OCR history retrieved successfully');
    }

    private function countBy($query, string $column): array
    {
        return $query->clone()->select($column)->get()
            ->groupBy($column)
            ->map->count()
            ->all();
    }

    private function confidenceScope($query, string $bucket)
    {
        return match ($bucket) {
            'high' => $query->where('confidence_score', '>=', 90),
            'medium' => $query->whereBetween('confidence_score', [75, 89.99999]),
            default => $query->where('confidence_score', '<', 75),
        };
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
}
