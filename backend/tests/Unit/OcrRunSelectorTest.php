<?php

namespace Tests\Unit;

use App\Services\Ocr\DocumentClassifier;
use App\Services\Ocr\DocumentFieldExtractor;
use App\Services\Ocr\DocumentTypes;
use App\Services\Ocr\OcrRunSelector;
use PHPUnit\Framework\TestCase;

class OcrRunSelectorTest extends TestCase
{
    private OcrRunSelector $selector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->selector = new OcrRunSelector(new DocumentClassifier, new DocumentFieldExtractor);
    }

    public function test_prefers_field_rich_output_over_long_garble(): void
    {
        $garble = 'U]?U%^* #$%$%^ %%&*^*& ()()()() +++ __ __ ~~~~ ~~~ !!!! ==== ##### %%%% $$$$ 8888 ----';
        $clean = <<<'TXT'
            REPUBLIC OF THE PHILIPPINES
            UNIFIED MULTIPURPOSE ID
            Surname: SANTOS
            Given Name: JOSE
            Date of Birth: 1980-01-16
            ID No: 1234-5678-9012
            Address: 28 PAYAPA ST, Caloocan City
            TXT;

        $versions = [
            ['path' => '/tmp/contrast.png', 'kind' => 'contrast', 'width' => 300, 'height' => 150],
            ['path' => '/tmp/threshold.png', 'kind' => 'threshold', 'width' => 300, 'height' => 150],
        ];

        $runner = function (string $path, array $options) use ($garble, $clean): array {
            return [
                'text' => str_contains($path, 'threshold') ? $clean : $garble,
                'words' => [],
                'conf' => 90.0,
            ];
        };

        $selected = $this->selector->select(
            $versions,
            DocumentTypes::GOVERNMENT_ID,
            $runner,
            ['psm_modes' => [6, 11, 12], 'max_passes' => 6]
        );

        $this->assertSame($clean, $selected['text']);
        $this->assertSame('threshold', $selected['kind']);
    }

    public function test_scoring_rewards_expected_labels_and_patterns(): void
    {
        $good = $this->selector->score(
            "UNIFIED MULTIPURPOSE ID\nSurname: SANTOS\nGiven Name: JOSE\nDate of Birth: 1980-01-16\nID No: 1234-5678-9012\nAddress: 28 PAYAPA ST",
            DocumentTypes::GOVERNMENT_ID
        );

        $bad = $this->selector->score(
            '%%%% ^^^^ &&&& ~~~~ 12345 67890 ||||||| ///////',
            DocumentTypes::GOVERNMENT_ID
        );

        $this->assertGreaterThan($bad['score'], $good['score']);
        $this->assertGreaterThan(0, $good['breakdown']['label_hits']);
    }

    public function test_respects_max_passes(): void
    {
        $versions = [
            ['path' => '/tmp/one.png', 'kind' => 'contrast', 'width' => 300, 'height' => 150],
            ['path' => '/tmp/two.png', 'kind' => 'threshold', 'width' => 300, 'height' => 150],
            ['path' => '/tmp/three.png', 'kind' => 'sharpened', 'width' => 300, 'height' => 150],
        ];

        $runner = fn (string $path, array $options) => [
            'text' => 'SURNAME'.basename($path),
            'words' => [],
            'conf' => 90.0,
        ];

        $selected = $this->selector->select(
            $versions,
            DocumentTypes::GOVERNMENT_ID,
            $runner,
            ['psm_modes' => [6, 11, 12], 'max_passes' => 2]
        );

        $this->assertLessThanOrEqual(2, $selected['passes']);
    }

    public function test_returns_empty_result_when_all_passes_fail(): void
    {
        $versions = [
            ['path' => '/tmp/one.png', 'kind' => 'contrast', 'width' => 300, 'height' => 150],
        ];

        $runner = fn (string $path, array $options) => ['text' => '', 'words' => [], 'conf' => null];

        $selected = $this->selector->select(
            $versions,
            DocumentTypes::GOVERNMENT_ID,
            $runner,
            ['psm_modes' => [6], 'max_passes' => 3]
        );

        $this->assertSame('', $selected['text']);
    }
}
