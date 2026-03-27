<?php
require_once __DIR__ . '/config.php';

/**
 * Run OCR on a given image file using PaddleOCR (via Python bridge).
 * Falls back to Tesseract if PaddleOCR fails.
 */
function runOCR(string $imagePath): string {
    if (!file_exists($imagePath)) {
        return '';
    }

    // 1. Try PaddleOCR (Recommended)
    $pyBin    = defined('PYTHON_BIN') ? PYTHON_BIN : 'py';
    $pyScript = defined('PADDLE_SCRIPT') ? PADDLE_SCRIPT : __DIR__ . '/ocr_paddle.py';
    $cmd      = sprintf('"%s" "%s" "%s" 2>&1', $pyBin, $pyScript, $imagePath);
    
    $output = [];
    $returnCode = 0;
    exec($cmd, $output, $returnCode);

    if ($returnCode === 0 && !empty($output)) {
        $json = implode('', $output);
        $data = json_decode($json, true);

        if (is_array($data)) {
            if (isset($data['error'])) {
                error_log("PaddleOCR Error: " . $data['error']);
                return runTesseract($imagePath);
            }
            
            // Return the raw JSON for process.php to use spatial data
            return $json;
        }
    }

    // 2. Fallback to Tesseract if PaddleOCR fails
    return runTesseract($imagePath);
}

/**
 * Standard Tesseract OCR (Fallback)
 */
function runTesseract(string $imagePath): string {
    if (!defined('TESSERACT_BIN') || !file_exists(TESSERACT_BIN)) return '';
    
    $outBase = tempnam(sys_get_temp_dir(), 'ocr_');
    $outTxt  = $outBase . '.txt';

    $cmd = sprintf('"%s" "%s" "%s" --psm 6 -l eng 2>&1', TESSERACT_BIN, $imagePath, $outBase);
    exec($cmd, $output, $returnCode);

    $text = '';
    if (file_exists($outTxt)) {
        $text = trim(file_get_contents($outTxt));
        @unlink($outTxt);
    }
    @unlink($outBase);

    // Return as JSON with mock boxes for consistency
    return json_encode([['text' => $text, 'box' => [[0,0],[0,0],[0,0],[0,0]]]]);
}
