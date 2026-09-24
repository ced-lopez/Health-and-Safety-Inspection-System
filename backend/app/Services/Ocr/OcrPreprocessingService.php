<?php

namespace App\Services\Ocr;

use GdImage;
use Throwable;

/**
 * Lightweight preprocessing before Tesseract, per Task 7.1.
 *
 * Intended as a simple, low-risk step: greyscale + contrast + sharpen + upscale.
 * The uploaded original is never modified; output is written to a temp file.
 * If intervention/image is available it could be used, but GD is used here
 * to avoid adding a new dependency (intervention/image not in composer.json).
 */
class OcrPreprocessingService
{
    public function prepare(string $inputPath, string $outputPath): string
    {
        if (!extension_loaded('gd') || !is_file($inputPath)) {
            return $inputPath;
        }

        try {
            $image = $this->load($inputPath);

            if (!$image) {
                return $inputPath;
            }

            // Greyscale
            imagefilter($image, IMG_FILTER_GRAYSCALE);
            // Increase contrast ~25 (GD contrast 0-100, negative = more contrast)
            imagefilter($image, IMG_FILTER_CONTRAST, -25);
            // Sharpen slightly ~8 is not directly mappable to GD, use convolution
            if (function_exists('imageconvolution')) {
                $copy = $this->copy($image);
                // Sharpen kernel, strength ~8 maps to moderate sharpen
                imageconvolution($copy, [[0, -1, 0], [-1, 5, -1], [0, -1, 0]], 1, 0);
                $image = $copy;
            }

            // Resize up if width < 1500 (Tesseract better on higher-res)
            $width = imagesx($image);
            $height = imagesy($image);

            if ($width > 0 && $width < 1500) {
                $newWidth = 1500;
                $newHeight = (int) round($height * (1500 / $width));
                $resized = imagecreatetruecolor($newWidth, $newHeight);
                imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                $image = $resized;
            }

            imagepng($image, $outputPath, 9);

            return $outputPath;
        } catch (Throwable) {
            return $inputPath;
        }
    }

    private function load(string $path): ?GdImage
    {
        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: '') : '';

        $image = match (strtolower((string) $mime)) {
            'image/jpeg', 'image/jpg', 'image/pjpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            default => @imagecreatefromstring((string) file_get_contents($path)),
        };

        return $image instanceof GdImage ? $image : null;
    }

    private function copy(GdImage $image): GdImage
    {
        $copy = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagecopy($copy, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $copy;
    }
}
