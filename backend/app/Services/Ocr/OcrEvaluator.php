<?php

namespace App\Services\Ocr;

/**
 * Offline accuracy metrics for the OCR pipeline.
 *
 * Compares OCR output against known ground-truth values without claiming that
 * OCR success implies document authenticity. All methods are pure so they can
 * be unit-tested and reused by the `ocr:evaluate` artisan command.
 */
class OcrEvaluator
{
    /**
     * Character Error Rate (0..1). Lower is better.
     */
    public static function cer(string $groundTruth, string $ocr): float
    {
        $gt = self::normalize($groundTruth);
        $out = self::normalize($ocr);

        if ($gt === '') {
            return $out === '' ? 0.0 : 1.0;
        }

        if ($gt === $out) {
            return 0.0;
        }

        $distance = levenshtein($gt, $out);

        return (float) round(min(1.0, $distance / mb_strlen($gt)), 4);
    }

    /**
     * Character accuracy (1 - CER).
     */
    public static function characterAccuracy(string $groundTruth, string $ocr): float
    {
        return (float) round(max(0.0, 1.0 - self::cer($groundTruth, $ocr)), 4);
    }

    /**
     * Field extraction accuracy against expected fields.
     *
     * @param  array<string, mixed>  $expected  field => expected value
     * @param  array<string, mixed>  $actual  field => extracted value
     * @return array{correct: int, total: int, percentage: float, fields: array<string, bool>}
     */
    public static function fieldAccuracy(array $expected, array $actual): array
    {
        $correct = 0;
        $total = 0;
        $fields = [];

        foreach ($expected as $field => $expectedValue) {
            if ($expectedValue === null || $expectedValue === '') {
                continue;
            }

            $total++;
            $actualValue = $actual[$field]['value'] ?? ($actual[$field] ?? null);

            $match = self::valuesMatch((string) $expectedValue, (string) $actualValue);
            $fields[$field] = $match;

            if ($match) {
                $correct++;
            }
        }

        return [
            'correct' => $correct,
            'total' => $total,
            'percentage' => $total > 0 ? (float) round($correct / $total * 100, 2) : 100.0,
            'fields' => $fields,
        ];
    }

    /**
     * Document classification accuracy.
     */
    public static function classificationAccuracy(string $expectedType, string $actualType): bool
    {
        return $expectedType === $actualType;
    }

    /**
     * Aggregates a series of per-document metric results.
     *
     * @param  array<int, array<string, mixed>>  $results
     * @return array<string, mixed>
     */
    public static function aggregate(array $results): array
    {
        $count = count($results);

        if ($count === 0) {
            return [
                'documents' => 0,
                'avg_cer' => 0.0,
                'avg_character_accuracy' => 0.0,
                'field_accuracy' => ['correct' => 0, 'total' => 0, 'percentage' => 0.0],
                'classification_accuracy' => 0.0,
                'avg_processing_time_ms' => 0.0,
                'by_type' => [],
            ];
        }

        $totalCer = 0.0;
        $cerDocs = 0;
        $correctFields = 0;
        $totalFields = 0;
        $correctClassifications = 0;
        $totalTimes = 0.0;
        $byType = [];

        foreach ($results as $result) {
            if (($result['cer'] ?? null) !== null) {
                $totalCer += $result['cer'];
                $cerDocs++;
            }

            $correctFields += $result['field_accuracy']['correct'] ?? 0;
            $totalFields += $result['field_accuracy']['total'] ?? 0;

            if ($result['classification_matched'] ?? false) {
                $correctClassifications++;
            }

            $totalTimes += $result['processing_time_ms'] ?? 0;

            $type = $result['type'] ?? 'unknown';
            $byType[$type][] = $result;
        }

        foreach ($byType as $type => $group) {
            $typeCers = array_column($group, 'cer');
            $typeCers = array_values(array_filter($typeCers, fn ($v) => $v !== null));
            $typeCer = $typeCers === [] ? null : array_sum($typeCers) / count($typeCers);
            $typeCorrect = array_sum(array_column($group, 'field_accuracy.correct')) ?: 0;
            $typeTotal = array_sum(array_column($group, 'field_accuracy.total')) ?: 0;

            $byType[$type] = [
                'documents' => count($group),
                'avg_cer' => $typeCer === null ? null : (float) round($typeCer, 4),
                'field_accuracy' => [
                    'percentage' => $typeTotal > 0 ? (float) round($typeCorrect / $typeTotal * 100, 2) : 100.0,
                ],
            ];
        }

        return [
            'documents' => $count,
            'avg_cer' => $cerDocs > 0 ? (float) round($totalCer / $cerDocs, 4) : null,
            'avg_character_accuracy' => $cerDocs > 0 ? (float) round(1.0 - $totalCer / $cerDocs, 4) : null,
            'field_accuracy' => [
                'correct' => $correctFields,
                'total' => $totalFields,
                'percentage' => $totalFields > 0 ? (float) round($correctFields / $totalFields * 100, 2) : 100.0,
            ],
            'classification_accuracy' => (float) round($correctClassifications / $count * 100, 2),
            'avg_processing_time_ms' => (float) round($totalTimes / $count, 2),
            'by_type' => $byType,
        ];
    }

    private static function valuesMatch(string $expected, string $actual): bool
    {
        if ($actual === '') {
            return false;
        }

        return self::normalize($expected) === self::normalize($actual);
    }

    public static function normalize(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^\p{L}\p{N}\s\-\']/u', '', $normalized) ?? $normalized;

        return trim($normalized);
    }
}
