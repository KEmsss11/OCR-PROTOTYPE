<?php
require_once __DIR__ . '/config.php';

/**
 * Run Tesseract OCR on a given image file.
 * Returns the extracted text string, or empty string on failure.
 */
function runTesseract(string $imagePath): string {
    if (!file_exists($imagePath)) {
        return '';
    }

    // Output to a temp file (tesseract appends .txt automatically)
    $outBase = tempnam(sys_get_temp_dir(), 'ocr_');
    $outTxt  = $outBase . '.txt';

    $cmd = sprintf(
        '"%s" "%s" "%s" --psm 6 -l eng 2>&1',
        TESSERACT_BIN,
        $imagePath,
        $outBase
    );

    exec($cmd, $output, $returnCode);

    $text = '';
    if (file_exists($outTxt)) {
        $text = file_get_contents($outTxt);
        @unlink($outTxt);
    }
    @unlink($outBase);

    return trim($text);
}
