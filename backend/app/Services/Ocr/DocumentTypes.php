<?php

namespace App\Services\Ocr;

/**
 * Canonical OCR document types used by the classifier and extractor,
 * mapped to the legacy document_type values stored in the documents table
 * so requirement matching keeps working without changing existing rules.
 */
final class DocumentTypes
{
    public const GOVERNMENT_ID = 'government_id';

    public const BARANGAY_ID = 'barangay_id';

    public const DTI_BUSINESS_REGISTRATION = 'dti_business_registration';

    public const SEC_REGISTRATION = 'sec_registration';

    public const COMMUNITY_TAX_CERTIFICATE = 'community_tax_certificate';

    public const BUSINESS_LOCATION_PROOF = 'business_location_proof';

    public const HEALTH_CERTIFICATE = 'health_certificate';

    public const APPLICATION_FORM = 'application_form';

    public const VICINITY_MAP = 'vicinity_map';

    public const ESTABLISHMENT_PHOTO = 'establishment_photo';

    public const BUSINESS_PERMIT = 'business_permit';

    public const UNKNOWN = 'unknown';

    public const ALL = [
        self::GOVERNMENT_ID,
        self::BARANGAY_ID,
        self::DTI_BUSINESS_REGISTRATION,
        self::SEC_REGISTRATION,
        self::COMMUNITY_TAX_CERTIFICATE,
        self::BUSINESS_LOCATION_PROOF,
        self::HEALTH_CERTIFICATE,
        self::APPLICATION_FORM,
        self::VICINITY_MAP,
        self::ESTABLISHMENT_PHOTO,
        self::BUSINESS_PERMIT,
        self::UNKNOWN,
    ];

    private const LABELS = [
        self::GOVERNMENT_ID => 'Government-Issued ID',
        self::BARANGAY_ID => 'Barangay ID',
        self::DTI_BUSINESS_REGISTRATION => 'DTI Business Name Registration',
        self::SEC_REGISTRATION => 'SEC Registration / Articles of Incorporation',
        self::COMMUNITY_TAX_CERTIFICATE => 'Community Tax Certificate (Cedula)',
        self::BUSINESS_LOCATION_PROOF => 'Proof of Business/Residency Location',
        self::HEALTH_CERTIFICATE => 'Health Certificate',
        self::APPLICATION_FORM => 'Application Form',
        self::VICINITY_MAP => 'Vicinity Map / Sketch',
        self::ESTABLISHMENT_PHOTO => 'Establishment Photo',
        self::BUSINESS_PERMIT => 'Business Permit',
        self::UNKNOWN => 'Unknown Document',
    ];

    /**
     * Maps a canonical classification type to the document_type value that
     * requirement rules and the documents table already use.
     */
    private const LEGACY_MAP = [
        self::GOVERNMENT_ID => 'government_id',
        self::BARANGAY_ID => 'barangay_id',
        self::DTI_BUSINESS_REGISTRATION => 'dti_registration',
        self::SEC_REGISTRATION => 'sec_registration',
        self::COMMUNITY_TAX_CERTIFICATE => 'cedula',
        self::BUSINESS_LOCATION_PROOF => 'proof_of_location',
        self::HEALTH_CERTIFICATE => 'health_certificate',
        self::APPLICATION_FORM => 'application_form',
        self::VICINITY_MAP => 'vicinity_map',
        self::ESTABLISHMENT_PHOTO => 'establishment_photo',
        self::BUSINESS_PERMIT => 'business_permit',
    ];

    public static function label(string $type): string
    {
        return self::LABELS[$type] ?? 'Unknown Document';
    }

    public static function legacyType(string $type): ?string
    {
        return self::LEGACY_MAP[$type] ?? null;
    }

    /**
     * Maps a legacy document_type value (as stored on the documents table)
     * back to the canonical OCR type, if one exists.
     */
    public static function canonical(string $legacyType): ?string
    {
        return array_search($legacyType, self::LEGACY_MAP, true) ?: null;
    }

    /**
     * Types whose layouts benefit from the specialized government-ID pipeline
     * (multi-stage preprocessing, multi-PSM comparison and region-based OCR).
     */
    public static function isIdType(string $type): bool
    {
        return in_array($type, [self::GOVERNMENT_ID, self::BARANGAY_ID], true);
    }

    public static function isKnown(string $type): bool
    {
        return $type !== self::UNKNOWN && isset(self::LABELS[$type]);
    }
}
