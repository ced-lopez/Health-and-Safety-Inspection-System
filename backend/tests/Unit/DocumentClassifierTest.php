<?php

namespace Tests\Unit;

use App\Services\Ocr\DocumentClassifier;
use App\Services\Ocr\DocumentTypes;
use PHPUnit\Framework\TestCase;

class DocumentClassifierTest extends TestCase
{
    private DocumentClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->classifier = new DocumentClassifier;
    }

    public function test_classifies_government_id(): void
    {
        $result = $this->classifier->classify(
            "REPUBLIC OF THE PHILIPPINES\nUNIFIED MULTIPURPOSE ID\nDate of Birth: 1990-05-14\nBlood Type: O+"
        );

        $this->assertSame(DocumentTypes::GOVERNMENT_ID, $result['type']);
        $this->assertGreaterThanOrEqual(0.7, $result['confidence']);
    }

    public function test_classifies_barangay_id(): void
    {
        $result = $this->classifier->classify(
            "BARANGAY ID\nSangguniang Barangay\nBarangay 178, North Caloocan City"
        );

        $this->assertSame(DocumentTypes::BARANGAY_ID, $result['type']);
        $this->assertGreaterThanOrEqual(0.7, $result['confidence']);
    }

    public function test_classifies_dti_registration(): void
    {
        $result = $this->classifier->classify(
            "Department of Trade and Industry\nCERTIFICATE OF BUSINESS NAME REGISTRATION\nBusiness Name: ABC Sari-Sari Store"
        );

        $this->assertSame(DocumentTypes::DTI_BUSINESS_REGISTRATION, $result['type']);
    }

    public function test_classifies_sec_registration(): void
    {
        $result = $this->classifier->classify(
            "SECURITIES AND EXCHANGE COMMISSION\nCERTIFICATE OF INCORPORATION\nCorporate Name: ABC Corporation\nAuthorized Capital Stock: PHP 1,000,000"
        );

        $this->assertSame(DocumentTypes::SEC_REGISTRATION, $result['type']);
    }

    public function test_classifies_community_tax_certificate(): void
    {
        $result = $this->classifier->classify(
            "REPUBLIC OF THE PHILIPPINES\nCOMMUNITY TAX CERTIFICATE\nCTC No: 5678901\nBasic Community Tax: 5.00\nTotal Community Tax: 40.00"
        );

        $this->assertSame(DocumentTypes::COMMUNITY_TAX_CERTIFICATE, $result['type']);
    }

    public function test_classifies_health_certificate(): void
    {
        $result = $this->classifier->classify(
            "CITY HEALTH OFFICE\nHEALTH CERTIFICATE\nCertificate No: HC-2026-001\nAttending Physician: Dr. Maria Santos"
        );

        $this->assertSame(DocumentTypes::HEALTH_CERTIFICATE, $result['type']);
    }

    public function test_classifies_business_location_proof(): void
    {
        $result = $this->classifier->classify(
            "CONTRACT OF LEASE\nProperty Owner: Pedro Reyes\nLessee: Juan Dela Cruz\nAddress: Blk 5 Lot 7, Barangay 178"
        );

        $this->assertSame(DocumentTypes::BUSINESS_LOCATION_PROOF, $result['type']);
    }

    public function test_classifies_application_form(): void
    {
        $result = $this->classifier->classify(
            "APPLICATION FORM\nApplication for Health and Safety Inspection\nFor Official Use Only\nApplicant Name: Juan Dela Cruz\nBusiness Name: ABC Store"
        );

        $this->assertSame(DocumentTypes::APPLICATION_FORM, $result['type']);
    }

    public function test_classifies_vicinity_map(): void
    {
        $result = $this->classifier->classify(
            "VICINITY MAP\nNorth Arrow\nBarangay 178\nScale: 1:500"
        );

        $this->assertSame(DocumentTypes::VICINITY_MAP, $result['type']);
    }

    public function test_classifies_establishment_photo(): void
    {
        $result = $this->classifier->classify(
            "PHOTOS OF THE ESTABLISHMENT\nABC Sari-Sari Store\nFront View"
        );

        $this->assertSame(DocumentTypes::ESTABLISHMENT_PHOTO, $result['type']);
    }

    public function test_classifies_business_permit(): void
    {
        $result = $this->classifier->classify(
            "CITY OF CALOOCAN\nBUSINESS PERMIT\nPermit No: 2025-000123"
        );

        $this->assertSame(DocumentTypes::BUSINESS_PERMIT, $result['type']);
        $this->assertGreaterThanOrEqual(0.7, $result['confidence']);
    }

    public function test_does_not_classify_on_a_single_keyword(): void
    {
        // Only one weak signal ("voter") — must NOT be classified as a government ID.
        $result = $this->classifier->classify('VOTER');

        $this->assertSame(DocumentTypes::UNKNOWN, $result['type']);
    }

    public function test_returns_unknown_for_unrelated_text(): void
    {
        $result = $this->classifier->classify('random scribble text nothing relevant here at all');

        $this->assertSame(DocumentTypes::UNKNOWN, $result['type']);
    }

    public function test_returns_unknown_for_empty_text(): void
    {
        $result = $this->classifier->classify('');

        $this->assertSame(DocumentTypes::UNKNOWN, $result['type']);
        $this->assertSame(0.0, $result['confidence']);
    }

    public function test_confidence_is_0_to_1_scale(): void
    {
        $result = $this->classifier->classify(
            "DEPARTMENT OF TRADE AND INDUSTRY\nCERTIFICATE OF BUSINESS NAME REGISTRATION\nBusiness Name: ABC Trading"
        );

        $this->assertGreaterThanOrEqual(0.0, $result['confidence']);
        $this->assertLessThanOrEqual(1.0, $result['confidence']);
    }
}
