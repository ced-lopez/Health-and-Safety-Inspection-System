<?php

namespace App\Services\Ocr;

use App\Models\Document;
use App\Models\DocumentExtraction;
use App\Models\DocumentRequirementRule;
use App\Models\InspectionRequest;
use Illuminate\Support\Facades\Storage;
use thiagoalessio\TesseractOCR\TesseractOCR;

class OcrService
{
    public function __construct(
        private readonly DocumentClassifier $classifier,
        private readonly DocumentFieldExtractor $fieldExtractor,
    ) {}

    public function process(Document $document): DocumentExtraction
    {
        $text = '';
        $enhancedPath = null;
        $warning = null;

        if ($this->isPdf($document)) {
            $warning = 'PDF documents cannot be OCR-processed. Upload an image (JPG/PNG) to enable OCR.';
        } else {
            $sourcePath = Storage::disk('public')->path($document->file_path);
            $enhancedPath = $this->enhanceImage($sourcePath, $document->mime_type);
            $text = $this->runTesseract($enhancedPath ?? $sourcePath);
        }

        $classification = $text !== ''
            ? $this->classifier->classify($text)
            : ['type' => null, 'label' => 'Unclassified Document', 'confidence' => 0];

        $effectiveType = $document->document_type ?: $classification['type'];

        $fields = $text !== ''
            ? $this->fieldExtractor->extract($text, $classification['type'])
            : [];

        $expiration = $this->fieldExtractor->detectExpiration($fields['expiration_date'] ?? null);

        $extractedData = array_merge([
            'ocr_text' => $text,
            'classification_label' => $classification['label'],
            'expiration_found' => $expiration['expiration_found'],
            'warning' => $warning,
        ], $fields, $expiration);

        $missing = $this->detectMissingRequirements($document, $effectiveType);

        $extraction = DocumentExtraction::query()->create([
            'document_id' => $document->id,
            'classification' => $classification['type'],
            'extracted_data' => $extractedData,
            'business_name' => $fields['business_name'] ?? null,
            'owner_name' => $fields['owner_name'] ?? null,
            'permit_number' => $fields['permit_number'] ?? null,
            'issuing_authority' => $fields['issuing_authority'] ?? null,
            'date_issued' => $fields['date_issued'] ?? null,
            'expiration_date' => $fields['expiration_date'] ?? null,
            'establishment_name' => $fields['business_name'] ?? null,
            'issuing_office' => $fields['issuing_office'] ?? null,
            'issue_date' => $fields['date_issued'] ?? null,
            'is_expired' => $expiration['is_expired'],
            'missing_requirements' => $missing,
            'confidence_score' => $classification['confidence'],
            'ai_processed_at' => now(),
        ]);

        if (empty($document->document_type) && $classification['type']) {
            $document->update(['document_type' => $classification['type']]);
        }

        if ($enhancedPath) {
            @unlink($enhancedPath);
        }

        return $extraction;
    }

    protected function runTesseract(string $imagePath): string
    {
        $ocr = (new TesseractOCR($imagePath))
            ->executable(config('ocr.tesseract_binary'))
            ->lang(config('ocr.lang'));

        return trim($ocr->run());
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
        $mime = $mimeType ?: (mime_content_type($path) ?: '');

        return match (strtolower($mime)) {
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
}
