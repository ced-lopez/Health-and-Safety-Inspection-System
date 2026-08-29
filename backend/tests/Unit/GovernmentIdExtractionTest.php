<?php

namespace Tests\Unit;

use App\Services\Ocr\DocumentFieldExtractor;
use App\Services\Ocr\DocumentTypes;
use PHPUnit\Framework\TestCase;

class GovernmentIdExtractionTest extends TestCase
{
    private DocumentFieldExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new DocumentFieldExtractor;
    }

    public function test_extracts_component_name_fields_with_header_layout(): void
    {
        $text = <<<'TXT'
            REPUBLIC OF THE PHILIPPINES
            UNIFIED MULTIPURPOSE ID
            SURNAME
            SANTOS
            GIVEN NAME
            JOSE
            MIDDLE NAME
            CRUZ
            SEX
            M
            DATE OF BIRTH
            01/16/1980
            ID NO
            D01-12B51B0-9
            ADDRESS
            28 PAYAPA ST BAGONG DIWA
            STO. CRISTOBAL CALOOCAN CITY
            METRO MANILA PHILIPPINES 1400
            TXT;

        $fields = $this->extractor->extract($text, DocumentTypes::GOVERNMENT_ID);

        $this->assertSame('SANTOS', $fields['surname']['value']);
        $this->assertSame('JOSE', $fields['given_name']['value']);
        $this->assertSame('CRUZ', $fields['middle_name']['value']);
        $this->assertSame('M', $fields['sex']['value']);
        $this->assertSame('1980-01-16', $fields['date_of_birth']['value']);
        $this->assertSame('D01-12B51B0-9', $fields['id_number']['value']);
        $this->assertStringContainsString('PAYAPA', $fields['address']['value']);
        $this->assertStringContainsString('STO. CRISTOBAL', $fields['address']['value']);
        $this->assertStringContainsString('METRO MANILA', $fields['address']['value']);
    }

    public function test_reconstructs_full_name_from_components(): void
    {
        $text = "SURNAME\nSANTOS\nGIVEN NAME\nJOSE\nMIDDLE NAME\nCRUZ";

        $fields = $this->extractor->extract($text, DocumentTypes::GOVERNMENT_ID);

        $this->assertSame('SANTOS', $fields['surname']['value']);
        $this->assertSame('JOSE', $fields['given_name']['value']);
        $this->assertSame('CRUZ', $fields['middle_name']['value']);
        $this->assertSame('JOSE CRUZ SANTOS', $fields['full_name']['value']);
        $this->assertGreaterThanOrEqual(0.75, $fields['full_name']['confidence']);
    }

    public function test_does_not_reconstruct_full_name_from_uncertain_component(): void
    {
        // The surname contains OCR noise, so its confidence drops below the
        // reconstruction threshold and no full name is invented.
        $text = "SURNAME\nS4NTO5\nGIVEN NAME\nJOSE\nMIDDLE NAME\nCRUZ";

        $fields = $this->extractor->extract($text, DocumentTypes::GOVERNMENT_ID);

        $this->assertNull($fields['full_name']['value']);
        $this->assertSame('JOSE', $fields['given_name']['value']);
        $this->assertSame('CRUZ', $fields['middle_name']['value']);
    }

    public function test_missing_fields_return_null_and_zero_confidence(): void
    {
        $fields = $this->extractor->extract('UNIFIED MULTIPURPOSE ID', DocumentTypes::GOVERNMENT_ID);

        foreach (['surname', 'given_name', 'middle_name', 'sex', 'date_of_birth', 'id_number', 'address'] as $field) {
            $this->assertNull($fields[$field]['value'], "{$field} should be null");
            $this->assertSame(0.0, $fields[$field]['confidence'], "{$field} confidence should be 0");
        }
    }

    public function test_normalizes_common_date_formats(): void
    {
        $cases = [
            '01/16/1980' => '1980-01-16',
            '1980-01-16' => '1980-01-16',
            'January 16, 1980' => '1980-01-16',
            '16 Jan 1980' => '1980-01-16',
            '16-01-1980' => '1980-01-16',
        ];

        foreach ($cases as $raw => $expected) {
            $fields = $this->extractor->extract(
                "Date of Birth: {$raw}",
                DocumentTypes::GOVERNMENT_ID
            );

            $this->assertSame($expected, $fields['date_of_birth']['value'], "for input {$raw}");
        }
    }

    public function test_extracts_sex_and_id_on_inline_labels(): void
    {
        $text = "Sex: FEMALE\nID No: PS-2024-009821\nBirthday: 1993-07-02";

        $fields = $this->extractor->extract($text, DocumentTypes::GOVERNMENT_ID);

        $this->assertSame('FEMALE', $fields['sex']['value']);
        $this->assertSame('PS-2024-009821', $fields['id_number']['value']);
        $this->assertSame('1993-07-02', $fields['date_of_birth']['value']);
    }
}
