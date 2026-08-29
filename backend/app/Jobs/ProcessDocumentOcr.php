<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\Ocr\OcrService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessDocumentOcr implements ShouldQueue
{
    use Queueable;

    /**
     * The unique ID of the document to run OCR against.
     */
    public function __construct(
        public readonly int $documentId,
    ) {}

    /**
     * Runs the OCR pipeline for the uploaded document. The pipeline itself
     * persists the extraction; failures are recorded on the document so the
     * resident or staff can retry from the manual `process-ocr` endpoint.
     */
    public function handle(OcrService $ocr): void
    {
        $document = Document::withTrashed()->find($this->documentId);

        if (! $document) {
            return;
        }

        if ($document->extraction) {
            return;
        }

        try {
            $ocr->process($document);
        } catch (Throwable) {
            // Pipeline already marks the document as failed; nothing to rethrow.
        }
    }
}
