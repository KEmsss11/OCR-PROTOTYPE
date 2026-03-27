<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ocr_gemini.php';

$testImage = 'c:/xampp/htdocs/OCR/uploads/pages/fb26f737-65ce-4913-9b39-677f82b56d28/page-1.png';

if (!file_exists($testImage)) {
    die("Test image not found at $testImage\n");
}

echo "Testing Gemini OCR on $testImage...\n";
$result = runGeminiOCR($testImage, 'form');

echo "--- RAW RESULT ---\n";
echo $result . "\n";
echo "--- DECODED ---\n";
print_r(json_decode($result, true));
