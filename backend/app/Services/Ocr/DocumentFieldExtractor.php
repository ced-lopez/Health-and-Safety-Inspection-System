<?php

namespace App\Services\Ocr;

use Carbon\Carbon;
use Throwable;

class DocumentFieldExtractor
{
    public function extract(string $text, string $classification): array
    {
        $text = $this->clean($text);

        return [
            'business_name' => $this->matchField($text, [
                'business name',
                'name of business',
                'establishment name',
                'trade name',
            ]),
            'owner_name' => $this->matchField($text, [
                "owner's name",
                'owner name',
                'proprietor',
                'registered owner',
                'name of owner',
                'applicant',
            ]),
            'permit_number' => $this->matchPermitNumber($text),
            'issuing_authority' => $this->matchIssuingAuthority($text),
            'issuing_office' => $this->matchIssuingOffice($text),
        ] + $this->matchDates($text);
    }

    public function detectExpiration(?string $expirationDate): array
    {
        $parsed = $expirationDate ? $this->parseDate($expirationDate) : null;

        if (! $parsed) {
            return [
                'is_expired' => false,
                'expiration_found' => false,
                'expiration_warning' => null,
            ];
        }

        $isExpired = $parsed->lt(Carbon::today()->startOfDay());

        return [
            'is_expired' => $isExpired,
            'expiration_found' => true,
            'expiration_warning' => $isExpired ? 'Document has expired.' : null,
        ];
    }

    private function clean(string $text): string
    {
        $value = str_replace(["\r"], '', $text);

        return preg_replace('/[^\x20-\x7E\x{00A0}-\x{00FF}\r\n]/u', '', $value) ?? $value;
    }

    private function matchField(string $text, array $labels): ?string
    {
        foreach ($labels as $label) {
            if (preg_match($this->fuzzyPattern($label), $text, $m)) {
                $value = trim($m[1]);
                $value = trim($value, " \t.:-,;\"'");

                if (mb_strlen($value) > 1) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function fuzzyPattern(string $label): string
    {
        $escaped = preg_quote(str_replace("'", "\x01", $label), '/');
        $escaped = str_replace("\x01", "['\\s\\*]?", $escaped);
        $escaped = str_replace('\\ ', '[ \\t\\\'\\*]+', $escaped);

        return '/\\b'.$escaped.'[ \\t]*[:.\-]?[ \\t]*([^\\n]{2,120})/i';
    }

    private function matchPermitNumber(string $text): ?string
    {
        if (preg_match(
            '/(?:permit|license|ordinance|certificate|reg(?:istration)?)[ \t]*(?:no\.?|number\.?)?[ \t]*[:#]?[ \t]*([a-z0-9][a-z0-9\-]{2,30})/i',
            $text,
            $m
        )) {
            return strtoupper(trim($m[1]));
        }

        return null;
    }

    private function matchIssuingAuthority(string $text): ?string
    {
        $authorities = [
            'barangay' => 'Barangay Government',
            'city government' => 'City Government',
            'municipal government' => 'Municipal Government',
            'city of caloocan' => 'City of Caloocan',
            'office of the mayor' => 'Office of the Mayor',
            'bplo' => 'Business Permits and Licensing Office',
            'city health' => 'City Health Office',
            'bureau of fire protection' => 'Bureau of Fire Protection',
            'bfp' => 'Bureau of Fire Protection',
        ];

        $lower = mb_strtolower($text);

        foreach ($authorities as $keyword => $label) {
            if (str_contains($lower, $keyword)) {
                return $label;
            }
        }

        return null;
    }

    private function matchIssuingOffice(string $text): ?string
    {
        if (preg_match('/[^\n]*office of[^\n]*/i', $text, $m)) {
            return trim($m[0]);
        }

        return null;
    }

    private function matchDates(string $text): array
    {
        $lines = preg_split('/\R/', $text) ?: [];
        $result = ['date_issued' => null, 'expiration_date' => null];

        foreach ($lines as $line) {
            $lower = mb_strtolower($line);
            $date = $this->findDateInLine($line);

            if ($date === null) {
                continue;
            }

            if (
                str_contains($lower, 'expir')
                || str_contains($lower, 'valid')
                || str_contains($lower, 'until')
                || str_contains($lower, 'validity')
                || str_contains($lower, 'good through')
            ) {
                $result['expiration_date'] = $date;
            } elseif (
                str_contains($lower, 'issued')
                || str_contains($lower, 'issue')
                || str_contains($lower, 'granted')
                || str_contains($lower, 'date issued')
            ) {
                $result['date_issued'] = $date;
            }
        }

        return $result;
    }

    private function findDateInLine(string $line): ?string
    {
        if (preg_match('/\b(\d{4}[-\/.]\d{1,2}[-\/.]\d{1,2})\b/', $line, $m)) {
            return $this->normalizeDate($m[1]);
        }

        if (preg_match('/\b(\d{1,2}[-\/.]\d{1,2}[-\/.]\d{2,4})\b/', $line, $m)) {
            return $this->normalizeDate($m[1]);
        }

        if (preg_match('/\b(\d{1,2}\s+(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?\s+\d{2,4})\b/i', $line, $m)) {
            return $this->normalizeDate($m[1]);
        }

        if (preg_match('/\b((?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?\s+\d{1,2},?\s+\d{2,4})\b/i', $line, $m)) {
            return $this->normalizeDate($m[1]);
        }

        return null;
    }

    private function normalizeDate(string $value): string
    {
        return str_replace(['.', '/'], '-', $value);
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = trim($value);
        $value = str_replace(['.', '/'], '-', $value);

        foreach (['Y-m-d', 'm-d-Y', 'd-m-Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (Throwable) {
                // try next format
            }
        }

        foreach (['M d Y', 'M j, Y', 'M j Y', 'd M Y', 'j M Y', 'F d, Y', 'F j, Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (Throwable) {
                // try next format
            }
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
