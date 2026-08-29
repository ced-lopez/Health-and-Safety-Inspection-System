<?php

namespace App\Services\Ocr;

use thiagoalessio\TesseractOCR\TesseractOCR;

/**
 * Thin wrapper around a single Tesseract invocation. Exposes the engine's
 * page segmentation modes and TSV word data (used for region-based OCR and
 * confidence-aware result selection) without hard-coding engine paths.
 */
class TesseractRunner
{
    /**
     * Runs one Tesseract pass.
     *
     * @param  array<string, mixed>  $options  psm, oem, lang, dpi, tsv
     * @return array{text: string, words: array<int, array<string, mixed>>, psm: int, conf: ?float, version: ?string}
     */
    public function run(string $imagePath, array $options = []): array
    {
        $psm = (int) ($options['psm'] ?? config('ocr.default_psm', 3));
        $tsv = (bool) ($options['tsv'] ?? false);

        $ocr = (new TesseractOCR($imagePath))
            ->executable(config('ocr.tesseract_binary'))
            ->lang(config('ocr.lang'))
            ->psm($psm);

        $oem = (int) ($options['oem'] ?? config('ocr.oem', 3));

        if ($oem >= 0 && $oem <= 3) {
            $ocr->oem($oem);
        }

        if ($tsv) {
            $ocr->tsv();
        }

        $version = null;

        try {
            $version = $ocr->version();
        } catch (\Throwable) {
            // Version probe is best-effort only.
        }

        $raw = trim((string) $ocr->run());

        if ($tsv) {
            return [
                'text' => $this->plainTextFromTsv($raw),
                'words' => $this->parseTsv($raw),
                'psm' => $psm,
                'conf' => null,
                'version' => $version,
            ];
        }

        $words = $this->wordsFromPlainText($raw);

        return [
            'text' => $raw,
            'words' => $words,
            'psm' => $psm,
            'conf' => $this->averageConfidence($words),
            'version' => $version,
        ];
    }

    /**
     * Parses Tesseract TSV output into word-level rows (position + confidence).
     *
     * @return array<int, array{text: string, left: int, top: int, width: int, height: int, conf: float}>
     */
    public function parseTsv(string $tsv): array
    {
        $words = [];
        $lines = preg_split('/\R/u', trim($tsv)) ?: [];

        foreach ($lines as $line) {
            if ($line === '' || str_starts_with($line, "level\t")) {
                continue;
            }

            $cols = explode("\t", $line);

            if (count($cols) < 12) {
                continue;
            }

            // level,page,block,par,line,word,left,top,width,height,conf,text
            $level = (int) $cols[0];
            $text = $cols[11] ?? '';

            if ($level !== 5 || $text === '') {
                continue;
            }

            $words[] = [
                'text' => $text,
                'left' => (int) $cols[6],
                'top' => (int) $cols[7],
                'width' => (int) $cols[8],
                'height' => (int) $cols[9],
                'conf' => (float) $cols[10],
            ];
        }

        return $words;
    }

    /**
     * @param  array<int, array{text: string, left: int, top: int, width: int, height: int, conf: float}>  $words
     */
    private function plainTextFromTsv(string $tsv): string
    {
        $words = $this->parseTsv($tsv);
        $lines = [];

        foreach ($words as $word) {
            $y = intdiv($word['top'], 12);

            if (! isset($lines[$y])) {
                $lines[$y] = '';
            }

            $lines[$y] .= $lines[$y] === '' ? $word['text'] : ' '.$word['text'];
        }

        ksort($lines);

        return trim(implode("\n", $lines));
    }

    /**
     * @param  array<int, array{text: string, left: int, top: int, width: int, height: int, conf: float}>  $words
     */
    private function averageConfidence(array $words): ?float
    {
        if ($words === []) {
            return null;
        }

        $total = 0.0;
        $count = 0;

        foreach ($words as $word) {
            if ($word['conf'] >= 0) {
                $total += $word['conf'];
                $count++;
            }
        }

        return $count > 0 ? $total / $count : null;
    }

    /**
     * Fallback: plain text has no per-word confidence, so we only estimate a
     * single synthetic token from the whole read.
     *
     * @return array<int, array{text: string, left: int, top: int, width: int, height: int, conf: float}>
     */
    private function wordsFromPlainText(string $raw): array
    {
        $tokens = preg_split('/\s+/u', trim($raw)) ?: [];

        return array_map(
            fn (string $token) => [
                'text' => $token,
                'left' => 0,
                'top' => 0,
                'width' => 0,
                'height' => 0,
                'conf' => 100.0,
            ],
            $tokens
        );
    }
}
