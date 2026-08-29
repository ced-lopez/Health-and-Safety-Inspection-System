<?php

namespace App\Services\Ocr;

use GdImage;
use Throwable;

/**
 * GD-based image preprocessing pipeline for OCR.
 *
 * The uploaded original is NEVER modified: every transformation writes to a
 * separate temporary file and the caller is responsible for cleanup.
 *
 * Produces one or more "OCR-friendly" versions (contrast-enhanced, adaptive-
 * threshold binarized, sharpened) so the multi-pass OCR selector can compare
 * results across them instead of relying on a single preprocessing recipe.
 */
class ImagePreprocessor
{
    /**
     * @return array<int, array{path: string, kind: string, width: int, height: int}>
     */
    public function prepare(string $sourcePath, ?string $mimeType, array $overrides = []): array
    {
        if (! extension_loaded('gd')) {
            return [];
        }

        $options = array_merge(config('ocr.preprocess', []), $overrides);

        if (! ($options['enabled'] ?? true)) {
            return [];
        }

        $image = $this->load($sourcePath, $mimeType);

        if (! $image) {
            return [];
        }

        if ($options['auto_orient'] ?? true) {
            $image = $this->autoOrient($image, $sourcePath, $mimeType);
        }

        $image = $this->normalizeSize($image, $options);
        $versions = [];

        if ($options['grayscale'] ?? true) {
            $this->toGrayscale($image);
        }

        if ($options['contrast'] ?? true) {
            $this->improveContrast($image);
        }

        $base = $this->write($image, 'contrast');
        $versions[] = $base;

        $denoised = $options['denoise'] ?? true
            ? $this->denoise($image)
            : $image;

        if ($options['adaptive_threshold'] ?? true) {
            $threshold = $this->adaptiveThreshold(
                $denoised,
                (int) ($options['threshold_window'] ?? 21),
                (int) ($options['threshold_offset'] ?? 8)
            );
            $versions[] = $this->write($threshold, 'threshold');
        }

        if ($options['sharpen'] ?? true) {
            $sharpened = $this->sharpen($denoised);
            if ($sharpened !== null) {
                $versions[] = $this->write($sharpened, 'sharpened');
            }
        }

        return $versions;
    }

    public function cropRegion(string $sourcePath, int $left, int $top, int $width, int $height, int $padding = 0): ?string
    {
        $image = $this->load($sourcePath);

        if (! $image) {
            return null;
        }

        $left = max(0, $left - $padding);
        $top = max(0, $top - $padding);
        $right = min(imagesx($image), $left + $width + $padding);
        $bottom = min(imagesy($image), $top + $height + $padding);

        $crop = imagecrop($image, [
            'x' => $left,
            'y' => $top,
            'width' => max(1, $right - $left),
            'height' => max(1, $bottom - $top),
        ]);

        if (! $crop) {
            return null;
        }

        $written = $this->write($crop, 'region');

        return $written['path'];
    }

    private function load(string $path, ?string $mimeType = null): ?GdImage
    {
        if (! is_file($path)) {
            return null;
        }

        $mime = $mimeType ?: (function_exists('mime_content_type') ? (mime_content_type($path) ?: '') : '');

        $image = match (strtolower((string) $mime)) {
            'image/jpeg', 'image/jpg', 'image/pjpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/gif' => @imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'image/bmp' => function_exists('imagecreatefrombmp') ? @imagecreatefrombmp($path) : false,
            default => @imagecreatefromstring((string) file_get_contents($path)),
        };

        return $image instanceof GdImage ? $image : null;
    }

    private function autoOrient(GdImage $image, string $path, ?string $mimeType): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $mime = strtolower((string) ($mimeType ?: ''));

        if (! in_array($mime, ['image/jpeg', 'image/jpg', 'image/pjpeg'], true)) {
            return $image;
        }

        try {
            $exif = @exif_read_data($path);

            if ($exif === false || ! isset($exif['Orientation'])) {
                return $image;
            }

            return match ((int) $exif['Orientation']) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                2 => $this->flipHorizontal($image),
                5 => $this->flipVertical(imagerotate($image, -90, 0)),
                7 => $this->flipHorizontal(imagerotate($image, -90, 0)),
                default => $image,
            };
        } catch (Throwable) {
            return $image;
        }
    }

    private function flipHorizontal(GdImage $image): GdImage
    {
        if (function_exists('imageflip')) {
            $copy = $this->copy($image);
            imageflip($copy, IMG_FLIP_HORIZONTAL);

            return $copy;
        }

        return $image;
    }

    private function flipVertical(GdImage $image): GdImage
    {
        if (function_exists('imageflip')) {
            $copy = $this->copy($image);
            imageflip($copy, IMG_FLIP_VERTICAL);

            return $copy;
        }

        return $image;
    }

    private function copy(GdImage $image): GdImage
    {
        $copy = imagecreatetruecolor(imagesx($image), imagesy($image));
        imagecopy($copy, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        return $copy;
    }

    private function normalizeSize(GdImage $image, array $options): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $minWidth = (int) ($options['min_width'] ?? 1200);
        $maxDimension = (int) ($options['max_dimension'] ?? 4000);

        $scale = 1.0;

        if (($options['upscale'] ?? true) && $width > 0 && $width < $minWidth) {
            $scale = $minWidth / $width;
        }

        $targetMax = max($width, $height) * $scale;

        if ($targetMax > $maxDimension) {
            $scale = $maxDimension / max($width, $height);
        }

        if (abs($scale - 1.0) < 0.001) {
            return $image;
        }

        $newWidth = (int) round($width * $scale);
        $newHeight = (int) round($height * $scale);
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $resized;
    }

    private function toGrayscale(GdImage $image): void
    {
        if (function_exists('imagefilter')) {
            imagefilter($image, IMG_FILTER_GRAYSCALE);
        }
    }

    private function improveContrast(GdImage $image): void
    {
        if (! function_exists('imagefilter')) {
            return;
        }

        // Normalize the luminance range so faint text over a patterned
        // background separates from the background.
        [$min, $max] = $this->luminanceRange($image);
        $spread = $max - $min;

        if ($spread < 30) {
            // Very flat image: widen the range directly.
            imagefilter($image, IMG_FILTER_CONTRAST, -40);
            imagefilter($image, IMG_FILTER_BRIGHTNESS, 10);

            return;
        }

        $scale = 255.0 / $spread;
        $shift = -$min * $scale;

        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $lum = (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
                $stretched = (int) min(255, max(0, $lum * $scale + $shift));
                $color = imagecolorallocate($image, $stretched, $stretched, $stretched);
                imagesetpixel($image, $x, $y, $color);
                imagecolordeallocate($image, $color);
            }
        }
    }

    private function luminanceRange(GdImage $image): array
    {
        $min = 255;
        $max = 0;
        $width = imagesx($image);
        $height = imagesy($image);

        for ($y = 0; $y < $height; $y += 2) {
            for ($x = 0; $x < $width; $x += 2) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $lum = (int) round(0.299 * $r + 0.587 * $g + 0.114 * $b);
                $min = min($min, $lum);
                $max = max($max, $lum);
            }
        }

        return [$min, $max];
    }

    /**
     * Light 3x3 median filter to remove salt-and-pepper noise without blurring
     * text edges. Only applied on the grayscale image.
     */
    private function denoise(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $output = imagecreatetruecolor($width, $height);

        $offsets = [
            [-1, -1], [0, -1], [1, -1],
            [-1, 0], [0, 0], [1, 0],
            [-1, 1], [0, 1], [1, 1],
        ];

        for ($y = 1; $y < $height - 1; $y++) {
            for ($x = 1; $x < $width - 1; $x++) {
                $values = [];
                foreach ($offsets as [$dx, $dy]) {
                    $pixel = imagecolorat($image, $x + $dx, $y + $dy);
                    $values[] = ($pixel >> 16) & 0xFF;
                }
                sort($values, SORT_NUMERIC);
                $median = $values[4];
                $color = imagecolorallocate($output, $median, $median, $median);
                imagesetpixel($output, $x, $y, $color);
                imagecolordeallocate($output, $color);
            }
        }

        // Copy unmodified borders.
        for ($x = 0; $x < $width; $x++) {
            imagesetpixel($output, $x, 0, imagecolorat($image, $x, 0));
            imagesetpixel($output, $x, $height - 1, imagecolorat($image, $x, $height - 1));
        }
        for ($y = 0; $y < $height; $y++) {
            imagesetpixel($output, 0, $y, imagecolorat($image, 0, $y));
            imagesetpixel($output, $width - 1, $y, imagecolorat($image, $width - 1, $y));
        }

        return $output;
    }

    /**
     * Local-mean adaptive thresholding (Sauvola-style, mean-only). Keeps text
     * readable even when the security/background pattern varies across the ID.
     */
    private function adaptiveThreshold(GdImage $image, int $window = 21, int $offset = 8): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        $integral = array_fill(0, ($height + 1) * ($width + 1), 0);

        for ($y = 1; $y <= $height; $y++) {
            $rowSum = 0;
            for ($x = 1; $x <= $width; $x++) {
                $pixel = imagecolorat($image, $x - 1, $y - 1);
                $lum = ($pixel >> 16) & 0xFF;
                $rowSum += $lum;
                $integral[$y * ($width + 1) + $x] = $integral[($y - 1) * ($width + 1) + $x] + $rowSum;
            }
        }

        $half = intdiv($window, 2);
        $output = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            $y1 = max(0, $y - $half);
            $y2 = min($height - 1, $y + $half);

            for ($x = 0; $x < $width; $x++) {
                $x1 = max(0, $x - $half);
                $x2 = min($width - 1, $x + $half);

                $count = ($x2 - $x1 + 1) * ($y2 - $y1 + 1);
                $sum = $this->integralSum($integral, $width + 1, $x1, $y1, $x2, $y2);
                $mean = $sum / $count;

                $pixel = imagecolorat($image, $x, $y);
                $lum = ($pixel >> 16) & 0xFF;

                $value = $lum < ($mean - $offset) ? 0 : 255;
                $color = imagecolorallocate($output, $value, $value, $value);
                imagesetpixel($output, $x, $y, $color);
                imagecolordeallocate($output, $color);
            }
        }

        return $output;
    }

    private function integralSum(array $integral, int $stride, int $x1, int $y1, int $x2, int $y2): int
    {
        return $integral[($y2 + 1) * $stride + ($x2 + 1)]
            - $integral[$y1 * $stride + ($x2 + 1)]
            - $integral[($y2 + 1) * $stride + $x1]
            + $integral[$y1 * $stride + $x1];
    }

    private function sharpen(GdImage $image): ?GdImage
    {
        if (! function_exists('imageconvolution')) {
            return null;
        }

        $copy = $this->copy($image);

        if (! imageconvolution($copy, [[0, -1, 0], [-1, 5, -1], [0, -1, 0]], 1, 0)) {
            return null;
        }

        return $copy;
    }

    private function write(GdImage $image, string $kind): array
    {
        $temp = tempnam(sys_get_temp_dir(), 'ocr_img_').'.png';
        imagepng($image, $temp);

        return [
            'path' => $temp,
            'kind' => $kind,
            'width' => imagesx($image),
            'height' => imagesy($image),
        ];
    }
}
