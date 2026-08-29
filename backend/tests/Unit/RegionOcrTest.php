<?php

namespace Tests\Unit;

use App\Services\Ocr\ImagePreprocessor;
use App\Services\Ocr\RegionOcr;
use Tests\TestCase;

class RegionOcrTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }
    }

    public function test_extracts_header_style_regions_from_word_boxes(): void
    {
        $image = $this->writePng();

        // TSV pass reports label + value word boxes; region crops are re-read
        // in label order (surname, given_name, then address).
        $values = ['SANTOS', 'JOSE', '28 PAYAPA ST'];
        $cropCount = 0;

        $runner = function (string $path, array $options) use (&$cropCount, $values): array {
            if (($options['psm'] ?? 0) === 11) {
                return ['text' => '', 'words' => $this->wordBoxes(), 'conf' => 96.0];
            }

            $text = $values[$cropCount] ?? 'UNREADABLE';
            $cropCount++;

            return ['text' => $text, 'words' => [], 'conf' => 90.0];
        };

        $results = (new RegionOcr(new ImagePreprocessor))->extract($image, $runner);

        $this->assertSame('SANTOS', $results['surname']['value']);
        $this->assertSame('JOSE', $results['given_name']['value']);
        $this->assertSame('28 PAYAPA ST', $results['address']['value']);
        $this->assertGreaterThanOrEqual(0.75, $results['surname']['confidence']);
        $this->assertArrayNotHasKey('middle_name', $results);
        $this->assertSame(3, $cropCount);

        @unlink($image);
    }

    public function test_extracts_inline_value_to_the_right_of_label(): void
    {
        $image = $this->writePng();

        // "SEX: FEMALE" reads as two words on the same line.
        $words = array_merge($this->wordBoxes(), [[
            'text' => 'SEX', 'left' => 20, 'top' => 118, 'width' => 30, 'height' => 12, 'conf' => 95,
        ], [
            'text' => 'FEMALE', 'left' => 56, 'top' => 118, 'width' => 60, 'height' => 12, 'conf' => 97,
        ]]);
        usort($words, fn (array $a, array $b) => $a['top'] <=> $b['top']);

        $values = ['SANTOS', 'JOSE', 'FEMALE', '28 PAYAPA ST'];
        $cropCount = 0;

        $runner = function (string $path, array $options) use (&$cropCount, $values, $words): array {
            if (($options['psm'] ?? 0) === 11) {
                return ['text' => '', 'words' => $words, 'conf' => 96.0];
            }

            $text = $values[$cropCount] ?? 'UNREADABLE';
            $cropCount++;

            return ['text' => $text, 'words' => [], 'conf' => 90.0];
        };

        $results = (new RegionOcr(new ImagePreprocessor))->extract($image, $runner);

        $this->assertSame('FEMALE', $results['sex']['value']);

        @unlink($image);
    }

    public function test_returns_empty_when_no_label_words_present(): void
    {
        $image = $this->writePng();

        $runner = fn () => [
            'text' => 'no labels here',
            'words' => [
                ['text' => 'NOISE', 'left' => 5, 'top' => 5, 'width' => 40, 'height' => 10, 'conf' => 50],
            ],
            'conf' => 50.0,
        ];

        $results = (new RegionOcr(new ImagePreprocessor))->extract($image, $runner);

        $this->assertSame([], $results);

        @unlink($image);
    }

    private function wordBoxes(): array
    {
        return [
            ['text' => 'SURNAME', 'left' => 20, 'top' => 20, 'width' => 70, 'height' => 12, 'conf' => 98],
            ['text' => 'SANTOS', 'left' => 20, 'top' => 42, 'width' => 55, 'height' => 12, 'conf' => 99],
            ['text' => 'GIVEN', 'left' => 20, 'top' => 66, 'width' => 45, 'height' => 12, 'conf' => 97],
            ['text' => 'NAME', 'left' => 70, 'top' => 66, 'width' => 45, 'height' => 12, 'conf' => 97],
            ['text' => 'JOSE', 'left' => 20, 'top' => 88, 'width' => 40, 'height' => 12, 'conf' => 95],
            ['text' => 'ADDRESS', 'left' => 20, 'top' => 114, 'width' => 75, 'height' => 12, 'conf' => 96],
            ['text' => '28', 'left' => 20, 'top' => 138, 'width' => 20, 'height' => 12, 'conf' => 90],
            ['text' => 'PAYAPA', 'left' => 44, 'top' => 138, 'width' => 60, 'height' => 12, 'conf' => 90],
            ['text' => 'ST', 'left' => 108, 'top' => 138, 'width' => 25, 'height' => 12, 'conf' => 90],
        ];
    }

    private function writePng(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ocr_region_').'.png';
        $image = imagecreatetruecolor(300, 160);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, 300, 160, $white);
        imagepng($image, $path);

        return $path;
    }
}
