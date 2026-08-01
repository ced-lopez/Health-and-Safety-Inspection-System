<?php

namespace Tests\Unit;

use App\Services\Ocr\DocumentClassifier;
use PHPUnit\Framework\TestCase;

class DocumentClassifierTest extends TestCase
{
    public function test_classifies_business_permit_text(): void
    {
        $result = (new DocumentClassifier)->classify(
            "CITY OF CALOOCAN\nBUSINESS PERMIT\nPermit No: 2025-000123"
        );

        $this->assertSame('business_permit', $result['type']);
        $this->assertGreaterThanOrEqual(55, $result['confidence']);
    }

    public function test_classifies_barangay_id_text(): void
    {
        $result = (new DocumentClassifier)->classify(
            "BARANGAY ID\nBarangay 178, North Caloocan City"
        );

        $this->assertSame('barangay_id', $result['type']);
    }

    public function test_classifies_business_registration_text(): void
    {
        $result = (new DocumentClassifier)->classify(
            "DTI CERTIFICATE OF BUSINESS REGISTRATION\nBusiness Name: ABC Trading"
        );

        $this->assertSame('business_registration', $result['type']);
    }

    public function test_returns_generic_for_unknown_text(): void
    {
        $result = (new DocumentClassifier)->classify('random scribble text nothing relevant');

        $this->assertSame('generic', $result['type']);
    }

    public function test_low_confidence_for_unknown_text(): void
    {
        $result = (new DocumentClassifier)->classify('random text');

        $this->assertLessThanOrEqual(35, $result['confidence']);
    }
}
