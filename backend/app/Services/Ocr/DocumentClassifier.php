<?php

namespace App\Services\Ocr;

class DocumentClassifier
{
    private const TYPE_KEYWORDS = [
        'business_permit' => [
            'business permit' => 3,
            "mayor's permit" => 3,
            'mayors permit' => 3,
            'business license' => 3,
            'license to operate' => 2,
            'permit to operate' => 2,
            'sanitary permit' => 2,
            'barangay permit' => 2,
            'permit no' => 2,
            'license no' => 2,
            'bplo' => 1,
            'ordinance no' => 1,
        ],
        'barangay_id' => [
            'barangay id' => 3,
            'barangay clearance' => 3,
            'community tax certificate' => 3,
            'residence certificate' => 2,
            'certificate of residency' => 2,
            'ctc no' => 1,
        ],
        'business_registration' => [
            'dti' => 2,
            'sec registration' => 2,
            'certificate of registration' => 2,
            'business registration' => 2,
            'bir registration' => 2,
        ],
    ];

    public function classify(string $text): array
    {
        $normalized = $this->normalize($text);
        $bestType = 'generic';
        $bestScore = 0;

        foreach (self::TYPE_KEYWORDS as $type => $keywords) {
            $score = 0;

            foreach ($keywords as $keyword => $weight) {
                if (str_contains($normalized, $keyword)) {
                    $score += $weight;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestType = $type;
            }
        }

        $confidence = $bestType === 'generic'
            ? (int) round(min(35, 10 + strlen(trim($text)) * 0.02))
            : (int) round(min(98, 55 + $bestScore * 8));

        return [
            'type' => $bestType,
            'label' => $this->labelFor($bestType),
            'confidence' => $confidence,
        ];
    }

    private function normalize(string $text): string
    {
        $value = mb_strtolower($text);
        $value = str_replace(["\r", "\t"], ' ', $value);
        $value = preg_replace('/[^a-z0-9\s.\'\-]/', ' ', $value) ?? $value;

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function labelFor(string $type): string
    {
        return match ($type) {
            'business_permit' => 'Business Permit',
            'barangay_id' => 'Barangay ID / Clearance',
            'business_registration' => 'Business Registration',
            default => 'Unclassified Document',
        };
    }
}
