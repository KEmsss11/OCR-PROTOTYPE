<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ocr_gemini.php';

$testImagesDir = 'c:/xampp/htdocs/OCR/uploads/pages/';
$dirs = glob($testImagesDir . '*', GLOB_ONLYDIR);
if (!empty($dirs)) {
    $latestDir = end($dirs);
    $testImage = $latestDir . '/page-1.png';
    if (file_exists($testImage)) {
        echo "Testing with: $testImage\n";
        $res = runGeminiOCR($testImage, 'form');
        echo "\nRESULT:\n";
        echo $res;
    } else {
        echo "No page-1.png found.";
    }
} else {
    echo "No uploads found.";
}
