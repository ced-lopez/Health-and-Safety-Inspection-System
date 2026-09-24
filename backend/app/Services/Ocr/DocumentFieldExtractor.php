<?php

namespace App\Services\Ocr;

use Carbon\Carbon;
use Throwable;

class DocumentFieldExtractor
{
    public const LOW_CONFIDENCE_THRESHOLD = 0.75;

    /**
     * Extracts structured fields (with per-field confidence) from OCR text
     * based on the classified document type.
     *
     * Every result value is an array: ['value' => scalar|null, 'confidence' => float].
     * Missing/unreadable fields return value null and confidence 0.
     */
    public function extract(string $text, string $classification): array
    {
        $clean = $this->clean($text);
        $lines = $this->lines($clean);

        $fields = [];

        foreach ($this->specFor($classification) as $field => $config) {
            $fields[$field] = $this->extractField($lines, $classification, $field, $config, $clean);
        }

        foreach ($this->extractCommon($lines, $classification, $clean) as $field => $result) {
            if (! isset($fields[$field]) || $fields[$field]['value'] === null) {
                $fields[$field] = $result;
            }
        }

        if (DocumentTypes::isIdType($classification)) {
            $fields = $this->reconstructFullName($fields);
        }

        return $fields;
    }

    public function lowConfidenceFields(array $fields): array
    {
        $flagged = [];

        foreach ($fields as $name => $result) {
            $value = $result['value'] ?? null;
            $confidence = (float) ($result['confidence'] ?? 0);

            if ($value !== null && $confidence < self::LOW_CONFIDENCE_THRESHOLD) {
                $flagged[] = [
                    'field' => $name,
                    'value' => $value,
                    'confidence' => round($confidence, 4),
                ];
            }
        }

        return $flagged;
    }

    public function isLowConfidence(float $confidence): bool
    {
        return $confidence < self::LOW_CONFIDENCE_THRESHOLD;
    }

    public function detectExpiration(?string $expirationDate): array
    {
        $parsed = $expirationDate ? $this->parseDate($expirationDate) : null;

        if (! $parsed) {
            return [
                'is_expired' => null,
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

    private function extractField(array $lines, string $classification, string $field, array $config, string $clean): array
    {
        $kind = $config['kind'] ?? 'text';

        if ($field === 'document_type' && $classification === DocumentTypes::BUSINESS_LOCATION_PROOF) {
            return $this->extractLocationProofSubType($lines);
        }

        if ($field === 'issuing_authority') {
            $authority = $this->extractIssuingAuthority($clean);

            if ($authority !== null) {
                return $this->result($authority, 0.92, $kind);
            }
        }

        if ($field === 'other_relevant_text' || $field === 'other_visible_labels') {
            return $this->aggregateLeftover($lines, $config);
        }

        if ($kind === 'date') {
            return $this->extractDateField($lines, $config);
        }

        if ($kind === 'address') {
            return $this->extractAddressField($lines, $classification, $config);
        }

        $matched = $this->findLabeledValue($lines, $config['labels'] ?? []);

        if ($matched === null) {
            if ($kind === 'number') {
                $fallback = $this->numberFallback($lines, $config['fallback_labels'] ?? []);

                return $fallback !== null
                    ? $this->result($fallback, 0.80, $kind)
                    : $this->result(null, 0.0, $kind);
            }

            return $this->result(null, 0.0, $kind);
        }

        $value = $matched['value'];

        // Value on the same line was empty; try the next non-empty line, but only
        // when the matched line was essentially just the bare label itself, so a
        // stray label-text like "Mango St" never swallows the line after it.
        if ($value === null || mb_strlen($value) < ($config['min_length'] ?? 2)) {
            $value = $matched['bare'] === true
                ? $this->nextLineValue($lines, $matched['line_index'], $kind, (int) ($config['min_length'] ?? 2))
                : null;
            $strategy = $value !== null ? 'next_line' : $matched['strategy'];
        } else {
            $strategy = $matched['strategy'];
        }

        if ($value === null) {
            return $this->result(null, 0.0, $kind);
        }

        $confidence = $this->confidenceFor($kind, $value, $strategy);

        return $this->result($value, $confidence, $kind);
    }

    private function extractDateField(array $lines, array $config): array
    {
        $matched = $this->findLabeledValue($lines, $config['labels'] ?? []);

        if ($matched !== null && $matched['value'] !== null) {
            $date = $this->normalizeDateValue($matched['value']);

            if ($date !== null) {
                return $this->result($date, 0.95, 'date');
            }
        }

        $date = $this->scanDateWithTriggers($lines, $config['triggers'] ?? []);

        return $date !== null
            ? $this->result($date, 0.9, 'date')
            : $this->result(null, 0.0, 'date');
    }

    private function extractAddressField(array $lines, string $classification, array $config): array
    {
        $matched = $this->findLabeledValue($lines, $config['labels'] ?? []);

        if ($matched === null) {
            return $this->result(null, 0.0, 'address');
        }

        $minLength = $config['min_length'] ?? 5;
        $value = $matched['value'];
        $startAt = $matched['line_index'] + 1;

        if ($value === null || mb_strlen($value) < $minLength) {
            if ($matched['bare'] !== true) {
                return $this->result(null, 0.0, 'address');
            }

            $value = $this->nextLineValue($lines, $matched['line_index'], 'address');
            $startAt = $matched['line_index'] + 2;
        }

        if ($value === null) {
            return $this->result(null, 0.0, 'address');
        }

        // Combine related address lines into a single structured value while
        // preserving the detected text exactly (no components are invented).
        $parts = [$value];
        $skip = $this->labelRegexesFor($classification);
        $collected = 0;
        $index = $startAt;

        while ($index < count($lines) && $collected < 4) {
            $line = trim($lines[$index]);

            if ($line === '' || $this->looksLikeDecor($line) || $this->looksLikeLabelLine($line, $skip)) {
                break;
            }

            $part = $this->sanitizeValue($line);

            if ($part === null || mb_strlen($part) < 3 || mb_strlen($part) > 80) {
                break;
            }

            $parts[] = $part;
            $collected++;
            $index++;
        }

        $joined = implode(', ', $parts);

        return $this->result(
            $joined,
            $this->clamp($this->confidenceFor('address', $joined, $matched['strategy']) + ($collected >= 1 ? 0.02 : 0.0)),
            'address'
        );
    }

    /**
     * Rebuilds full_name from surname/given/middle components when no full name
     * was printed on the ID. Reconstruction only happens when every present
     * component was read with at least medium confidence, so an uncertain read
     * is never silently combined; the individual components remain available
     * for staff verification.
     */
    private function reconstructFullName(array $fields): array
    {
        if (($fields['full_name']['value'] ?? null) !== null) {
            return $fields;
        }

        $components = ['given_name', 'middle_name', 'surname'];
        $pieces = [];
        $confidences = [];

        foreach ($components as $component) {
            $value = $fields[$component]['value'] ?? null;

            if ($value !== null) {
                $confidence = (float) ($fields[$component]['confidence'] ?? 0.0);

                if ($confidence < 0.75) {
                    return $fields;
                }

                $pieces[] = $value;
                $confidences[] = $confidence;
            }
        }

        if (count($pieces) < 2) {
            return $fields;
        }

        $fields['full_name'] = [
            'value' => implode(' ', $pieces),
            'confidence' => (float) round(min($confidences), 4),
        ];

        return $fields;
    }

    /**
     * Builds label-anchored skip regexes for the classification so address
     * line-joining stops before the next field's label.
     *
     * @return array<int, string>
     */
    private function labelRegexesFor(string $classification): array
    {
        $regexes = [];

        foreach ($this->specFor($classification) as $field => $config) {
            foreach ($config['labels'] ?? [] as $label) {
                $regexes[] = $this->labelStartPattern($label);
            }
        }

        return $regexes;
    }

    private function extractLocationProofSubType(array $lines): array
    {
        $text = mb_strtolower(implode("\n", $lines));
        $subtypes = [
            'certificate of occupancy' => 'Certificate of Occupancy',
            'contract of lease' => 'Contract of Lease',
            'lease agreement' => 'Lease Agreement',
            'deed of absolute sale' => 'Deed of Absolute Sale',
            'deed of sale' => 'Deed of Sale',
            'contract to sell' => 'Contract to Sell',
            'certificate of land title' => 'Certificate of Land Title',
            'tax declaration' => 'Tax Declaration',
        ];

        foreach ($subtypes as $needle => $label) {
            if (str_contains($text, $needle)) {
                return $this->result($label, 0.80, 'text');
            }
        }

        return $this->result(null, 0.0, 'text');
    }

    private function extractIssuingAuthority(string $clean): ?string
    {
        $lower = mb_strtolower($clean);
        $authorities = [
            'department of trade and industry' => 'Department of Trade and Industry',
            'securities and exchange commission' => 'Securities and Exchange Commission',
            'land transportation office' => 'Land Transportation Office',
            'bureau of internal revenue' => 'Bureau of Internal Revenue (BIR)',
            'philippine health insurance corporation' => 'Philippine Health Insurance Corporation (PhilHealth)',
            'social security system' => 'Social Security System (SSS)',
            'philippine statistics authority' => 'Philippine Statistics Authority (PSA)',
            'national statistics office' => 'Philippine Statistics Authority (PSA)',
            'professional regulation commission' => 'Professional Regulation Commission (PRC)',
            'commission on elections' => 'Commission on Elections (COMELEC)',
            'business permits and licensing office' => 'Business Permits and Licensing Office',
            'city health office' => 'City Health Office',
            'municipal health office' => 'Municipal Health Office',
            'department of health' => 'Department of Health',
            'office of the mayor' => 'Office of the Mayor',
            'registrar of deeds' => 'Registry of Deeds',
            'city government' => 'City Government',
            'municipal government' => 'Municipal Government',
            'barangay government' => 'Barangay Government',
            'bureau of fire protection' => 'Bureau of Fire Protection',
            'barangay hall' => 'Barangay Government',
        ];

        foreach ($authorities as $needle => $label) {
            if (str_contains($lower, $needle)) {
                return $label;
            }
        }

        if (preg_match('/dti/', $lower)) {
            return 'Department of Trade and Industry';
        }
        if (preg_match('/\bsec\b/', $lower)) {
            return 'Securities and Exchange Commission';
        }
        if (preg_match('/\bbir\b/', $lower)) {
            return 'Bureau of Internal Revenue (BIR)';
        }
        if (preg_match('/\bphilhealth\b/', $lower)) {
            return 'Philippine Health Insurance Corporation (PhilHealth)';
        }
        if (preg_match('/\bsss\b/', $lower)) {
            return 'Social Security System (SSS)';
        }
        if (preg_match('/\bbarangay\b/', $lower)) {
            return 'Barangay Government';
        }

        return null;
    }

    private function aggregateLeftover(array $lines, array $config): array
    {
        $title = $config['title'] ?? null;
        $skipLabelRegexes = array_map(fn ($label) => $this->labelStartPattern($label), $config['labels'] ?? []);

        $collected = [];
        $count = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || mb_strlen($trimmed) < 2 || mb_strlen($trimmed) > 80) {
                continue;
            }
            if ($title !== null && $this->similar($trimmed, $title)) {
                continue;
            }
            if ($this->looksLikeLabelLine($trimmed, $skipLabelRegexes)) {
                continue;
            }
            if ($this->looksLikeDecor($trimmed)) {
                continue;
            }

            $collected[] = $trimmed;
            $count++;
            if ($count >= 10) {
                break;
            }
        }

        if ($collected === []) {
            return $this->result(null, 0.0, 'text');
        }

        $value = implode("\n", $collected);

        return $this->result($value, 0.72, 'text');
    }

    private function extractCommon(array $lines, string $classification, string $clean): array
    {
        $fields = [];

        $businessName = $this->findLabeledValue($lines, [
            'business name',
            'name of business',
            'trade name',
            'establishment name',
            'registered business name',
        ]);

        if ($businessName !== null && $businessName['value'] !== null) {
            $fields['business_name'] = $this->result(
                $businessName['value'],
                $this->confidenceFor('name', $businessName['value'], $businessName['strategy']),
                'name'
            );
        } else {
            $fields['business_name'] = $this->result(null, 0.0, 'name');
        }

        $owner = $this->findLabeledValue($lines, [
            "owner's name",
            'owner name',
            'proprietor',
            'registered owner',
            'name of owner',
            'applicant',
        ]);

        if ($owner !== null && $owner['value'] !== null) {
            $fields['owner_name'] = $this->result(
                $owner['value'],
                $this->confidenceFor('name', $owner['value'], $owner['strategy']),
                'name'
            );
        } else {
            $fields['owner_name'] = $this->result(null, 0.0, 'name');
        }

        $permitNumber = $this->matchedNumber($lines, [
            'permit number',
            'permit no',
            'license number',
            'license no',
            'business permit no',
        ]);
        $fields['permit_number'] = $permitNumber !== null
            ? $this->result($permitNumber, 0.9, 'number')
            : $this->result(null, 0.0, 'number');

        $authority = $this->extractIssuingAuthority($clean);
        $fields['issuing_authority'] = $authority !== null
            ? $this->result($authority, 0.92, 'text')
            : $this->result(null, 0.0, 'text');

        $office = $this->findOfficeLine($lines);
        $fields['issuing_office'] = $office !== null
            ? $this->result($office, 0.84, 'text')
            : $this->result(null, 0.0, 'text');

        $issued = $this->extractDateField($lines, [
            'labels' => ['date issued', 'date of issue', 'issue date', 'issued on', 'issued'],
            'triggers' => ['issued', 'issue', 'granted'],
        ]);
        $fields['date_issued'] = $issued;

        $expiration = $this->extractDateField($lines, [
            'labels' => ['expiration date', 'expiry date', 'valid until', 'valid thru', 'valid through', 'validity', 'expiration', 'good through'],
            'triggers' => ['expir', 'valid', 'until', 'validity', 'good through', 'expiry'],
        ]);
        $fields['expiration_date'] = $expiration;

        $certNumber = $this->matchedNumber($lines, [
            'certificate number',
            'certificate no',
            'cert no',
            'control number',
            'registration number',
            'registration no',
            'reg no',
        ]);
        $fields['certificate_number'] = $certNumber !== null
            ? $this->result($certNumber, 0.9, 'number')
            : $this->result(null, 0.0, 'number');

        return $fields;
    }

    private function specFor(string $classification): array
    {
        return match ($classification) {
            DocumentTypes::GOVERNMENT_ID => [
                'surname' => ['labels' => ['surname', 'last name', 'family name'], 'kind' => 'name'],
                'given_name' => ['labels' => ['given name', 'first name'], 'kind' => 'name'],
                'middle_name' => ['labels' => ['middle name'], 'kind' => 'name'],
                'full_name' => ['labels' => ['full name', 'complete name', 'name of applicant', 'holder name', 'name'], 'kind' => 'name'],
                'sex' => ['labels' => ['sex', 'gender'], 'kind' => 'name', 'min_length' => 1],
                'id_number' => ['labels' => ['id no', 'id number', 'identification number', 'identification no', 'crn', 'crn no', 'no'], 'kind' => 'number'],
                'date_of_birth' => ['labels' => ['date of birth', 'birth date', 'birthday', 'dob'], 'kind' => 'date', 'triggers' => ['date of birth', 'birth date', 'birthday']],
                'address' => ['labels' => ['address', 'present address', 'home address', 'residence address'], 'kind' => 'address', 'min_length' => 5, 'join_lines' => true],
                'date_issued' => ['labels' => ['date issued', 'date of issue', 'issue date', 'issued on'], 'kind' => 'date', 'triggers' => ['issued', 'issue']],
                'expiration_date' => ['labels' => ['expiration date', 'expiry date', 'valid until', 'valid thru', 'valid through', 'expiration', 'good through'], 'kind' => 'date', 'triggers' => ['expir', 'valid', 'good thru', 'good through', 'expiry']],
            ],
            DocumentTypes::BARANGAY_ID => [
                'barangay_name' => ['labels' => ['barangay', 'barangay name', 'sk brgy'], 'kind' => 'name'],
                'city' => ['labels' => ['city', 'municipality', 'city/municipality'], 'kind' => 'name'],
                'id_number' => ['labels' => ['id no', 'id number', 'barangay id no', 'member no', 'number'], 'kind' => 'number'],
                'surname' => ['labels' => ['surname', 'last name', 'family name'], 'kind' => 'name'],
                'given_name' => ['labels' => ['given name', 'first name'], 'kind' => 'name'],
                'middle_name' => ['labels' => ['middle name'], 'kind' => 'name'],
                'full_name' => ['labels' => ['full name', 'complete name', 'resident name', 'holder name', 'name'], 'kind' => 'name'],
                'sex' => ['labels' => ['sex', 'gender'], 'kind' => 'name', 'min_length' => 1],
                'date_of_birth' => ['labels' => ['date of birth', 'birth date', 'birthday', 'dob'], 'kind' => 'date', 'triggers' => ['date of birth', 'birth date', 'birthday']],
                'address' => ['labels' => ['address', 'present address', 'home address'], 'kind' => 'address', 'min_length' => 5, 'join_lines' => true],
                'date_issued' => ['labels' => ['date issued', 'date of issue', 'issue date', 'issued on'], 'kind' => 'date', 'triggers' => ['issued', 'issue']],
                'expiration_date' => ['labels' => ['expiration date', 'expiry date', 'valid until', 'valid thru', 'valid through', 'expiration', 'good through'], 'kind' => 'date', 'triggers' => ['expir', 'valid', 'good thru', 'good through', 'expiry']],
            ],
            DocumentTypes::DTI_BUSINESS_REGISTRATION => [
                'business_name' => ['labels' => ['business name', 'name of business', 'trade name', 'registered business name'], 'kind' => 'name'],
                'owner_name' => ['labels' => ["owner's name", 'owner name', 'proprietor', 'name of owner', 'registered owner'], 'kind' => 'name'],
                'business_name_registration_number' => ['labels' => ['business name registration no', 'business name registration number', 'registration number', 'registration no', 'reg no', 'certificate number'], 'kind' => 'number'],
                'registration_date' => ['labels' => ['date of registration', 'registration date', 'date registered', 'registered on'], 'kind' => 'date', 'triggers' => ['registration date', 'date of registration', 'registered']],
                'expiration_date' => ['labels' => ['expiration date', 'expiry date', 'valid until', 'valid thru', 'valid through', 'expiration', 'validity'], 'kind' => 'date', 'triggers' => ['expir', 'valid', 'expiry', 'good thru', 'good through']],
                'business_address' => ['labels' => ['business address', 'address of business', 'principal address', 'place of business'], 'kind' => 'address', 'min_length' => 5],
                'territorial_scope' => ['labels' => ['territorial scope', 'scope', 'place of operation'], 'kind' => 'text'],
                'issuing_authority' => ['labels' => ['issuing authority', 'issued by'], 'kind' => 'text'],
            ],
            DocumentTypes::COMMUNITY_TAX_CERTIFICATE => [
                'certificate_number' => ['labels' => ['community tax certificate no', 'ctc no', 'cedula no', 'cedula number', 'certificate no', 'certificate number', 'no'], 'kind' => 'number'],
                'full_name' => ['labels' => ['full name', 'complete name', 'taxpayer name', 'holder name', 'name'], 'kind' => 'name'],
                'address' => ['labels' => ['address', 'residence', 'home address', 'present address'], 'kind' => 'address', 'min_length' => 5],
                'tin' => ['labels' => ['tin no', 'tin number', 'tin', 'tax identification number'], 'kind' => 'number'],
                'date_of_birth' => ['labels' => ['date of birth', 'birth date', 'birthday', 'dob'], 'kind' => 'date', 'triggers' => ['date of birth', 'birth date', 'birthday']],
                'place_of_issue' => ['labels' => ['place of issue', 'place of issuance', 'issued at'], 'kind' => 'name'],
                'date_issued' => ['labels' => ['date issued', 'date of issue', 'issue date', 'issued'], 'kind' => 'date', 'triggers' => ['issued', 'issue']],
                'occupation' => ['labels' => ['occupation', 'business/occupation', 'profession', 'nature of work'], 'kind' => 'text'],
                'basic_community_tax' => ['labels' => ['basic community tax', 'basic tax'], 'kind' => 'amount'],
                'additional_community_tax' => ['labels' => ['additional community tax', 'additional tax'], 'kind' => 'amount'],
                'total_community_tax' => ['labels' => ['total community tax', 'total tax', 'total'], 'kind' => 'amount'],
            ],
            DocumentTypes::BUSINESS_LOCATION_PROOF => [
                'document_type' => ['kind' => 'text'],
                'property_owner_name' => ['labels' => ['property owner', "owner's name", 'registered owner', 'owner name'], 'kind' => 'name'],
                'applicant_or_lessee_name' => ['labels' => ['lessee', 'tenant', 'applicant', 'lessee name', 'name of lessee'], 'kind' => 'name'],
                'business_address' => ['labels' => ['business address', 'address of business'], 'kind' => 'address', 'min_length' => 5],
                'property_address' => ['labels' => ['property address', 'address of property', 'located at', 'location', 'address'], 'kind' => 'address', 'min_length' => 5],
                'document_number' => ['labels' => ['document number', 'document no', 'doc no', 'doc number'], 'kind' => 'number'],
                'contract_number' => ['labels' => ['contract number', 'contract no', 'ctr no', 'agreement no'], 'kind' => 'number'],
                'issue_date' => ['labels' => ['date issued', 'issue date', 'date of issue', 'issued on'], 'kind' => 'date', 'triggers' => ['issued', 'issue']],
                'start_date' => ['labels' => ['start date', 'commencement', 'effectivity', 'effective date', 'effectivity date'], 'kind' => 'date', 'triggers' => ['start date', 'commencement', 'effectiv', 'effective']],
                'expiration_date' => ['labels' => ['expiration date', 'expiry date', 'valid until', 'end date', 'termination date', 'valid thru', 'valid through'], 'kind' => 'date', 'triggers' => ['expir', 'valid', 'termination', 'end date', 'expiry']],
                'issuing_authority' => ['labels' => ['issuing authority', 'issued by'], 'kind' => 'text'],
            ],
            DocumentTypes::HEALTH_CERTIFICATE => [
                'certificate_number' => ['labels' => ['certificate number', 'certificate no', 'cert no', 'control number', 'card number', 'no'], 'kind' => 'number'],
                'holder_name' => ['labels' => ['holder name', 'name of applicant', 'full name', 'applicant name', 'name'], 'kind' => 'name'],
                'establishment_name' => ['labels' => ['establishment name', 'name of establishment', 'establishment', 'business name', 'company'], 'kind' => 'name'],
                'issuing_office' => ['labels' => ['issuing office', 'issued at', 'health office', 'office'], 'kind' => 'text'],
                'issue_date' => ['labels' => ['date issued', 'issue date', 'date of issue', 'issued'], 'kind' => 'date', 'triggers' => ['issued', 'issue']],
                'expiration_date' => ['labels' => ['expiration date', 'expiry date', 'valid until', 'valid thru', 'valid through', 'expiration', 'validity'], 'kind' => 'date', 'triggers' => ['expir', 'valid', 'expiry', 'good thru', 'good through']],
                'physician_or_authorized_officer' => ['labels' => ['physician', 'attending physician', 'medical officer', 'authorized officer', 'certified by', 'approved by'], 'kind' => 'name'],
            ],
            DocumentTypes::APPLICATION_FORM => [
                'applicant_name' => ['labels' => ['applicant name', 'name of applicant', 'applicant', 'complete name', 'full name'], 'kind' => 'name'],
                'age' => ['labels' => ['age'], 'kind' => 'int'],
                'address' => ['labels' => ['address', 'present address', 'home address', 'complete address'], 'kind' => 'address', 'min_length' => 5],
                'contact_number' => ['labels' => ['contact number', 'contact no', 'mobile number', 'mobile no', 'phone number', 'telephone number', 'cellphone'], 'kind' => 'text'],
                'email' => ['labels' => ['email address', 'email', 'e-mail'], 'kind' => 'email'],
                'business_name' => ['labels' => ['business name', 'name of business', 'trade name', 'name of store'], 'kind' => 'name'],
                'business_type' => ['labels' => ['business type', 'type of business'], 'kind' => 'text'],
                'nature_of_business' => ['labels' => ['nature of business', 'kind of business', 'line of business', 'business nature'], 'kind' => 'text'],
                'business_address' => ['labels' => ['business address', 'address of business', 'place of business'], 'kind' => 'address', 'min_length' => 5],
                'ownership_type' => ['labels' => ['type of ownership', 'ownership type', 'ownership', 'mode of ownership'], 'kind' => 'text'],
                'date_of_application' => ['labels' => ['date of application', 'date applied', 'application date', 'date'], 'kind' => 'date', 'triggers' => ['date of application', 'date applied', 'date']],
            ],
            DocumentTypes::SEC_REGISTRATION => [
                'corporate_name' => ['labels' => ['corporate name', 'name of corporation', 'company name', 'registered name'], 'kind' => 'name'],
                'registration_number' => ['labels' => ['registration number', 'sec reg no', 'registration no', 'reg no', 'secure registration no'], 'kind' => 'number'],
                'registration_date' => ['labels' => ['date of registration', 'registration date', 'date registered', 'registered on'], 'kind' => 'date', 'triggers' => ['registration date', 'date of registration', 'registered']],
                'corporation_type' => ['labels' => ['type of corporation', 'corporation type', 'kind of corporation'], 'kind' => 'name'],
                'principal_office_address' => ['labels' => ['principal office address', 'principal address', 'registered office', 'office address'], 'kind' => 'address', 'min_length' => 5],
                'business_purpose' => ['labels' => ['primary purpose', 'second purpose', 'business purpose', 'purposes'], 'kind' => 'text'],
                'incorporators' => ['labels' => ['incorporators', 'incorporator', 'names of incorporators'], 'kind' => 'text'],
                'authorized_capital' => ['labels' => ['authorized capital', 'authorized capital stock', 'capital stock', 'authorized'], 'kind' => 'amount'],
                'subscribed_capital' => ['labels' => ['subscribed capital', 'subscribed capital stock', 'subscribed'], 'kind' => 'amount'],
                'paid_up_capital' => ['labels' => ['paid up capital', 'paid-up capital', 'paid in capital', 'paid in'], 'kind' => 'amount'],
            ],
            DocumentTypes::VICINITY_MAP => [
                'business_name' => ['labels' => ['business name', 'establishment name', 'name of business'], 'kind' => 'name'],
                'street_name' => ['labels' => ['street', 'road', 'st', 'street name'], 'kind' => 'name'],
                'barangay' => ['labels' => ['barangay', 'brgy'], 'kind' => 'name'],
                'city' => ['labels' => ['city', 'municipality'], 'kind' => 'name'],
                'nearby_landmarks' => ['labels' => ['landmark', 'landmarks', 'reference'], 'kind' => 'text'],
                'road_names' => ['labels' => ['roads', 'road names', 'roads nearby'], 'kind' => 'text'],
                'other_visible_labels' => ['kind' => 'text', 'title' => 'vicinity map'],
            ],
            DocumentTypes::ESTABLISHMENT_PHOTO => [
                'visible_business_name' => ['labels' => ['business name', 'store name', 'name of store', 'name'], 'kind' => 'name'],
                'visible_address' => ['labels' => ['address', 'located at', 'location'], 'kind' => 'address', 'min_length' => 5],
                'visible_barangay' => ['labels' => ['barangay', 'brgy'], 'kind' => 'name'],
                'visible_contact_number' => ['labels' => ['contact', 'contact number', 'mobile', 'cell', 'phone'], 'kind' => 'text'],
                'other_relevant_text' => ['kind' => 'text', 'title' => 'establishment photo'],
            ],
            DocumentTypes::BUSINESS_PERMIT => [
                'business_name' => ['labels' => ['business name', 'name of business', 'establishment name', 'trade name'], 'kind' => 'name'],
                'owner_name' => ['labels' => ["owner's name", 'owner name', 'proprietor', 'registered owner', 'name of owner'], 'kind' => 'name'],
                'permit_number' => ['labels' => ['permit number', 'permit no', 'business permit no', 'license number', 'license no'], 'kind' => 'number', 'fallback_labels' => ['permit', 'license', 'ordinance', 'certificate']],
                'issuing_authority' => ['labels' => ['issuing authority', 'issued by'], 'kind' => 'text'],
                'issuing_office' => ['kind' => 'text'],
                'date_issued' => ['labels' => ['date issued', 'date of issue', 'issue date', 'issued on'], 'kind' => 'date', 'triggers' => ['issued', 'issue', 'granted']],
                'expiration_date' => ['labels' => ['expiration date', 'expiry date', 'valid until', 'valid thru', 'valid through', 'validity', 'good through'], 'kind' => 'date', 'triggers' => ['expir', 'valid', 'until', 'good thru', 'good through', 'expiry']],
            ],
            default => [],
        };
    }

    /*
     |----------------------------------------------------------------------
     | Matching helpers
     |----------------------------------------------------------------------
     */

    private function findLabeledValue(array $lines, array $labels): ?array
    {
        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            foreach ($labels as $label) {
                if (preg_match($this->labelPattern($label), $line, $m)) {
                    $separator = trim($m[2]);
                    $value = $this->sanitizeValue($m[4]);

                    // e.g. "CERTIFICATE OF *BUSINESS NAME* REGISTRATION" must not be
                    // mistaken for the value of Business Name. Only rejected when no
                    // explicit separator (":", "-") precedes the value.
                    if ($separator === '' && $value !== null && $this->isContinuationWord($value)) {
                        continue;
                    }

                    $bare = mb_strtolower(trim($line, " \t.:-,;\"'"))
                        === mb_strtolower(trim($label, " \t.:-,;\"'"));

                    return $this->payload($value, $index, 'label', $bare);
                }
            }
        }

        return null;
    }

    private function labelPattern(string $label): string
    {
        $escaped = preg_quote(str_replace("'", "\x01", $label), '/');
        $escaped = str_replace("\x01", "['\\s\\*]?", $escaped);
        $escaped = str_replace('\\ ', '[ \\t\\\'\\*]+', $escaped);

        return '/(?:^|[^a-z0-9])'.$escaped.'(?![a-z])([ \\t]*)([:.\-]?)([ \\t]*)([^\\n]{0,120})/i';
    }

    private function isContinuationWord(string $value): bool
    {
        return (bool) preg_match(
            '/
            ^(certificate|cert|registration|registered|reg|name|no|number|id|date|issued|issue|
              valid|until|expiration|expiry|business|office|department|address|tax|community|
              license|permit|scope|board|sticker|ntf|notice|and|of|the|for|official|only|use|
              street|road|brgy|barangay|city|municipality|province|health|food)
            \b/ix',
            $value
        );
    }

    private function labelStartPattern(string $label): string
    {
        $escaped = preg_quote($label, '/');

        return '/'.str_replace('\\ ', '[ \\t\\\'\\*]+', $escaped).'/i';
    }

    private function payload(?string $value, int $lineIndex, string $strategy, bool $bare = false): array
    {
        return ['value' => $value, 'line_index' => $lineIndex, 'strategy' => $strategy, 'bare' => $bare];
    }

    private function sanitizeValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        $trimmed = trim($trimmed, " \t.:-,;\"'");

        return $trimmed === '' ? null : $trimmed;
    }

    private function nextLineValue(array $lines, int $index, string $kind, int $minLength = 2): ?string
    {
        $next = $index + 1;

        if (! isset($lines[$next])) {
            return null;
        }

        $candidate = $this->sanitizeValue($lines[$next]);

        if ($candidate === null) {
            return null;
        }

        if ($this->looksLikeLabelLine($candidate, [])) {
            return null;
        }

        if ($kind === 'date') {
            $date = $this->normalizeDateValue($candidate);

            return $date ?? null;
        }

        return mb_strlen($candidate) >= $minLength ? $candidate : null;
    }

    private function matchedNumber(array $lines, array $labels): ?string
    {
        $matched = $this->findLabeledValue($lines, $labels);

        if ($matched !== null && $matched['value'] !== null) {
            return $matched['value'];
        }

        return $this->numberFallback($lines, ['permit', 'license', 'certificate', 'reg', 'registration', 'id']);
    }

    private function numberFallback(array $lines, array $labels): ?string
    {
        foreach ($lines as $line) {
            if (preg_match(
                '/(?:'.implode('|', $labels).')[ \t]*no\.?[ \t]*[:#]?[ \t]*([a-z0-9][a-z0-9\-]{2,40})/i',
                $line,
                $m
            )) {
                return $m[1];
            }
        }

        return null;
    }

    private function findOfficeLine(array $lines): ?string
    {
        foreach ($lines as $line) {
            if (preg_match('/(?:office|bureau|department|field office) of/i', $line)) {
                $value = $this->sanitizeValue($line);

                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function looksLikeLabelLine(string $line, array $skipRegexes): bool
    {
        foreach ($skipRegexes as $regex) {
            if (preg_match($regex, $line)) {
                return true;
            }
        }

        // Colon-separated label/value lines are the strongest structural signal,
        // while dashes/periods are common inside actual values (SARI-SARI, 0917-1234).
        return (bool) preg_match('/^[^:\r\n]{2,40}:\s*\S/i', $line);
    }

    private function looksLikeDecor(string $line): bool
    {
        $trimmed = trim($line);

        if ($trimmed === '') {
            return true;
        }

        // Purely decorative runs (----- , *** , "====" , ".....").
        return (bool) preg_match('/^[\d\-\*\+\=\_#\/\\\\|~.,;:\s]+$/', $trimmed);
    }

    private function similar(string $a, string $b): bool
    {
        return mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }

    /*
     |----------------------------------------------------------------------
     | Confidence helpers
     |----------------------------------------------------------------------
     */

    private function confidenceFor(string $kind, string $value, string $strategy): float
    {
        $base = match ($strategy) {
            'label' => 0.90,
            'fuzzy_label' => 0.82,
            'next_line' => 0.84,
            'date_scan' => 0.86,
            default => 0.80,
        };

        $bonus = match ($kind) {
            'date' => $this->parseDate($value) !== null ? 0.05 : -0.25,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? 0.06 : -0.22,
            'number' => (bool) preg_match('/^[a-z0-9][a-z0-9\- ]{1,60}$/i', trim($value)) ? 0.03 : -0.18,
            'amount' => (bool) preg_match('/^[₱peso]?\s*[\d][\d,.]*\s*(pesos|php|₱)?$/i', $value) ? 0.05 : -0.20,
            'int' => ctype_digit(trim($value)) ? 0.06 : -0.25,
            default => 0,
        };

        $penalty = in_array($kind, ['name', 'text', 'address'], true)
            ? $this->ocrPenalty($value)
            : 0;

        return $this->clamp($base + $bonus - $penalty);
    }

    private function ocrPenalty(string $value): float
    {
        $penalty = 0.0;

        // Digit mixed inside an otherwise alphabetic token, e.g. H0USE, S4NTO5.
        if (preg_match('/\b(?=[a-z]*[0-9])(?=[a-z0-9]*[a-z])[a-z0-9]+\b/i', $value)) {
            $penalty += 0.20;
        }

        // Suspicious OCR artifacts.
        if (preg_match('/[¬¦¯ÐÍÈØØØ]/u', $value)) {
            $penalty += 0.15;
        }

        // Single letters / stray characters suggest a truncated read.
        if (mb_strlen($value) < 4) {
            $penalty += 0.12;
        }

        return $penalty;
    }

    private function clamp(float $confidence): float
    {
        return round(max(0.0, min(1.0, $confidence)), 4);
    }

    private function result(?string $value, float $confidence, string $kind): array
    {
        if ($value === null) {
            return ['value' => null, 'confidence' => 0.0];
        }

        $normalized = match ($kind) {
            'date' => $this->normalizeDateValue($value),
            default => $value,
        };

        if ($normalized === null) {
            return ['value' => null, 'confidence' => 0.0];
        }

        return ['value' => $normalized, 'confidence' => $confidence];
    }

    private function normalizeDateValue(string $value): ?string
    {
        $parsed = $this->parseDate($value);

        return $parsed?->format('Y-m-d');
    }

    private function scanDateWithTriggers(array $lines, array $triggers): ?string
    {
        foreach ($lines as $i => $line) {
            $lower = mb_strtolower($line);

            $hit = true;
            if ($triggers !== []) {
                $hit = false;
                foreach ($triggers as $trigger) {
                    if (str_contains($lower, $trigger)) {
                        $hit = true;
                        break;
                    }
                }
            }

            if ($hit) {
                $date = $this->findDateInLine($line);

                if ($date === null && isset($lines[$i + 1])) {
                    $date = $this->findDateInLine($lines[$i + 1]);
                }

                if ($date !== null) {
                    return $this->normalizeDateValue($date);
                }
            }
        }

        return null;
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

        if (preg_match('/^(\d{1,4})-(\d{1,2})-(\d{2,4})$/', $value, $m)) {
            $first = (int) $m[1];
            $second = (int) $m[2];
            $year = (int) $m[3];

            if (strlen($m[1]) === 4) {
                try {
                    return Carbon::createFromFormat('Y-m-d', $value);
                } catch (Throwable) {
                    return null;
                }
            }

            if ($year < 100) {
                $year += $year > 50 ? 1900 : 2000;
            }

            try {
                // First component 13+ cannot be a month: unambiguous DD-MM-YYYY.
                if ($first > 12) {
                    return Carbon::create($year, $second, $first, 0, 0, 0);
                }

                // Ambiguous MM-DD-YYYY / DD-MM-YYYY: interpret month-first
                // (MM/DD/YYYY is the primary supported format).
                return Carbon::create($year, $first, $second, 0, 0, 0);
            } catch (Throwable) {
                return null;
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

    private function clean(string $text): string
    {
        $value = str_replace(["\r"], '', $text);

        return preg_replace('/[^\x20-\x7E\x{00A0}-\x{00FF}\r\n]/u', '', $value) ?? $value;
    }

    private function lines(string $text): array
    {
        return preg_split('/\R/u', $text) ?: [];
    }
}
