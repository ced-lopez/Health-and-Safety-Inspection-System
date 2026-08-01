<?php

return [
    'tesseract_binary' => env('TESSERACT_BINARY', 'tesseract'),
    'lang' => env('TESSERACT_LANG', 'eng'),
    'enhancement_enabled' => (bool) env('OCR_ENHANCEMENT_ENABLED', true),
];
