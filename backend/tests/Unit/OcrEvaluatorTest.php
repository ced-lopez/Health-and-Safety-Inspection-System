<?php

namespace Tests\Unit;

use App\Services\Ocr\OcrEvaluator;
use PHPUnit\Framework\TestCase;

class OcrEvaluatorTest extends TestCase
{
    public function test_cer_is_zero_for_identical_text(): void
    {
        $this->assertSame(0.0, OcrEvaluator::cer('JOSE CRUZ SANTOS', 'JOSE CRUZ SANTOS'));
    }

    public function test_cer_is_one_for_fully_different_text(): void
    {
        $this->assertSame(1.0, OcrEvaluator::cer('ABCDEF', 'GHIJKL'));
    }

    public function test_cer_reflects_partial_corruption(): void
    {
        $cer = OcrEvaluator::cer('SANTOS', 'S4NTO5');

        $this->assertGreaterThan(0.0, $cer);
        $this->assertLessThan(1.0, $cer);
    }

    public function test_character_accuracy_is_one_minus_cer(): void
    {
        $this->assertSame(1.0, OcrEvaluator::characterAccuracy('SANTOS', 'SANTOS'));
    }

    public function test_field_accuracy_counts_correct_fields(): void
    {
        $expected = [
            'surname' => 'SANTOS',
            'given_name' => 'JOSE',
            'date_of_birth' => '1980-01-16',
            'address' => '28 PAYAPA ST BAGONG DIWA',
        ];

        $actual = [
            'surname' => ['value' => 'SANTOS', 'confidence' => 0.96],
            'given_name' => ['value' => 'JOSE', 'confidence' => 0.95],
            'date_of_birth' => ['value' => '1980-01-16', 'confidence' => 0.94],
            'address' => ['value' => '28 PAYAPA ST BAGONG DIWA', 'confidence' => 0.91],
        ];

        $result = OcrEvaluator::fieldAccuracy($expected, $actual);

        $this->assertSame(4, $result['correct']);
        $this->assertSame(4, $result['total']);
        $this->assertSame(100.0, $result['percentage']);
    }

    public function test_field_accuracy_is_case_and_punctuation_insensitive(): void
    {
        $result = OcrEvaluator::fieldAccuracy(
            ['surname' => 'SANTOS'],
            ['surname' => ['value' => ' santos', 'confidence' => 0.9]]
        );

        $this->assertTrue($result['fields']['surname']);
    }

    public function test_classification_accuracy(): void
    {
        $this->assertTrue(OcrEvaluator::classificationAccuracy('government_id', 'government_id'));
        $this->assertFalse(OcrEvaluator::classificationAccuracy('government_id', 'dti_business_registration'));
    }

    public function test_aggregate_rolls_up_metrics(): void
    {
        $results = [
            [
                'type' => 'government_id',
                'cer' => 0.1,
                'field_accuracy' => ['correct' => 3, 'total' => 4, 'percentage' => 75.0],
                'classification_matched' => true,
                'processing_time_ms' => 120.0,
            ],
            [
                'type' => 'government_id',
                'cer' => 0.3,
                'field_accuracy' => ['correct' => 4, 'total' => 4, 'percentage' => 100.0],
                'classification_matched' => false,
                'processing_time_ms' => 200.0,
            ],
        ];

        $aggregate = OcrEvaluator::aggregate($results);

        $this->assertSame(2, $aggregate['documents']);
        $this->assertSame(0.2, $aggregate['avg_cer']);
        $this->assertSame(0.8, $aggregate['avg_character_accuracy']);
        $this->assertSame(7, $aggregate['field_accuracy']['correct']);
        $this->assertSame(8, $aggregate['field_accuracy']['total']);
        $this->assertSame(87.5, $aggregate['field_accuracy']['percentage']);
        $this->assertSame(50.0, $aggregate['classification_accuracy']);
        $this->assertSame(160.0, $aggregate['avg_processing_time_ms']);
        $this->assertArrayHasKey('government_id', $aggregate['by_type']);
    }
}
