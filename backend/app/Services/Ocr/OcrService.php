<?php

namespace App\Services\Ocr;

use App\Models\Document;
use App\Models\DocumentExtraction;
use App\Models\DocumentExtractionVersion;
use App\Models\DocumentRequirementRule;
use App\Models\InspectionRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Throwable;

class OcrService
{
    /**
     * Characters whose OCR readings are frequently ambiguous (B/8, O/0, I/1,
     * S/5, D/0, Z/2, G/6, L/1). ID numbers containing these alongside digits
     * are flagged for manual review instead of being silently "corrected".
     */
    private const AMBIGUOUS_LETTERS = ['B', 'O', 'I', 'S', 'D', 'Z', 'G', 'L', 'l'];

    private const AMBIGUOUS_PAIRS = [
        'B' => 'B / 8',
        'O' => 'O / 0',
        'I' => 'I / 1',
        'S' => 'S / 5',
        'D' => 'D / 0',
        'Z' => 'Z / 2',
        'G' => 'G / 6',
        'L' => 'L / 1',
        'l' => 'l / 1',
    ];

    public function __construct(
        private readonly DocumentClassifier $classifier,
        private readonly DocumentFieldExtractor $fieldExtractor,
        private readonly DocumentValidator $validator,
    ) {}

    /**
     * Runs the OCR pipeline for a document:
     * validate file → detect type → render PDF pages (Ghostscript)
     * → preprocess image → OCR (multi-PSM/region-based for government IDs)
     * → cleanup → classify → extract fields → validate → score confidence
     * → flag low-confidence → save results.
     *
     * The originally uploaded file is never modified; all processed versions
     * are written to temporary files and cleaned up afterwards.
     */
    public function process(Document $document, bool $force = false): DocumentExtraction
    {
        if ($force && $document->extraction) {
            $this->archiveExtraction($document, $document->extraction);
            $document->extraction->delete();
            $document->unsetRelation('extraction');
        }

        if ($document->extraction) {
            return $document->extraction;
        }

        $started = hrtime(true);

        $extraction = DocumentExtraction::query()->create([
            'document_id' => $document->id,
            'ocr_status' => 'processing',
        ]);

        $text = '';
        $enhancedPath = null;
        $warning = null;
        $specialized = null;
        $temporaryFiles = [];
        $regionResults = [];
        $idAmbiguity = [];

        try {
            $imagePath = null;

            if ($this->isPdf($document)) {
                $sourcePath = Storage::disk($this->storageDisk())->path($document->file_path);
                $convertedPath = $this->convertPdfToImage($sourcePath);

                if ($convertedPath === null) {
                    $warning = 'PDF documents could not be OCR-processed. Ensure Ghostscript is installed (GHOSTSCRIPT_BINARY), or upload an image (JPG/PNG) to enable OCR.';
                } else {
                    $temporaryFiles[] = $convertedPath;
                    $imagePath = $convertedPath;
                }
            } else {
                $imagePath = Storage::disk($this->storageDisk())->path($document->file_path);
            }

            $declaredType = DocumentTypes::canonical((string) ($document->document_type ?? ''));

            $specialized = $imagePath !== null
                && DocumentTypes::isIdType((string) $declaredType)
                && in_array($declaredType, config('ocr.specialized_types', ['government_id', 'barangay_id']), true);

            if ($specialized) {
                $run = $this->runSpecialized($imagePath, $document->mime_type, (string) $declaredType);
                $text = $run['text'];
                $regionResults = $run['region_results'] ?? [];
                $temporaryFiles = array_merge($temporaryFiles, $run['temporary_files'] ?? []);
            } elseif ($imagePath !== null) {
                // General-purpose documents keep the standard single pass.
                $enhancedPath = $this->enhanceImage($imagePath, $document->mime_type);
                $temporaryFiles[] = $enhancedPath;
                $text = $this->runTesseract($enhancedPath ?? $imagePath);
            }

            $classification = $text !== ''
                ? $this->classifier->classify($text)
                : ['type' => DocumentTypes::UNKNOWN, 'label' => DocumentTypes::label(DocumentTypes::UNKNOWN), 'confidence' => 0.0];

            $fields = $text !== ''
                ? $this->fieldExtractor->extract($text, $classification['type'])
                : [];

            if ($regionResults !== []) {
                $fields = $this->mergeRegionResults($fields, $regionResults, $classification['type']);
            }

            if (DocumentTypes::isIdType($classification['type'])) {
                [$fields, $idAmbiguity] = $this->flagAmbiguousIdFields($fields, $classification['type']);
            }

            $validation = $this->validator->validate($document, $classification['type'], $fields);

            if ($idAmbiguity !== []) {
                $validation['warnings'][] = [
                    'scope' => 'id_number',
                    'code' => 'ambiguous_id_characters',
                    'message' => 'The extracted ID number contains characters that OCR may have misread (e.g. B/8, O/0, I/1, S/5). Verify against the original document before accepting.',
                    'fields' => array_column($idAmbiguity, 'field'),
                ];
                $validation['is_valid'] = false;
            }

            $lowConfidence = $this->fieldExtractor->lowConfidenceFields($fields);

            $expiration = $this->fieldExtractor->detectExpiration($this->fieldValue($fields, 'expiration_date'));

            $legacyType = DocumentTypes::legacyType($classification['type']);
            $effectiveType = $legacyType ?: $document->document_type;

            $missing = $this->detectMissingRequirements($document, $effectiveType);

            $needsReview = $this->needsReview($classification, $lowConfidence, $fields);
            $ocrStatus = $needsReview ? 'needs_review' : 'completed';

            $extractedData = [
                'ocr_text' => $text,
                'classification' => $classification['type'],
                'classification_label' => $classification['label'],
                'classification_confidence' => $classification['confidence'],
                'document_type' => $effectiveType,
                'fields' => $fields,
                'low_confidence_fields' => $lowConfidence,
                'validation' => $validation,
                'expiration_found' => $expiration['expiration_found'],
                'is_expired' => $expiration['is_expired'],
                'expiration_warning' => $expiration['expiration_warning'],
                'warning' => $warning,
                'region_results' => $regionResults !== [] ? $regionResults : null,
                'id_ambiguity' => $idAmbiguity !== [] ? $idAmbiguity : null,
            ];

            $extraction->update([
                'classification' => $classification['type'],
                'classification_confidence' => $classification['confidence'],
                'extracted_data' => $extractedData,
                'ocr_text' => $text,
                'ocr_raw_text' => $text,
                'ocr_status' => $ocrStatus,
                'processing_time_ms' => (int) round((hrtime(true) - $started) / 1e6),
                'ocr_engine' => $specialized ? ($run['engine'] ?? 'tesseract') : 'tesseract',
                'ocr_language' => config('ocr.lang'),
                'ocr_passes' => $specialized ? (int) ($run['passes'] ?? 1) : 1,
                'ocr_versions' => $specialized ? ($run['versions'] ?? null) : null,
                'low_confidence_fields' => $lowConfidence,
                'business_name' => $this->fieldValue($fields, 'business_name'),
                'owner_name' => $this->fieldValue($fields, 'owner_name'),
                'permit_number' => $this->fieldValue($fields, 'permit_number'),
                'issuing_authority' => $this->fieldValue($fields, 'issuing_authority'),
                'issuing_office' => $this->fieldValue($fields, 'issuing_office'),
                'date_issued' => $this->fieldValue($fields, 'date_issued'),
                'expiration_date' => $this->fieldValue($fields, 'expiration_date'),
                'issue_date' => $this->fieldValue($fields, 'issue_date'),
                'establishment_name' => $this->fieldValue($fields, 'establishment_name') ?? $this->fieldValue($fields, 'business_name'),
                'certificate_number' => $this->fieldValue($fields, 'certificate_number'),
                'is_expired' => $expiration['is_expired'],
                'missing_requirements' => $missing,
                'confidence_score' => round($classification['confidence'] * 100, 2),
                'ai_processed_at' => now(),
            ]);

            if (empty($document->document_type) && $legacyType) {
                $document->update(['document_type' => $legacyType]);
            }

            if ($needsReview) {
                $document->update(['status' => 'needs_review']);
            } elseif ($document->status === 'pending' || $document->status === 'processing' || $document->status === 'failed') {
                $document->update(['status' => 'processed']);
            }
        } catch (Throwable $e) {
            report($e);
            $extraction->update([
                'ocr_status' => 'failed',
                'ocr_error' => $e->getMessage(),
                'processing_time_ms' => (int) round((hrtime(true) - $started) / 1e6),
            ]);
            $document->update(['status' => 'failed']);

            throw $e;
        } finally {
            foreach ($temporaryFiles as $file) {
                if ($file && is_file($file)) {
                    @unlink($file);
                }
            }
        }

        return $extraction->fresh();
    }

    /**
     * Snapshots the current extraction as an immutable OCR attempt so the
     * previous result is never lost when a document is reprocessed.
     */
    private function archiveExtraction(Document $document, DocumentExtraction $extraction): void
    {
        DocumentExtractionVersion::query()->create([
            'document_id' => $document->id,
            'attempt' => $this->nextAttemptNumber($document),
            'ocr_status' => $extraction->ocr_status,
            'classification' => $extraction->classification,
            'classification_confidence' => $extraction->classification_confidence,
            'extracted_data' => $extraction->extracted_data,
            'ocr_text' => $extraction->ocr_text,
            'ocr_raw_text' => $extraction->ocr_raw_text,
            'processing_time_ms' => $extraction->processing_time_ms,
            'ocr_engine' => $extraction->ocr_engine,
            'ocr_language' => $extraction->ocr_language,
            'ocr_passes' => $extraction->ocr_passes,
            'confidence_score' => $extraction->confidence_score,
            'ocr_error' => $extraction->ocr_error,
            'processed_at' => $extraction->ai_processed_at ?? $extraction->updated_at ?? now(),
        ]);
    }

    private function nextAttemptNumber(Document $document): int
    {
        return (int) DocumentExtractionVersion::query()
            ->where('document_id', $document->id)
            ->max('attempt') + 1;
    }

    /**
     * Single Tesseract pass. Overridden by tests to feed synthetic OCR output.
     */
    protected function runTesseract(string $imagePath): string
    {
        $ocr = (new TesseractOCR($imagePath))
            ->executable(config('ocr.tesseract_binary'))
            ->lang(config('ocr.lang'));

        return trim($ocr->run());
    }

    /**
     * Tesseract pass with engine options (PSM/OEM). Defaults to the plain
     * runTesseract hook so existing fakes keep working; returns word data only
     * when the engine produced TSV output.
     *
     * @param  array<string, mixed>  $options
     * @return array{text: string, words: array<int, mixed>, conf: ?float, psm: int}
     */
    protected function runTesseractWithOptions(string $imagePath, array $options = []): array
    {
        $psm = (int) ($options['psm'] ?? config('ocr.default_psm', 3));

        if ($options['tsv'] ?? false) {
            $result = (new TesseractRunner)->run($imagePath, array_merge($options, ['tsv' => true]));

            return [
                'text' => $result['text'],
                'words' => $result['words'],
                'conf' => $result['conf'],
                'psm' => $result['psm'],
            ];
        }

        return [
            'text' => $this->runTesseract($imagePath),
            'words' => [],
            'conf' => null,
            'psm' => $psm,
        ];
    }

    /**
     * Specialized government-ID pipeline: preprocessing → multi-PSM → region OCR.
     *
     * @return array{text: string, engine: string, passes: int, versions: array<int, array<string, mixed>>, region_results: array<string, array{value: string, confidence: float}>, temporary_files: array<int, string>}
     */
    protected function runSpecialized(string $imagePath, ?string $mimeType, string $classification): array
    {
        $versions = $this->preprocessImage($imagePath, $mimeType);

        $selector = new OcrRunSelector($this->classifier, $this->fieldExtractor);

        $selected = $selector->select(
            $versions,
            $classification,
            fn (string $path, array $options) => $this->runTesseractWithOptions($path, $options)
        );

        $regionResults = [];

        if ($selected['text'] !== '' && config('ocr.region_ocr.enabled', true)) {
            try {
                $region = new RegionOcr(new ImagePreprocessor);
                $regionResults = $region->extract(
                    $versions[0]['path'] ?? $imagePath,
                    fn (string $path, array $options) => $this->runTesseractWithOptions($path, $options)
                );
            } catch (Throwable) {
                // Region OCR is best-effort; fall back to the full-image result.
                $regionResults = [];
            }
        }

        return [
            'text' => $selected['text'],
            'engine' => 'tesseract',
            'passes' => $selected['passes'],
            'psm' => $selected['psm'],
            'kind' => $selected['kind'],
            'versions' => array_map(
                fn (array $version) => ['kind' => $version['kind'], 'width' => $version['width'], 'height' => $version['height']],
                $versions
            ),
            'region_results' => $regionResults,
            'temporary_files' => array_column($versions, 'path'),
        ];
    }

    /**
     * Runs the GD preprocessing pipeline and returns OCR-friendly versions.
     *
     * @return array<int, array{path: string, kind: string, width: int, height: int}>
     */
    protected function preprocessImage(string $imagePath, ?string $mimeType): array
    {
        return (new ImagePreprocessor)->prepare($imagePath, $mimeType);
    }

    /**
     * Merges region-based OCR results into the extracted fields. A region value
     * only overrides the full-image value when the full-image read is missing
     * or has lower confidence; it never invents data.
     */
    private function mergeRegionResults(array $fields, array $regionResults, string $classification): array
    {
        $dateFields = ['date_of_birth', 'date_issued', 'expiration_date'];

        foreach ($regionResults as $field => $region) {
            if (! isset($fields[$field])) {
                continue;
            }

            $value = $region['value'];
            $confidence = (float) $region['confidence'];

            if (in_array($field, $dateFields, true)) {
                $normalized = $this->normalizeDate($value);
                if ($normalized === null) {
                    continue;
                }
                $value = $normalized;
            }

            $current = $fields[$field];

            if (($current['value'] ?? null) !== null && (float) $current['confidence'] >= $confidence) {
                continue;
            }

            $fields[$field] = ['value' => $value, 'confidence' => $confidence];
        }

        return $fields;
    }

    /**
     * Flags ID numbers whose detected characters are OCR-ambiguous by lowering
     * their confidence so the document is sent for manual review. The original
     * detected text is preserved — nothing is auto-corrected.
     *
     * @return array{0: array<string, array{value: mixed, confidence: float}>, 1: array<int, array{field: string, value: string, ambiguous: array<int, string>}>}
     */
    private function flagAmbiguousIdFields(array $fields, string $classification): array
    {
        $targets = $classification === DocumentTypes::GOVERNMENT_ID
            ? ['id_number', 'document_number']
            : ['id_number', 'member_number', 'document_number'];

        $ambiguity = [];

        foreach ($targets as $field) {
            $value = (string) ($fields[$field]['value'] ?? '');

            if ($value === '') {
                continue;
            }

            $pairs = $this->ambiguousPairs($value);

            if ($pairs === []) {
                continue;
            }

            $current = (float) ($fields[$field]['confidence'] ?? 1.0);
            $fields[$field]['confidence'] = round(min($current, 0.68), 4);

            $ambiguity[] = [
                'field' => $field,
                'value' => $value,
                'ambiguous' => $pairs,
            ];
        }

        return [$fields, $ambiguity];
    }

    /**
     * @return array<int, string>
     */
    private function ambiguousPairs(string $value): array
    {
        $hasDigit = (bool) preg_match('/\d/', $value);
        $pairs = [];

        foreach (str_split($value) as $char) {
            if (! $hasDigit) {
                break;
            }

            if (isset(self::AMBIGUOUS_PAIRS[$char]) && ! in_array(self::AMBIGUOUS_PAIRS[$char], $pairs, true)) {
                $pairs[] = self::AMBIGUOUS_PAIRS[$char];
            }
        }

        return $pairs;
    }

    private function normalizeDate(string $value): ?string
    {
        try {
            return Carbon::parse(str_replace(['.', '/'], '-', $value))->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    private function convertPdfToImage(string $pdfPath): ?string
    {
        $binary = config('ocr.ghostscript_binary');
        $dpi = config('ocr.pdf_dpi');
        $outputPath = tempnam(sys_get_temp_dir(), 'ocr_pdf_').'.png';

        $command = sprintf(
            '%s -dNOPAUSE -dBATCH -sDEVICE=png16m -r%d -dFirstPage=1 -dLastPage=1 -sOutputFile=%s %s',
            escapeshellarg($binary),
            (int) $dpi,
            escapeshellarg($outputPath),
            escapeshellarg($pdfPath)
        );

        $output = [];
        $exitCode = 0;
        exec($command.' 2>&1', $output, $exitCode);

        if ($exitCode !== 0 || ! is_file($outputPath) || filesize($outputPath) === 0) {
            @unlink($outputPath);

            return null;
        }

        return $outputPath;
    }

    private function enhanceImage(string $sourcePath, ?string $mimeType): ?string
    {
        if (! config('ocr.enhancement_enabled') || ! extension_loaded('gd')) {
            return null;
        }

        $image = $this->loadImage($sourcePath, $mimeType);

        if ($image === null) {
            return null;
        }

        imagefilter($image, IMG_FILTER_GRAYSCALE);
        imagefilter($image, IMG_FILTER_CONTRAST, -35);
        imagefilter($image, IMG_FILTER_BRIGHTNESS, 12);

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > 0 && $width < 800) {
            $scale = min(2, 1200 / $width);
            $newWidth = (int) round($width * $scale);
            $newHeight = (int) round($height * $scale);
            $resized = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            $image = $resized;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'ocr_enhanced_').'.png';
        imagepng($image, $tempPath);

        return $tempPath;
    }

    private function loadImage(string $path, ?string $mimeType): ?\GdImage
    {
        $mime = $mimeType ?: (function_exists('mime_content_type') ? (mime_content_type($path) ?: '') : '');

        return match (strtolower((string) $mime)) {
            'image/jpeg', 'image/jpg', 'image/pjpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            default => @imagecreatefromstring((string) file_get_contents($path)),
        } ?: null;
    }

    private function isPdf(Document $document): bool
    {
        return strtolower((string) $document->mime_type) === 'application/pdf'
            || strtolower((string) pathinfo($document->file_name, PATHINFO_EXTENSION)) === 'pdf';
    }

    private function detectMissingRequirements(Document $document, ?string $effectiveType): array
    {
        $request = $document->documentable instanceof InspectionRequest
            ? $document->documentable
            : null;

        if (! $request) {
            return [];
        }

        $rules = DocumentRequirementRule::query()
            ->where('inspection_category_id', $request->inspection_category_id)
            ->where('application_type_id', $request->application_type_id)
            ->where(function ($query) use ($request) {
                $query->whereNull('sub_path')
                    ->orWhere('sub_path', $request->sub_path);
            })
            ->where('is_required', true)
            ->get();

        if ($rules->isEmpty()) {
            return [];
        }

        $presentTypes = $request->documents()
            ->get()
            ->map(fn (Document $doc) => $doc->document_type)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if ($effectiveType) {
            $presentTypes[] = $effectiveType;
        }

        $missing = [];

        foreach ($rules as $rule) {
            if (! in_array($rule->document_type, $presentTypes, true)) {
                $missing[] = [
                    'document_type' => $rule->document_type,
                    'document_name' => $rule->document_name,
                    'requires_expiration_check' => $rule->requires_expiration_check,
                ];
            }
        }

        return $missing;
    }

    private function fieldValue(array $fields, string $field): ?string
    {
        $value = $fields[$field]['value'] ?? null;

        return $value === null ? null : (string) $value;
    }

    private function needsReview(array $classification, array $lowConfidence, array $fields): bool
    {
        if ($classification['type'] === DocumentTypes::UNKNOWN) {
            return true;
        }

        if ($lowConfidence !== []) {
            return true;
        }

        // Document classified but not a single field was read.
        if (count($fields) > 0) {
            $allEmpty = true;
            foreach ($fields as $field) {
                if (($field['value'] ?? null) !== null) {
                    $allEmpty = false;
                    break;
                }
            }

            if ($allEmpty) {
                return true;
            }
        }

        return false;
    }

    private function storageDisk(): string
    {
        return config('ocr.storage_disk', 'public');
    }
}
