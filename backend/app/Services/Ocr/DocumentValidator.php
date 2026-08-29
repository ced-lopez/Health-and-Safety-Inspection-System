<?php

namespace App\Services\Ocr;

use App\Models\Document;
use App\Models\InspectionRequest;
use Carbon\Carbon;
use Throwable;

/**
 * Validates extracted fields where possible. Validation never rejects a
 * document; problems are reported as warnings so staff can review them.
 */
class DocumentValidator
{
    public function validate(Document $document, string $classification, array $fields): array
    {
        $warnings = [];

        $this->validateRequiredFields($classification, $fields, $warnings);
        $this->validateDateOrder($fields, $warnings);
        $this->validateEmail($fields, $warnings);
        $this->validateRegistrationNumbers($fields, $warnings);
        $this->crossCheckApplication($document, $fields, $warnings);

        return [
            'is_valid' => $warnings === [],
            'warnings' => $warnings,
        ];
    }

    private function validateRequiredFields(string $classification, array $fields, array &$warnings): void
    {
        $required = $this->requiredFor($classification);
        $missing = [];

        foreach ($required as $field) {
            if (($fields[$field]['value'] ?? null) === null) {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            $warnings[] = [
                'scope' => 'document',
                'code' => 'required_fields_missing',
                'message' => 'Required fields could not be read from the document. Review the extracted values below.',
                'fields' => $missing,
            ];
        }
    }

    private function validateDateOrder(array $fields, array &$warnings): void
    {
        $pairs = [
            ['expiration_date', 'date_issued', 'Expiration date'],
            ['expiration_date', 'issue_date', 'Expiration date'],
            ['expiration_date', 'registration_date', 'Expiration date'],
            ['expiration_date', 'start_date', 'Expiration date'],
        ];

        foreach ($pairs as [$expField, $baseField, $label]) {
            $exp = $this->parse($fields[$expField]['value'] ?? null);
            $base = $this->parse($fields[$baseField]['value'] ?? null);

            if ($exp === null || $base === null) {
                continue;
            }

            if ($exp->lt($base->startOfDay())) {
                $warnings[] = [
                    'scope' => $expField,
                    'code' => 'expiration_before_issue',
                    'message' => $label.' is earlier than the issue/registration date.',
                ];
            }
        }
    }

    private function validateEmail(array $fields, array &$warnings): void
    {
        $email = $fields['email']['value'] ?? null;

        if ($email === null) {
            return;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $warnings[] = [
                'scope' => 'email',
                'code' => 'invalid_email',
                'message' => 'The extracted email address does not look valid.',
            ];
        }
    }

    private function validateRegistrationNumbers(array $fields, array &$warnings): void
    {
        foreach (['business_name_registration_number', 'registration_number', 'certificate_number', 'permit_number', 'id_number'] as $field) {
            $value = $fields[$field]['value'] ?? null;

            if ($value === null) {
                continue;
            }

            if (preg_match('/[^A-Za-z0-9\- ]/', $value)) {
                $warnings[] = [
                    'scope' => $field,
                    'code' => 'unnormalized_number',
                    'message' => 'The extracted number may contain characters outside its original format.',
                ];
            }
        }
    }

    private function crossCheckApplication(Document $document, array $fields, array &$warnings): void
    {
        $request = $document->documentable instanceof InspectionRequest
            ? $document->documentable
            : null;

        if ($request === null) {
            return;
        }

        $businessName = $fields['business_name']['value'] ?? $fields['corporate_name']['value'] ?? null;
        if ($businessName !== null && $request->business_name !== null) {
            if (! $this->looselyMatches($businessName, $request->business_name)) {
                $warnings[] = [
                    'scope' => 'business_name',
                    'code' => 'business_name_mismatch',
                    'message' => 'The extracted business name differs from the one submitted in the application.',
                    'extracted' => $businessName,
                    'on_file' => $request->business_name,
                ];
            }
        }

        $ownerName = $fields['owner_name']['value'] ?? $fields['applicant_name']['value'] ?? $fields['full_name']['value'] ?? null;
        if ($ownerName !== null && $request->applicant_name !== null) {
            if (! $this->looselyMatches($ownerName, $request->applicant_name)) {
                $warnings[] = [
                    'scope' => 'owner_name',
                    'code' => 'applicant_name_mismatch',
                    'message' => 'The extracted applicant/owner name differs from the application.',
                    'extracted' => $ownerName,
                    'on_file' => $request->applicant_name,
                ];
            }
        }

        $address = $fields['address']['value'] ?? $fields['business_address']['value'] ?? $fields['applicant_address']['value'] ?? null;
        if ($address !== null && $request->applicant_address !== null) {
            if (! $this->looselyMatches($address, $request->applicant_address)) {
                $warnings[] = [
                    'scope' => 'address',
                    'code' => 'address_mismatch',
                    'message' => 'The extracted address differs from the application record.',
                    'extracted' => $address,
                    'on_file' => $request->applicant_address,
                ];
            }
        }
    }

    private function looselyMatches(string $a, string $b): bool
    {
        $norm = fn (string $value) => preg_replace('/[^a-z0-9]+/i', ' ', mb_strtolower(trim($value)));

        $na = trim((string) $norm($a));
        $nb = trim((string) $norm($b));

        if ($na === '' || $nb === '') {
            return true;
        }

        return str_contains($na, $nb) || str_contains($nb, $na);
    }

    private function parse(?string $date): ?Carbon
    {
        if ($date === null) {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (Throwable) {
            return null;
        }
    }

    private function requiredFor(string $classification): array
    {
        return match ($classification) {
            DocumentTypes::GOVERNMENT_ID => ['full_name', 'id_number'],
            DocumentTypes::BARANGAY_ID => ['full_name', 'id_number', 'barangay_name'],
            DocumentTypes::DTI_BUSINESS_REGISTRATION => ['business_name'],
            DocumentTypes::COMMUNITY_TAX_CERTIFICATE => ['certificate_number', 'full_name'],
            DocumentTypes::BUSINESS_LOCATION_PROOF => ['document_type'],
            DocumentTypes::HEALTH_CERTIFICATE => ['certificate_number', 'holder_name'],
            DocumentTypes::SEC_REGISTRATION => ['corporate_name'],
            DocumentTypes::APPLICATION_FORM => ['applicant_name', 'business_name'],
            default => [],
        };
    }
}
