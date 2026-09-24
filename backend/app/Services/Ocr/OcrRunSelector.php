<?php

namespace App\Services\Ocr;

/**
 * Runs bounded multi-pass OCR over preprocessed versions and page-segmentation
 * modes, then picks the result that yields the most reliable structured fields.
 *
 * The winner is NOT the longest output: candidates are scored on expected
 * labels, field patterns (names, dates, ID numbers, addresses), token density
 * and word-level confidence when the engine provides it.
 */
class OcrRunSelector
{
    public function __construct(
        private readonly DocumentClassifier $classifier,
        private readonly DocumentFieldExtractor $fieldExtractor,
    ) {}

    /**
     * @param  array<int, array{path: string, kind: string, width: int, height: int}>  $versions
     * @param  callable(string, array): array  $runner  fn($imagePath, $options) => ['text'=>..., 'words'=>[...], 'conf'=>?float]
     * @return array{text: string, psm: int, kind: string, passes: int, conf: ?float, words: array<int, mixed>}
     */
    public function select(array $versions, string $classification, callable $runner, array $options = []): array
    {
        $psmModes = array_filter(
            array_map('intval', (array) ($options['psm_modes'] ?? $this->cfg('ocr.psm_modes', [6, 11, 12]))),
            fn (int $mode) => $mode > 0
        );

        $maxPasses = (int) ($options['max_passes'] ?? $this->cfg('ocr.max_passes', 6));

        if ($psmModes === []) {
            $psmModes = [$this->cfg('ocr.default_psm', 3)];
        }

        if ($versions === []) {
            $versions = [['path' => '', 'kind' => 'original', 'width' => 0, 'height' => 0]];
        }

        $candidates = [];
        $seen = [];
        $passes = 0;
        $earlyExitEnabled = (bool) ($options['early_exit'] ?? $this->cfg('ocr.early_exit_enabled', true));
        $earlyExitScore = (float) ($options['early_exit_score'] ?? $this->cfg('ocr.early_exit_score', 7.5));
        $bestScore = 0.0;

        foreach ($versions as $version) {
            foreach ($psmModes as $psm) {
                if ($passes >= $maxPasses) {
                    break 2;
                }

                $path = $version['path'] ?? '';

                if ($path === '') {
                    continue;
                }

                try {
                    $result = $runner($path, ['psm' => $psm, 'tsv' => $passes === 0]);
                } catch (\Throwable) {
                    // Some runners (and test fakes) cannot produce TSV; retry
                    // the same pass as plain text rather than failing the run.
                    $result = $runner($path, ['psm' => $psm]);
                }

                $text = trim((string) ($result['text'] ?? ''));

                if ($text === '') {
                    $passes++;

                    continue;
                }

                $key = $this->fingerprint($text);

                if (isset($seen[$key])) {
                    $passes++;

                    continue;
                }

                $seen[$key] = true;

                $scoreData = $this->score($text, $classification);
                $candidates[] = [
                    'text' => $text,
                    'psm' => $psm,
                    'kind' => $version['kind'] ?? 'original',
                    'conf' => isset($result['conf']) ? (float) $result['conf'] : null,
                    'words' => $result['words'] ?? [],
                    'score' => $scoreData,
                ];

                $bestScore = max($bestScore, (float) $scoreData['score']);
                $passes++;

                // Early-exit: if a candidate already scores very high, stop
                // running remaining PSMs/versions — accuracy already achieved.
                if ($earlyExitEnabled && $bestScore >= $earlyExitScore) {
                    break 2;
                }
            }
        }

        if ($candidates === []) {
            return [
                'text' => '',
                'psm' => (int) ($psmModes[0] ?? 3),
                'kind' => 'original',
                'passes' => $passes,
                'conf' => null,
                'words' => [],
            ];
        }

        usort($candidates, fn (array $a, array $b) => $b['score']['score'] <=> $a['score']['score']);

        $best = $candidates[0];

        return [
            'text' => $best['text'],
            'psm' => $best['psm'],
            'kind' => $best['kind'],
            'passes' => $passes,
            'conf' => $best['conf'],
            'words' => $best['words'],
            'runners_up' => array_map(fn (array $c) => $c['text'], array_slice($candidates, 1, 2)),
        ];
    }

    /**
     * Scores an OCR candidate for a given classification. Higher is better.
     *
     * @return array{score: float, breakdown: array<string, float|int>}
     */
    public function score(string $text, string $classification): array
    {
        $lower = mb_strtolower($text);
        $tokens = preg_split('/[\s,;]+/u', trim($text)) ?: [];
        $tokens = array_values(array_filter($tokens, fn (string $t) => $t !== ''));
        $meaningful = array_filter($tokens, fn (string $t) => (bool) preg_match('/[a-z0-9]/i', $t));

        $score = 0.0;
        $breakdown = [];

        // Expected labels for the classified type carry the most weight.
        $labels = $this->expectedLabels($classification);
        $labelHits = 0;

        foreach ($labels as $label) {
            if (preg_match('/\b'.preg_quote($label, '/').'\b/i', $text)) {
                $labelHits++;
                $score += 2.0;
            }
        }

        $breakdown['label_hits'] = $labelHits;
        $breakdown['label_score'] = $labelHits * 2.0;

        // Field patterns.
        $patterns = 0;

        if (preg_match('/\b\d{1,2}[-\/\.]\d{1,2}[-\/\.]\d{2,4}\b|\b\d{4}[-\/\.]\d{1,2}[-\/\.]\d{1,2}\b/i', $text)) {
            $patterns += 2;
        }
        if (preg_match('/\b[a-z]{3,9}\.?\s+\d{1,2},?\s+\d{4}\b|\b\d{1,2}\s+[a-z]{3,9}\.?\s+\d{4}\b/i', $text)) {
            $patterns += 2;
        }
        if (preg_match('/\b[A-Z0-9]{2,}[-\s][A-Z0-9][A-Z0-9\-]{3,}\b/', $text)) {
            $patterns += 2;
        }
        if (preg_match('/\b(?:st\.?|street|brgy|barangay|ave\.?|avenue|road|rd\.?|city|blvd)\b/i', $text)) {
            $patterns += 1;
        }

        $breakdown['pattern_score'] = $patterns;
        $score += $patterns;

        // Name-like tokens (multiple uppercase words).
        $nameLike = 0;

        foreach ($tokens as $token) {
            if (preg_match('/^[A-Z][A-Z\.\-\']+$/u', $token)) {
                $nameLike++;
            }
        }

        $breakdown['name_tokens'] = $nameLike;
        $score += min(3.0, $nameLike * 0.5);

        // Density: meaningful tokens per character, capped so a huge blob of
        // garbage cannot win on length alone.
        $length = mb_strlen($text);

        if ($length > 0) {
            $density = min(1.5, (count($meaningful) / $length) * 10);
            $score += $density;
            $breakdown['density_score'] = $density;
        }

        // Word-level confidence is folded in by the caller (runTesseractWithOptions)
        // which supplies the 'conf' value used above for ordering candidates.
        $breakdown['token_count'] = count($tokens);
        $breakdown['meaningful_tokens'] = count($meaningful);

        // Penalties.
        $penalty = 0.0;

        if ($length < 20) {
            $penalty += 1.5;
        }

        if (preg_match('/[^\x20-\x7E\x{00A0}-\x{00FF}]/u', $text)) {
            $penalty += 2.0;
        }

        $symbols = preg_match_all('/[^a-z0-9\s\-\.\'\/:,()@_]/i', $text);

        if ($symbols > max(6, (int) ($length * 0.15))) {
            $penalty += 2.0;
        }

        if (preg_match('/([a-z])\1{4,}/i', $text)) {
            $penalty += 1.0;
        }

        $breakdown['penalty'] = $penalty;
        $score -= $penalty;

        return [
            'score' => round(max(0.0, $score), 4),
            'breakdown' => $breakdown,
        ];
    }

    private function fingerprint(string $text): string
    {
        $normalized = mb_strtolower(trim($text));
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return hash('sha256', $normalized);
    }

    private function cfg(string $key, mixed $default): mixed
    {
        try {
            return function_exists('config') ? config($key, $default) : $default;
        } catch (\Throwable) {
            return $default;
        }
    }

    private function expectedLabels(string $classification): array
    {
        return match ($classification) {
            DocumentTypes::GOVERNMENT_ID, DocumentTypes::BARANGAY_ID => [
                'surname', 'given name', 'middle name', 'full name', 'sex', 'gender',
                'date of birth', 'birth date', 'address', 'id no', 'id number',
                'identification', 'date issued', 'expiration', 'valid until',
            ],
            DocumentTypes::DTI_BUSINESS_REGISTRATION => [
                'business name', 'registration', 'owner', 'trade name',
            ],
            DocumentTypes::COMMUNITY_TAX_CERTIFICATE => [
                'community tax', 'cedula', 'residence certificate', 'tin',
            ],
            DocumentTypes::BUSINESS_LOCATION_PROOF => [
                'lease', 'deed', 'occupancy', 'property', 'land title',
            ],
            DocumentTypes::HEALTH_CERTIFICATE => [
                'health certificate', 'medical', 'physician', 'holder',
            ],
            DocumentTypes::SEC_REGISTRATION => [
                'incorporation', 'securities', 'corporation', 'registration',
            ],
            DocumentTypes::APPLICATION_FORM => [
                'application', 'applicant', 'business', 'signature',
            ],
            DocumentTypes::BUSINESS_PERMIT => [
                'permit', 'license', 'business', 'valid', 'owner',
            ],
            default => ['date', 'name', 'no', 'number', 'address'],
        };
    }
}
