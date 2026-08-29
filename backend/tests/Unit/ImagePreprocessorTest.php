<?php

namespace Tests\Unit;

use App\Services\Ocr\ImagePreprocessor;
use Tests\TestCase;

class ImagePreprocessorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension is not available.');
        }
    }

    public function test_prepare_never_modifies_the_source_image(): void
    {
        $source = $this->writePng(300, 80);
        $hashBefore = hash_file('sha256', $source);

        $versions = (new ImagePreprocessor)->prepare($source, 'image/png');

        $this->assertNotSame([], $versions);
        $this->assertSame($hashBefore, hash_file('sha256', $source));

        foreach ($versions as $version) {
            $this->assertFileExists($version['path']);
            @unlink($version['path']);
        }

        @unlink($source);
    }

    public function test_prepare_upscales_small_images(): void
    {
        $source = $this->writePng(60, 40);

        $versions = (new ImagePreprocessor)->prepare($source, 'image/png', [
            'min_width' => 300,
            'sharpen' => false,
        ]);

        $this->assertNotSame([], $versions);
        $this->assertGreaterThanOrEqual(300, $versions[0]['width']);

        foreach ($versions as $version) {
            @unlink($version['path']);
        }

        @unlink($source);
    }

    public function test_prepare_produces_multiple_ocr_friendly_versions(): void
    {
        $source = $this->writePng(400, 120);

        $versions = (new ImagePreprocessor)->prepare($source, 'image/png', [
            'sharpen' => true,
            'adaptive_threshold' => true,
        ]);

        $kinds = array_column($versions, 'kind');

        $this->assertContains('contrast', $kinds);
        $this->assertContains('threshold', $kinds);

        foreach ($versions as $version) {
            @unlink($version['path']);
        }

        @unlink($source);
    }

    public function test_prepare_returns_empty_when_disabled(): void
    {
        $source = $this->writePng(300, 80);

        $versions = (new ImagePreprocessor)->prepare($source, 'image/png', ['enabled' => false]);

        $this->assertSame([], $versions);

        @unlink($source);
    }

    public function test_crop_region_returns_cropped_image(): void
    {
        $source = $this->writePng(300, 120);

        $crop = (new ImagePreprocessor)->cropRegion($source, 10, 10, 100, 40);

        $this->assertNotNull($crop);
        $this->assertFileExists($crop);

        [$width, $height] = getimagesize($crop);

        $this->assertGreaterThanOrEqual(100, $width);
        $this->assertLessThanOrEqual(140, $height);

        @unlink($crop);
        @unlink($source);
    }

    private function writePng(int $width, int $height): string
    {
        $path = tempnam(sys_get_temp_dir(), 'ocr_test_').'.png';
        $image = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefilledrectangle($image, 0, 0, $width, $height, $white);

        for ($y = 0; $y < $height; $y += 6) {
            imageline($image, 0, $y, $width, $y, $black);
        }

        imagestring($image, 5, max(4, intdiv($width, 3)), intdiv($height, 3), 'TEST', $black);
        imagepng($image, $path);

        return $path;
    }
}
