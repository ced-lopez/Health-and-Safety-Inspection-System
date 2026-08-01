<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Document\UploadDocumentRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\InspectionRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Ocr\OcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use thiagoalessio\TesseractOCR\TesseractNotFoundException;

class DocumentController extends BaseApiController
{
    public function __construct(private readonly OcrService $ocrService) {}

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
        $path = $file->store('documents/'.$inspectionRequest->id, 'public');

        $document = Document::query()->create([
            'documentable_type' => InspectionRequest::class,
            'documentable_id' => $inspectionRequest->id,
            'uploaded_by' => $request->user()->id,
            'document_type' => $request->validated('document_type'),
            'file_path' => $path,
            'file_name' => $file->hashName(),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status' => 'pending',
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

    private function streamDocument(Request $request, Document $document): BinaryFileResponse
    {
        if (! Storage::disk('public')->exists($document->file_path)) {
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
            Storage::disk('public')->path($document->file_path),
            [
                'Content-Disposition' => 'inline; filename="'.basename($document->original_name ?? $document->file_path).'"',
            ]
        );
    }

    public function processOcr(Request $request, Document $document): JsonResponse
    {
        if ($document->extraction) {
            $document->load('extraction.reviewer.role', 'uploader.role');

            return $this->success(
                new DocumentResource($document),
                'Document already processed'
            );
        }

        try {
            $extraction = $this->ocrService->process($document);
        } catch (TesseractNotFoundException) {
            return $this->error(
                'Tesseract OCR is not installed or the configured binary path is incorrect.',
                500
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->error('OCR processing failed: '.$e->getMessage(), 500);
        }

        $document->update(['status' => 'processed']);

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

    public function verify(Request $request, Document $document): JsonResponse
    {
        $validated = $request->validate([
            'classification' => ['nullable', 'string', 'max:100'],
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

    private function canAccess(User $user, InspectionRequest $inspectionRequest): bool
    {
        if (in_array($user->role?->slug, ['administrator', 'barangay_staff', 'inspector'], true)) {
            return true;
        }

        return $inspectionRequest->resident_id === $user->id;
    }
}
