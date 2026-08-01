<?php

namespace Tests\Unit;

use App\Services\Ocr\DocumentFieldExtractor;
use PHPUnit\Framework\TestCase;

class DocumentFieldExtractorTest extends TestCase
{
    public function test_extracts_common_permit_fields(): void
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

        $fields = (new DocumentFieldExtractor)->extract($text, 'business_permit');

        $this->assertSame('Sari-Sari Store ni Juan', $fields['business_name']);
        $this->assertSame('Juan Dela Cruz', $fields['owner_name']);
        $this->assertSame('2025-045678', $fields['permit_number']);
        $this->assertSame('2025-01-15', $fields['date_issued']);
        $this->assertSame('2026-01-15', $fields['expiration_date']);
        $this->assertSame('Barangay Government', $fields['issuing_authority']);
    }

    public function test_extract_tolerates_ocr_apostrophe_errors(): void
    {
        $text = 'Owner*s Name: Maria Santos';

        $fields = (new DocumentFieldExtractor)->extract($text, 'business_permit');

        $this->assertSame('Maria Santos', $fields['owner_name']);
    }

    public function test_detect_expiration_when_expired(): void
    {
        $result = (new DocumentFieldExtractor)->detectExpiration('2020-01-01');

        $this->assertTrue($result['is_expired']);
        $this->assertTrue($result['expiration_found']);
        $this->assertNotNull($result['expiration_warning']);
    }

    public function test_detect_expiration_when_valid(): void
    {
        $result = (new DocumentFieldExtractor)->detectExpiration('2999-01-01');

        $this->assertFalse($result['is_expired']);
        $this->assertTrue($result['expiration_found']);
        $this->assertNull($result['expiration_warning']);
    }

    public function test_detect_expiration_when_missing(): void
    {
        $result = (new DocumentFieldExtractor)->detectExpiration(null);

        $this->assertFalse($result['is_expired']);
        $this->assertFalse($result['expiration_found']);
    }
}
