<?php

namespace App\Console\Commands;

use App\Services\Ocr\DocumentClassifier;
use App\Services\Ocr\DocumentFieldExtractor;
use App\Services\Ocr\DocumentTypes;
use App\Services\Ocr\ImagePreprocessor;
use App\Services\Ocr\OcrEvaluator;
use App\Services\Ocr\OcrRunSelector;
use App\Services\Ocr\TesseractRunner;
use Illuminate\Console\Command;
use JsonException;
use Throwable;

/**
 * Offline accuracy evaluation: runs the OCR pipeline over a folder of sample
 * documents and compares the results against known ground-truth values.
 *
 * Manifest format (manifest.json next to the images, or configured via
 * OCR_EVALUATION_MANIFEST):
 *
 *   {
 *     "government_id_complex.png": {
 *       "document_type": "government_id",
 *       "fields": { "surname": "SANTOS", "given_name": "JOSE",
 *                   "middle_name": "CRUZ", "date_of_birth": "1980-01-16" }
 *     }
 *   }
 *
 * Metrics: Character Error Rate, character accuracy, field extraction accuracy,
 * document classification accuracy, and average processing time, broken down
 * by document type. Successful OCR never implies document authenticity.
 */
class OcrEvaluateCommand extends Command
{
    protected $signature = 'ocr:evaluate {--images=} {--manifest=} {--report=}';

    protected $description = 'Compare OCR output against ground-truth values and report accuracy metrics';

    public function handle(
        DocumentClassifier $classifier,
        DocumentFieldExtractor $extractor,
        ImagePreprocessor $preprocessor,
        OcrRunSelector $selector,
        TesseractRunner $runner,
    ): int {
        $imagesDir = $this->option('images') ?: config('ocr.evaluation.images_dir');
        $manifestPath = $this->option('manifest') ?: config('ocr.evaluation.manifest');
        $reportPath = $this->option('report') ?: config('ocr.evaluation.result');

        if (! is_file($manifestPath)) {
            $this->error("Ground-truth manifest not found: {$manifestPath}");

            return self::FAILURE;
        }

        try {
            $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error('Manifest is not valid JSON: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! is_dir($imagesDir)) {
            $this->error("Images directory not found: {$imagesDir}");

            return self::FAILURE;
        }

        $results = [];

        foreach ($manifest as $file => $expected) {
            $path = rtrim($imagesDir, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$file;

            if (! is_file($path)) {
                $this->warn("Skipping missing image: {$file}");

                continue;
            }

            $this->info("Evaluating: {$file}");

            $results[] = $this->evaluate($path, $expected, $classifier, $extractor, $preprocessor, $selector, $runner);
        }

        if ($results === []) {
            $this->warn('No documents evaluated.');

            return self::SUCCESS;
        }

        $aggregate = OcrEvaluator::aggregate($results);

        $this->table(
            ['Document', 'Type', 'CER', 'Char Acc', 'Field Acc', 'Class Match', 'Time (ms)'],
            array_map(function (array $r) {
                $cer = $r['cer'];

                return [
                    $r['file'],
                    $r['type'],
                    $cer === null ? 'n/a' : number_format($cer, 4),
                    $cer === null ? 'n/a' : number_format(1 - $cer, 4),
                    $r['field_accuracy']['percentage'].'%',
                    $r['classification_matched'] ? 'yes' : 'no',
                    number_format($r['processing_time_ms'], 1),
                ];
            }, $results)
        );

        $this->table(
            ['Metric', 'Value'],
            [
                ['Documents', $aggregate['documents']],
                ['Average CER', $aggregate['avg_cer'] === null ? 'n/a' : number_format($aggregate['avg_cer'], 4)],
                ['Average character accuracy', $aggregate['avg_character_accuracy'] === null ? 'n/a' : number_format($aggregate['avg_character_accuracy'], 4)],
                ['Field extraction accuracy', $aggregate['field_accuracy']['percentage'].'%'],
                ['Document classification accuracy', $aggregate['classification_accuracy'].'%'],
                ['Average processing time', $aggregate['avg_processing_time_ms'].' ms'],
            ]
        );

        if (! empty($aggregate['by_type'])) {
            $this->line('By document type:');
            $this->table(
                ['Type', 'Documents', 'Avg CER', 'Field Acc'],
                array_map(function (string $type, array $data) {
                    return [
                        $type,
                        $data['documents'],
                        ($data['avg_cer'] ?? null) === null ? 'n/a' : number_format($data['avg_cer'], 4),
                        $data['field_accuracy']['percentage'].'%',
                    ];
                }, array_keys($aggregate['by_type']), array_values($aggregate['by_type']))
            );
        }

        $this->saveReport($reportPath, $aggregate, $results);

        $this->info("Report written to: {$reportPath}");

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    private function evaluate(
        string $path,
        array $expected,
        DocumentClassifier $classifier,
        DocumentFieldExtractor $extractor,
        ImagePreprocessor $preprocessor,
        OcrRunSelector $selector,
        TesseractRunner $runner,
    ): array {
        $expectedType = (string) ($expected['document_type'] ?? $expected['classification'] ?? 'unknown');
        $expectedFields = $expected['fields'] ?? [];
        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: '') : '';

        $started = hrtime(true);
        $text = '';
        $ocrError = null;

        try {
            if (DocumentTypes::isIdType($expectedType)) {
                $versions = $preprocessor->prepare($path, $mime);
                $selected = $selector->select(
                    $versions,
                    $expectedType,
                    fn (string $image, array $options) => $runner->run($image, $options)
                );
                $text = $selected['text'];

                foreach (array_column($versions, 'path') as $temp) {
                    @unlink($temp);
                }
            } else {
                $text = $runner->run($path, ['psm' => config('ocr.default_psm', 3)])['text'];
            }
        } catch (Throwable $e) {
            $ocrError = $e->getMessage();
        }

        $processingTime = (hrtime(true) - $started) / 1e6;

        $classified = $text !== ''
            ? $classifier->classify($text)['type']
            : DocumentTypes::UNKNOWN;

        $actualFields = $text !== ''
            ? $extractor->extract($text, $classified)
            : [];

        $rawText = $expected['raw_text'] ?? null;

        return [
            'file' => basename($path),
            'type' => $expectedType,
            'cer' => $ocrError === null && $rawText !== null
                ? OcrEvaluator::cer((string) $rawText, $text)
                : null,
            'field_accuracy' => OcrEvaluator::fieldAccuracy($expectedFields, $actualFields),
            'classification_matched' => $expectedType === $classified,
            'classification' => $classified,
            'ocr_text' => $text,
            'ocr_error' => $ocrError,
            'processing_time_ms' => (float) round($processingTime, 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $aggregate
     * @param  array<int, array<string, mixed>>  $results
     */
    private function saveReport(string $path, array $aggregate, array $results): void
    {
        @mkdir(dirname($path), 0777, true);

        $report = [
            'generated_at' => now()->toIso8601String(),
            'aggregate' => $aggregate,
            'documents' => $results,
        ];

        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
