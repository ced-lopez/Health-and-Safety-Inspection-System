<?php

namespace App\Services\Ocr;

/**
 * Region-based OCR for complex ID layouts.
 *
 * Coordinates are never hard-coded per card design: word boxes come from a
 * Tesseract TSV pass, labels are located in the text, and the crop is derived
 * from the geometry of the detected label (to its right for inline values, or
 * in the band below it for header-style layouts). If reliable regions cannot
 * be detected the method returns an empty result and the pipeline falls back
 * to full-image multi-PSM OCR.
 */
class RegionOcr
{
    /** @var array<string, array<int, string>> */
    public const IDENTIFIER_LABELS = [
        'surname' => ['surname', 'last name', 'family name'],
        'given_name' => ['given name', 'first name'],
        'middle_name' => ['middle name'],
        'sex' => ['sex', 'gender'],
        'date_of_birth' => ['date of birth', 'birth date', 'birthday', 'dob'],
        'id_number' => ['id no', 'id number', 'identification no', 'identification number', 'crn', 'crn no'],
        'address' => ['address', 'present address', 'home address', 'residence address'],
        'full_name' => ['full name', 'complete name'],
    ];

    public function __construct(private readonly ImagePreprocessor $preprocessor) {}

    /**
     * @param  callable(string, array): array  $runner
     * @return array<string, array{value: string, confidence: float}>
     */
    public function extract(string $imagePath, callable $runner, array $options = []): array
    {
        $enabled = $options['enabled'] ?? config('ocr.region_ocr.enabled', true);

        if (! $enabled || ! function_exists('imagecrop')) {
            return [];
        }

        $labels = $options['labels'] ?? self::IDENTIFIER_LABELS;

        $full = $runner($imagePath, ['psm' => 11, 'tsv' => true]);
        $words = $full['words'] ?? [];

        if ($words === []) {
            return [];
        }

        $lines = $this->groupIntoLines($words);

        if ($lines === []) {
            return [];
        }

        $results = [];

        foreach ($labels as $field => $synonyms) {
            $found = $this->findLabel($lines, $synonyms);

            if ($found === null) {
                continue;
            }

            [$line, $startIndex, $endIndex] = $found;

            $crop = $this->cropFor($imagePath, $lines, $line, $startIndex, $endIndex, $options);

            if ($crop === null) {
                continue;
            }

            $ocr = $runner($crop['path'], ['psm' => (int) ($options['region_psm'] ?? config('ocr.region_ocr.region_psm', 7))]);

            @unlink($crop['path']);

            $value = trim((string) ($ocr['text'] ?? ''));

            if ($value === '' || mb_strlen($value) < 2) {
                continue;
            }

            $labelConfidence = $line['words'][$endIndex]['conf'] ?? null;
            $results[$field] = [
                'value' => $value,
                'confidence' => $this->confidence($value, $labelConfidence),
            ];
        }

        return $results;
    }

    /**
     * @param  array<int, array{text: string, left: int, top: int, width: int, height: int, conf: float}>  $words
     * @return array<int, array{top: int, height: int, words: array<int, array{text: string, left: int, top: int, width: int, height: int, conf: float}>}>
     */
    private function groupIntoLines(array $words): array
    {
        $sorted = $words;
        usort($sorted, fn (array $a, array $b) => $a['top'] <=> $b['top']);

        $lines = [];

        foreach ($sorted as $word) {
            $height = max(1, $word['height']);
            $tolerance = max(8, (int) round($height * 0.8));

            $key = null;
            foreach ($lines as $index => $line) {
                if (abs($line['top'] - $word['top']) <= max($tolerance, (int) round($line['height'] * 0.8))) {
                    $key = $index;
                    break;
                }
            }

            if ($key === null) {
                $lines[] = ['top' => $word['top'], 'height' => $height, 'words' => [$word]];

                continue;
            }

            $lines[$key]['words'][] = $word;
            $lines[$key]['height'] = max($lines[$key]['height'], $word['height']);
        }

        foreach ($lines as &$line) {
            usort($line['words'], fn (array $a, array $b) => $a['left'] <=> $b['left']);
        }
        unset($line);

        return $lines;
    }

    /**
     * @param  array<int, array{top: int, height: int, words: array<int, array{text: string, left: int, top: int, width: int, height: int, conf: float}>}>  $lines
     * @param  array<int, string>  $synonyms
     * @return array{0: array{top: int, height: int, words: array<int, array{text: string, left: int, top: int, width: int, height: int, conf: float}>}, 1: int, 2: int}|null
     */
    private function findLabel(array $lines, array $synonyms): ?array
    {
        foreach ($lines as $line) {
            $words = $line['words'];
            $count = count($words);

            foreach ($synonyms as $synonym) {
                $tokens = preg_split('/\s+/u', mb_strtolower(trim($synonym))) ?: [];
                $n = count($tokens);

                if ($n === 0 || $n > $count) {
                    continue;
                }

                // Match multi-word labels ("GIVEN NAME", "DATE OF BIRTH") as
                // contiguous word sequences so headers aren't misread.
                for ($start = 0; $start <= $count - $n; $start++) {
                    $window = array_slice($words, $start, $n);
                    $joined = mb_strtolower(implode(' ', array_map(
                        fn (array $w) => trim($w['text'], " \t.:-"),
                        $window
                    )));

                    if ($joined === mb_strtolower($synonym)) {
                        return [$line, $start, $start + $n - 1];
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{top: int, height: int, words: array<int, array{text: string, left: int, top: int, width: int, height: int, conf: float}>}>  $lines
     * @return array{path: string, label: string}|null
     */
    private function cropFor(string $imagePath, array $lines, array $labelLine, int $startIndex, int $endIndex, array $options): ?array
    {
        $padding = (int) ($options['crop_padding'] ?? config('ocr.region_ocr.crop_padding', 10));
        $lineWords = $labelLine['words'];
        $lastWord = $lineWords[$endIndex];
        $labelRight = $lastWord['left'] + $lastWord['width'];
        $labelBottom = $lastWord['top'] + $lastWord['height'];

        // Same-line value to the right of the label (end of the detected label).
        $rightWords = array_values(array_filter(
            $lineWords,
            fn (array $w) => $w['left'] > $labelRight
        ));

        if ($rightWords !== []) {
            $maxRight = max(array_map(fn (array $w) => $w['left'] + $w['width'], $rightWords));
            $top = $labelLine['top'] - max(2, intdiv($padding, 2));
            $height = $labelLine['height'] + $padding;

            $path = $this->crop($imagePath, $labelRight, $top, $maxRight - $labelRight, $height, $padding);

            return $path === null ? null : ['path' => $path, 'label' => 'inline'];
        }

        // Header-style label: value lives in the band below, up to the next line.
        $nextLine = null;

        foreach ($lines as $line) {
            if ($line['top'] > $labelLine['top'] + 2) {
                $nextLine = $line;
                break;
            }
        }

        $top = $labelBottom + max(2, intdiv($padding, 2));
        $bottom = $nextLine !== null ? $nextLine['top'] - max(2, intdiv($padding, 2)) : $labelBottom + 90;
        $height = max(10, $bottom - $top);

        $path = $this->crop($imagePath, 0, $top, 10000, $height, $padding);

        return $path === null ? null : ['path' => $path, 'label' => 'header'];
    }

    private function crop(string $imagePath, int $left, int $top, int $width, int $height, int $padding): ?string
    {
        return $this->preprocessor->cropRegion($imagePath, $left, $top, $width, $height, $padding);
    }

    private function confidence(string $value, ?float $labelWordConfidence): float
    {
        $base = 0.72;

        if ($labelWordConfidence !== null) {
            $base = min(0.9, 0.6 + ($labelWordConfidence / 100) * 0.3);
        }

        if (preg_match('/\b\d{1,2}[-\/\.]\d{1,2}[-\/\.]\d{2,4}\b|\b\d{4}[-\/\.]\d{1,2}[-\/\.]\d{1,2}\b/i', $value)) {
            $base += 0.06;
        }

        if (mb_strlen($value) > 4 && preg_match('/^[a-z][a-z0-9\s\.\-\'\/]+$/i', trim($value))) {
            $base += 0.04;
        }

        return (float) round(max(0.0, min(1.0, $base)), 4);
    }
}
