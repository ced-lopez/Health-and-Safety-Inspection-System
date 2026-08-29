<?php

namespace App\Services\Ocr;

/**
 * Classifies OCR text into a canonical document type.
 *
 * Uses multiple, weighted evidence signals (titles, labels, issuing
 * authorities, terminology) so that a document is never classified using a
 * single keyword alone. Returns 'unknown' when the document cannot be
 * confidently classified.
 */
class DocumentClassifier
{
    /**
     * Evidence keywords per type. Weight determines signal strength.
     * Two or more distinct matches and a minimum cumulative score are required.
     */
    private const TYPE_KEYWORDS = [
        DocumentTypes::GOVERNMENT_ID => [
            'government issued id' => 4,
            'government-issued identification' => 4,
            'government issued identification' => 4,
            'unified multipurpose id' => 4,
            'umid' => 3,
            "driver's license" => 3,
            'drivers license' => 3,
            'driving license' => 3,
            'philippine national id' => 4,
            'national identity card' => 3,
            'philhealth' => 3,
            'postal id' => 3,
            'tin id' => 3,
            'passport' => 3,
            'prc id' => 3,
            'professional regulation commission' => 3,
            'voters id' => 3,
            'voter' => 2,
            'sss' => 2,
            'philippine identification' => 2,
            'republic of the philippines' => 1,
            'blood type' => 1,
            'date of birth' => 1,
        ],
        DocumentTypes::BARANGAY_ID => [
            'barangay id' => 4,
            'barangay identification' => 4,
            'barangay 178' => 2,
            'sk id' => 3,
            'sk chairman' => 2,
            'punong barangay' => 3,
            'barangay captain' => 3,
            'barangay chairman' => 3,
            'barangay official' => 2,
            'kagawad' => 2,
            'barangay council' => 2,
            'sangguniang barangay' => 3,
        ],
        DocumentTypes::DTI_BUSINESS_REGISTRATION => [
            'dti' => 3,
            'department of trade and industry' => 4,
            'certificate of business name registration' => 4,
            'business name registration' => 3,
            'certificate of business name' => 3,
            'business name certificate' => 2,
            'sole proprietorship' => 2,
            'single proprietorship' => 2,
            'registered business name' => 2,
            'territorial scope' => 2,
        ],
        DocumentTypes::SEC_REGISTRATION => [
            'securities and exchange commission' => 4,
            'certificate of incorporation' => 4,
            'articles of incorporation' => 4,
            'sec registration' => 4,
            'incorporators' => 2,
            'primary license' => 2,
            'secondary license' => 2,
            'corporate name' => 2,
            'registered corporation' => 2,
            'authorized capital stock' => 2,
            'paid-up capital' => 2,
            'sec form' => 2,
            'corporation' => 1,
        ],
        DocumentTypes::COMMUNITY_TAX_CERTIFICATE => [
            'community tax certificate' => 4,
            'community tax' => 3,
            'cedula' => 3,
            'residence certificate' => 3,
            'certificate of residence' => 3,
            'basic community tax' => 3,
            'additional community tax' => 3,
            'total community tax' => 3,
            'ctc no' => 2,
            'cedula no' => 2,
        ],
        DocumentTypes::BUSINESS_LOCATION_PROOF => [
            'proof of business location' => 4,
            'proof of residency' => 4,
            'certificate of occupancy' => 4,
            'contract of lease' => 4,
            'lease agreement' => 3,
            'deed of absolute sale' => 4,
            'deed of sale' => 3,
            'contract to sell' => 3,
            'certificate of land title' => 4,
            'tax declaration' => 3,
            'land title' => 3,
            'barangay certificate' => 2,
            'property owner' => 1,
        ],
        DocumentTypes::HEALTH_CERTIFICATE => [
            'health certificate' => 4,
            'certificate of medical examination' => 4,
            'medical certificate' => 3,
            'food handler' => 3,
            'food handling' => 2,
            'physician' => 2,
            'attending physician' => 2,
            'city health office' => 3,
            'municipal health office' => 3,
            'health office' => 2,
            'x-ray' => 1,
            'stool examination' => 1,
        ],
        DocumentTypes::APPLICATION_FORM => [
            'application form' => 4,
            'inspection application' => 3,
            'application for health and safety' => 4,
            'business permit application' => 3,
            'application for business' => 2,
            'for official use only' => 3,
            'signature over printed name' => 2,
            'fill out' => 1,
            'applicant name' => 1,
        ],
        DocumentTypes::VICINITY_MAP => [
            'vicinity map' => 4,
            'location map' => 3,
            'locational sketch' => 4,
            'sketch map' => 3,
            'vicinity plan' => 4,
            'site plan' => 2,
            'north arrow' => 2,
            'legend' => 1,
            'scale' => 1,
        ],
        DocumentTypes::ESTABLISHMENT_PHOTO => [
            'establishment photo' => 4,
            'photos of the establishment' => 4,
            'store front' => 3,
            'front view' => 2,
            'exterior view' => 2,
            'photo of establishment' => 4,
            'establishment pictures' => 3,
        ],
        DocumentTypes::BUSINESS_PERMIT => [
            'business permit' => 4,
            "mayor's permit" => 4,
            'mayors permit' => 4,
            'business license' => 3,
            'license to operate' => 2,
            'permit to operate' => 2,
            'sanitary permit' => 2,
            'barangay permit' => 2,
            'permit no' => 2,
            'license no' => 2,
            'business permits and licensing office' => 3,
            'bplo' => 2,
        ],
    ];

    private const MIN_SCORE = 4;

    private const MIN_MATCHES = 2;

    public function classify(string $text): array
    {
        $normalized = $this->normalize($text);

        if ($normalized === '') {
            return [
                'type' => DocumentTypes::UNKNOWN,
                'label' => DocumentTypes::label(DocumentTypes::UNKNOWN),
                'confidence' => 0.0,
            ];
        }

        $bestType = DocumentTypes::UNKNOWN;
        $bestScore = 0;
        $bestMatches = 0;

        foreach (self::TYPE_KEYWORDS as $type => $keywords) {
            $matches = 0;
            $score = 0;

            foreach ($keywords as $keyword => $weight) {
                if ($this->containsKeyword($normalized, $keyword)) {
                    $score += $weight;
                    $matches++;
                }
            }

            if (($score > $bestScore || ($score === $bestScore && $matches > $bestMatches))
                && $this->qualified($score, $matches)) {
                $bestScore = $score;
                $bestMatches = $matches;
                $bestType = $type;
            }
        }

        $isKnown = $bestType !== DocumentTypes::UNKNOWN;
        $confidence = $isKnown
            ? min(0.99, 0.5 + $bestScore * 0.055)
            : min(0.5, 0.15 + mb_strlen($normalized) * 0.002);

        return [
            'type' => $bestType,
            'label' => DocumentTypes::label($bestType),
            'confidence' => (float) round(max(0.0, $confidence), 4),
        ];
    }

    private function qualified(int $score, int $matches): bool
    {
        return $score >= self::MIN_SCORE && $matches >= self::MIN_MATCHES;
    }

    private function containsKeyword(string $normalized, string $keyword): bool
    {
        if (preg_match('/\b'.$keyword.'\b/', $normalized)) {
            return true;
        }

        // A loose fallback catches hyphenated/abbreviated real-world OCR variants.
        return strlen($keyword) >= 8 && str_contains($normalized, $keyword);
    }

    private function normalize(string $text): string
    {
        $value = mb_strtolower($text);
        $value = str_replace(["\r", "\t"], ' ', $value);
        $value = preg_replace('/[^a-z0-9\s.\'\-]/', ' ', $value) ?? $value;

        return trim((string) preg_replace('/\s+/', ' ', $value));
    }
}
