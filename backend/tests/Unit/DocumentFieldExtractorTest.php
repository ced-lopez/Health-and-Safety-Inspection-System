<?php

namespace Tests\Unit;

use App\Services\Ocr\DocumentFieldExtractor;
use App\Services\Ocr\DocumentTypes;
use PHPUnit\Framework\TestCase;

class DocumentFieldExtractorTest extends TestCase
{
    private DocumentFieldExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new DocumentFieldExtractor;
    }

    public function test_extracts_government_id_fields(): void
    {
        $text = <<<'TXT'
            REPUBLIC OF THE PHILIPPINES
            UNIFIED MULTIPURPOSE ID
            Full Name: JUAN DELA CRUZ
            ID No: 1234-5678-9012
            Date of Birth: 1990-05-14
            Address: 12 Mango St, Barangay 178, Caloocan City
            Date Issued: 2020-03-10
            Valid Until: 2030-03-10
            TXT;

        $fields = $this->extractor->extract($text, DocumentTypes::GOVERNMENT_ID);

        $this->assertSame('JUAN DELA CRUZ', $fields['full_name']['value']);
        $this->assertSame('1234-5678-9012', $fields['id_number']['value']);
        $this->assertSame('1990-05-14', $fields['date_of_birth']['value']);
        $this->assertStringContainsString('Mango St', $fields['address']['value']);
        $this->assertSame('2020-03-10', $fields['date_issued']['value']);
        $this->assertSame('2030-03-10', $fields['expiration_date']['value']);
        $this->assertGreaterThanOrEqual(0.75, $fields['full_name']['confidence']);
    }

    public function test_extracts_barangay_id_fields(): void
    {
        $text = <<<'TXT'
            BARANGAY ID
            Barangay 178
            City: North Caloocan
            Name: MARIA SANTOS
            ID No: B178-2024-0001
            Date of Birth: 1992-08-21
            Address: 45 Sampaguita St
            Date Issued: 2024-01-15
            Valid Until: 2027-01-15
            TXT;

        $fields = $this->extractor->extract($text, DocumentTypes::BARANGAY_ID);

        $this->assertSame('178', $fields['barangay_name']['value']);
        $this->assertSame('North Caloocan', $fields['city']['value']);
        $this->assertSame('MARIA SANTOS', $fields['full_name']['value']);
        $this->assertSame('B178-2024-0001', $fields['id_number']['value']);
        $this->assertSame('1992-08-21', $fields['date_of_birth']['value']);
        $this->assertSame('2027-01-15', $fields['expiration_date']['value']);
    }

    public function test_extracts_dti_business_registration_fields(): void
    {
        $text = <<<'TXT'
            DEPARTMENT OF TRADE AND INDUSTRY
            CERTIFICATE OF BUSINESS NAME REGISTRATION
            Business Name: ABC SARI-SARI STORE
            Owner's Name: JUAN DELA CRUZ
            Business Name Registration No: BN-2026-001234
            Date of Registration: 2026-01-10
            Valid Until: 2029-01-10
            Business Address: 12 Mango St, Barangay 178
            Territorial Scope: Caloocan City
            TXT;

        $fields = $this->extractor->extract($text, DocumentTypes::DTI_BUSINESS_REGISTRATION);

        $this->assertSame('ABC SARI-SARI STORE', $fields['business_name']['value']);
        $this->assertSame('JUAN DELA CRUZ', $fields['owner_name']['value']);
        $this->assertSame('BN-2026-001234', $fields['business_name_registration_number']['value']);
        $this->assertSame('2026-01-10', $fields['registration_date']['value']);
        $this->assertSame('2029-01-10', $fields['expiration_date']['value']);
        $this->assertStringContainsString('Mango St', $fields['business_address']['value']);
        $this->assertSame('Caloocan City', $fields['territorial_scope']['value']);
        $this->assertSame('Department of Trade and Industry', $fields['issuing_authority']['value']);
    }

    public function test_extracts_community_tax_certificate_fields(): void
    {
        $text = <<<'TXT'
            COMMUNITY TAX CERTIFICATE
            CTC No: 5678901
            Name: JUAN DELA CRUZ
            Address: 12 Mango St, Caloocan City
            TIN: 123-456-789
            Date of Birth: 1990-05-14
            Place of Issue: Caloocan City
            Date Issued: 2026-01-15
            Occupation: Sari-Sari Store Owner
            Basic Community Tax: 5.00
            Additional Community Tax: 25.00
            Total Community Tax: 30.00
            TXT;

        $fields = $this->extractor->extract($text, DocumentTypes::COMMUNITY_TAX_CERTIFICATE);

        $this->assertSame('5678901', $fields['certificate_number']['value']);
        $this->assertSame('JUAN DELA CRUZ', $fields['full_name']['value']);
        $this->assertSame('123-456-789', $fields['tin']['value']);
        $this->assertSame('1990-05-14', $fields['date_of_birth']['value']);
        $this->assertSame('Caloocan City', $fields['place_of_issue']['value']);
        $this->assertSame('2026-01-15', $fields['date_issued']['value']);
        $this->assertSame('Sari-Sari Store Owner', $fields['occupation']['value']);
        $this->assertSame('30.00', $fields['total_community_tax']['value']);
    }

    public function test_extracts_business_location_proof_fields(): void
    {
        $text = <<<'TXT'
            CONTRACT OF LEASE
            Property Owner: PEDRO REYES
            Lessee: JUAN DELA CRUZ
            Business Address: Unit 2, 12 Mango St
            Property Address: Lot 7, Block 5, Barangay 178
            Contract No: CL-2026-045
            Start Date: 2026-02-01
            Expiration Date: 2027-02-01
            Issued by: Barangay 178 Office
            TXT;

        $fields = $this->extractor->extract($text, DocumentTypes::BUSINESS_LOCATION_PROOF);

        $this->assertSame('Contract of Lease', $fields['document_type']['value']);
        $this->assertSame('PEDRO REYES', $fields['property_owner_name']['value']);
        $this->assertSame('JUAN DELA CRUZ', $fields['applicant_or_lessee_name']['value']);
        $this->assertSame('CL-2026-045', $fields['contract_number']['value']);
        $this->assertSame('2026-02-01', $fields['start_date']['value']);
        $this->assertSame('2027-02-01', $fields['expiration_date']['value']);
        $this->assertSame('Barangay Government', $fields['issuing_authority']['value']);
    }

    public function test_extracts_health_certificate_fields(): void
    {
        $text = <<<'TXT'
            CITY HEALTH OFFICE
            HEALTH CERTIFICATE
            Certificate No: HC-2026-0001
            Holder Name: JUAN DELA CRUZ
            Establishment Name: ABC SARI-SARI STORE
            Date Issued: 2026-03-01
            Expiration Date: 2027-03-01
            Attending Physician: DR. MARIA SANTOS
            TXT;

        $fields = $this->extractor->extract($text, DocumentTypes::HEALTH_CERTIFICATE);

        $this->assertSame('HC-2026-0001', $fields['certificate_number']['value']);
        $this->assertSame('JUAN DELA CRUZ', $fields['holder_name']['value']);
        $this->assertSame('ABC SARI-SARI STORE', $fields['establishment_name']['value']);
        $this->assertSame('2026-03-01', $fields['issue_date']['value']);
        $this->assertSame('2027-03-01', $fields['expiration_date']['value']);
        $this->assertSame('DR. MARIA SANTOS', $fields['physician_or_authorized_officer']['value']);
    }

    public function test_extracts_application_form_fields(): void
    {
        $text = <<<'TXT'
            APPLICATION FORM
            Applicant Name: JUAN DELA CRUZ
            Age: 34
            Address: 12 Mango St, Barangay 178
            Contact Number: 0917-123-4567
            Email: juan.delacruz@example.com
            Business Name: ABC SARI-SARI STORE
            Business Type: Retail
            Nature of Business: Sari-sari store
            Business Address: 12 Mango St, Barangay 178
            Ownership Type: Sole Proprietorship
            Date of Application: 2026-07-01
            TXT;

        $fields = $this->extractor->extract($text, DocumentTypes::APPLICATION_FORM);

        $this->assertSame('JUAN DELA CRUZ', $fields['applicant_name']['value']);
        $this->assertSame('34', $fields['age']['value']);
        $this->assertSame('0917-123-4567', $fields['contact_number']['value']);
        $this->assertSame('juan.delacruz@example.com', $fields['email']['value']);
        $this->assertSame('ABC SARI-SARI STORE', $fields['business_name']['value']);
        $this->assertSame('Sole Proprietorship', $fields['ownership_type']['value']);
        $this->assertSame('2026-07-01', $fields['date_of_application']['value']);
        $this->assertGreaterThanOrEqual(0.9, $fields['email']['confidence']);
    }

    public function test_extracts_sec_registration_fields(): void
    {
        $text = <<<'TXT'
            SECURITIES AND EXCHANGE COMMISSION
            CERTIFICATE OF INCORPORATION
            Corporate Name: ABC FOODS CORPORATION
            Registration No: CS2026123456
            Date of Registration: 2026-04-20
            Corporation Type: Stock Corporation
            Principal Office Address: 88 Roxas Blvd, Manila
            Authorized Capital: PHP 1,000,000.00
            Subscribed Capital: PHP 500,000.00
            Paid Up Capital: PHP 125,000.00
            TXT;

        $fields = $this->extractor->extract($text, DocumentTypes::SEC_REGISTRATION);

        $this->assertSame('ABC FOODS CORPORATION', $fields['corporate_name']['value']);
        $this->assertSame('CS2026123456', $fields['registration_number']['value']);
        $this->assertSame('2026-04-20', $fields['registration_date']['value']);
        $this->assertStringContainsString('Roxas Blvd', $fields['principal_office_address']['value']);
        $this->assertSame('PHP 1,000,000.00', $fields['authorized_capital']['value']);
    }

    public function test_extracts_vicinity_map_labels(): void
    {
        $text = <<<'TXT'
            VICINITY MAP
            BUSINESS NAME
            ABC SARI-SARI STORE
            STREET
            Mango St
            BARANGAY
            Barangay 178
            TXT;

        $fields = $this->extractor->extract($text, DocumentTypes::VICINITY_MAP);

        $this->assertSame('ABC SARI-SARI STORE', $fields['business_name']['value']);
        $this->assertStringContainsString('Mango St', $fields['street_name']['value']);
    }

    public function test_extracts_establishment_photo_text(): void
    {
        $text = <<<'TXT'
            ABC SARI-SARI STORE
            12 Mango St
            Barangay 178
            Contact: 0917-123-4567
            TXT;

        $fields = $this->extractor->extract($text, DocumentTypes::ESTABLISHMENT_PHOTO);

        $this->assertStringContainsString('0917-123-4567', $fields['visible_contact_number']['value']);
        $this->assertNotEmpty($fields['other_relevant_text']['value']);
    }

    public function test_extracts_legacy_business_permit_fields(): void
    {
        $text = <<<'TXT'
            CITY OF CALOOCAN
            BUSINESS PERMIT
            Business Name: Sari-Sari Store ni Juan
            Owner's Name: Juan Dela Cruz
            Permit No: 2025-045678
            Date Issued: 2025-01-15
            Valid Until: 2026-01-15
            Issued by the Office of the Barangay Captain
            TXT;

        $fields = $this->extractor->extract($text, DocumentTypes::BUSINESS_PERMIT);

        $this->assertSame('Sari-Sari Store ni Juan', $fields['business_name']['value']);
        $this->assertSame('Juan Dela Cruz', $fields['owner_name']['value']);
        $this->assertSame('2025-045678', $fields['permit_number']['value']);
        $this->assertSame('2025-01-15', $fields['date_issued']['value']);
        $this->assertSame('2026-01-15', $fields['expiration_date']['value']);
    }

    public function test_tolerates_ocr_apostrophe_errors(): void
    {
        $text = "Owner*s Name: Maria Santos\nBusiness Name: Sample Store";

        $fields = $this->extractor->extract($text, DocumentTypes::BUSINESS_PERMIT);

        $this->assertSame('Maria Santos', $fields['owner_name']['value']);
    }

    public function test_returns_null_for_missing_fields(): void
    {
        $fields = $this->extractor->extract("BUSINESS PERMIT\nPermit No: 2025-0001", DocumentTypes::BUSINESS_PERMIT);

        $this->assertNull($fields['business_name']['value']);
        $this->assertNull($fields['owner_name']['value']);
        $this->assertSame(0.0, $fields['business_name']['confidence']);
    }

    public function test_normalizes_dates_to_iso(): void
    {
        $fields = $this->extractor->extract(
            "Date Issued: 2026/01/05\nValid Until: January 15, 2027",
            DocumentTypes::BUSINESS_PERMIT
        );

        $this->assertSame('2026-01-05', $fields['date_issued']['value']);
        $this->assertSame('2027-01-15', $fields['expiration_date']['value']);
    }

    public function test_preserves_registration_number_format(): void
    {
        $fields = $this->extractor->extract(
            'Registration No: BN-2026-001234',
            DocumentTypes::DTI_BUSINESS_REGISTRATION
        );

        $this->assertSame('BN-2026-001234', $fields['business_name_registration_number']['value']);
    }

    public function test_flags_low_confidence_ocr_noise(): void
    {
        $fields = $this->extractor->extract(
            "Business Name: ABC FOOD H0USE\nOwner's Name: Juan Dela Cruz",
            DocumentTypes::BUSINESS_PERMIT
        );

        $this->assertSame('ABC FOOD H0USE', $fields['business_name']['value']);
        $this->assertLessThan(0.75, $fields['business_name']['confidence']);

        $flagged = $this->extractor->lowConfidenceFields($fields);
        $flaggedNames = array_column($flagged, 'field');
        $this->assertContains('business_name', $flaggedNames);
        $this->assertNotContains('owner_name', $flaggedNames);
    }

    public function test_high_confidence_fields_are_not_flagged(): void
    {
        $fields = $this->extractor->extract(
            "Business Name: ABC SARI-SARI STORE\nOwner's Name: JUAN DELA CRUZ",
            DocumentTypes::BUSINESS_PERMIT
        );

        $this->assertGreaterThanOrEqual(0.75, $fields['business_name']['confidence']);
        $this->assertSame([], $this->extractor->lowConfidenceFields($fields));
    }

    public function test_detect_expiration_when_expired(): void
    {
        $result = $this->extractor->detectExpiration('2020-01-01');

        $this->assertTrue($result['is_expired']);
        $this->assertTrue($result['expiration_found']);
        $this->assertNotNull($result['expiration_warning']);
    }

    public function test_detect_expiration_when_valid(): void
    {
        $result = $this->extractor->detectExpiration('2999-01-01');

        $this->assertFalse($result['is_expired']);
        $this->assertTrue($result['expiration_found']);
        $this->assertNull($result['expiration_warning']);
    }

    public function test_detect_expiration_when_missing(): void
    {
        $result = $this->extractor->detectExpiration(null);

        $this->assertNull($result['is_expired']);
        $this->assertFalse($result['expiration_found']);
    }
}
