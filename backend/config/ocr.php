<?php

return [
    'tesseract_binary' => env('TESSERACT_BINARY', PHP_OS_FAMILY === 'Windows' ? 'tesseract' : 'tesseract'),
    'lang' => env('TESSERACT_LANG', 'eng'),
    'enhancement_enabled' => (bool) env('OCR_ENHANCEMENT_ENABLED', true),
    'ghostscript_binary' => env('GHOSTSCRIPT_BINARY', PHP_OS_FAMILY === 'Windows' ? 'gswin64c' : 'gs'),
    'pdf_dpi' => (int) env('OCR_PDF_DPI', 300),
    'storage_disk' => env('OCR_STORAGE_DISK', 'public'),
    'confidence' => [
        // 0.90–1.00 high, 0.75–0.89 medium, below 0.75 low (flagged for manual review).
        'low' => (float) env('OCR_CONFIDENCE_LOW_THRESHOLD', 0.75),
        'medium' => (float) env('OCR_CONFIDENCE_MEDIUM_THRESHOLD', 0.9),
    ],
    'max_file_size_kb' => (int) env('OCR_MAX_FILE_SIZE_KB', 10240),
    'allowed_mimes' => explode(',', env('OCR_ALLOWED_MIMES', 'pdf,jpg,jpeg,png,webp')),

    // Tesseract engine-level options. OEM is left at the package default unless
    // configured; PSM modes are used by the multi-pass selector on complex IDs.
    'oem' => (int) env('OCR_OEM', 3),
    'psm_modes' => array_map(
        fn (string $mode) => (int) trim($mode),
        explode(',', env('OCR_PSM_MODES', '6,11,12'))
    ),
    'default_psm' => (int) env('OCR_DEFAULT_PSM', 3),

    // Bounded multi-pass OCR: never run unlimited passes.
    'max_passes' => (int) env('OCR_MAX_PASSES', 6),

    // Image preprocessing pipeline (GD). The uploaded original is never modified.
    'preprocess' => [
        'enabled' => (bool) env('OCR_PREPROCESS_ENABLED', true),
        'auto_orient' => (bool) env('OCR_AUTO_ORIENT', true),
        'upscale' => (bool) env('OCR_UPSCALE_ENABLED', true),
        'min_width' => (int) env('OCR_MIN_WIDTH', 1200),
        'max_dimension' => (int) env('OCR_MAX_DIMENSION', 4000),
        'grayscale' => (bool) env('OCR_GRAYSCALE_ENABLED', true),
        'contrast' => (bool) env('OCR_CONTRAST_ENABLED', true),
        'denoise' => (bool) env('OCR_DENOISE_ENABLED', true),
        'adaptive_threshold' => (bool) env('OCR_ADAPTIVE_THRESHOLD_ENABLED', true),
        'sharpen' => (bool) env('OCR_SHARPEN_ENABLED', true),
        'threshold_window' => (int) env('OCR_THRESHOLD_WINDOW', 21),
        'threshold_offset' => (int) env('OCR_THRESHOLD_OFFSET', 8),
        'min_luminance' => (int) env('OCR_MIN_LUMINANCE', 0),
        'max_luminance' => (int) env('OCR_MAX_LUMINANCE', 255),
    ],

    // Region-based OCR for complex ID layouts. Coordinates are never hard-coded;
    // regions are discovered from label words (word boxes come from Tesseract TSV).
    'region_ocr' => [
        'enabled' => (bool) env('OCR_REGION_OCR_ENABLED', true),
        'min_label_confidence' => (int) env('OCR_REGION_MIN_LABEL_CONFIDENCE', 55),
        'crop_padding' => (int) env('OCR_REGION_CROP_PADDING', 10),
        'region_psm' => (int) env('OCR_REGION_PSM', 7),
    ],

    // Which declared document types get the specialized government-ID pipeline
    // (preprocessing + multi-PSM + region-based OCR).
    'specialized_types' => ['government_id', 'barangay_id'],

    // Offline accuracy evaluation (php artisan ocr:evaluate).
    'evaluation' => [
        'images_dir' => env('OCR_EVALUATION_IMAGES', storage_path('app/ocr-evaluation/images')),
        'manifest' => env('OCR_EVALUATION_MANIFEST', storage_path('app/ocr-evaluation/manifest.json')),
        'result' => env('OCR_EVALUATION_RESULT', storage_path('app/ocr-evaluation/report.json')),
    ],
];
