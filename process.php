<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ocr.php';
require_once __DIR__ . '/validate.php';

/**
 * Auto-detect the Ghostscript executable on Windows.
 * Scans common install directories so users don't have to set exact paths.
 */
function findGhostscript(): ?string {
    // 1. Try the configured path first
    if (defined('GHOSTSCRIPT_BIN') && file_exists(GHOSTSCRIPT_BIN)) {
        return GHOSTSCRIPT_BIN;
    }

    // 2. Scan common Program Files directories for any gs version
    $searchDirs = [
        'C:/Program Files/gs',
        'C:/Program Files (x86)/gs',
    ];
    $binaries = ['gswin64c.exe', 'gswin32c.exe', 'gs.exe'];

    foreach ($searchDirs as $dir) {
        if (!is_dir($dir)) continue;
        // Each subdirectory is a version folder like gs10.04.0
        foreach (glob($dir . '/*/bin/*') ?: [] as $path) {
            if (in_array(basename($path), $binaries)) {
                return str_replace('\\', '/', $path);
            }
        }
    }

    // 3. Try PATH via where.exe
    foreach ($binaries as $bin) {
        exec('where.exe ' . $bin . ' 2>nul', $out, $code);
        if ($code === 0 && !empty($out[0])) {
            return trim($out[0]);
        }
    }

    return null;
}

/**
 * Convert a PDF to per-page PNG images using Ghostscript.
 * Returns array of image file paths, or false on failure.
 */
function pdfToImages(string $pdfPath, string $outputDir): ?array {
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $gsBin = findGhostscript();
    if ($gsBin === null) {
        error_log('Ghostscript not found on this system.');
        return null;
    }

    $outputPattern = $outputDir . '/page-%d.png';

    $cmd = sprintf(
        '"%s" -dBATCH -dNOPAUSE -dSAFER -sDEVICE=png16m -r150 ' .
        '-dTextAlphaBits=4 -dGraphicsAlphaBits=4 ' .
        '-sOutputFile="%s" "%s" 2>&1',
        $gsBin,
        $outputPattern,
        $pdfPath
    );

    exec($cmd, $output, $returnCode);

    if ($returnCode !== 0) {
        error_log('Ghostscript error (exit ' . $returnCode . '): ' . implode("\n", $output));
        return null;
    }

    // Collect generated files
    $pages = [];
    $i = 1;
    while (true) {
        $file = $outputDir . "/page-$i.png";
        if (!file_exists($file)) break;
        $pages[$i] = $file;
        $i++;
    }

    return $pages;
}

require_once __DIR__ . '/ocr_gemini.php';

/**
 * Extracts specific fields from OCR text using Gemini 1.5 Flash (Vision).
 */
function extractPageMetadata(string $imagePath, string $pageType = 'form', int $pageNum = 1, string $aiModel = 'gemini-flash-latest'): array {
    $geminiJson = runGeminiOCR($imagePath, $pageType, $aiModel);
    $geminiData = json_decode($geminiJson, true);
    
    if ($geminiData && !isset($geminiData['error'])) {
        return $geminiData;
    }

    $errorMessage = 'AI was unable to extract structured data from this page.';
    if (isset($geminiData['error'])) {
        $errorMessage = 'API Error: ' . (is_string($geminiData['error']) ? $geminiData['error'] : json_encode($geminiData['error']));
        if (isset($geminiData['details'])) {
            $errorMessage .= "\n" . (is_string($geminiData['details']) ? $geminiData['details'] : json_encode($geminiData['details']));
        }
    }

    return [
        'error'    => 'Extraction failed',
        'raw_text' => $errorMessage
    ];
}

/**
 * Main processing function.
 * Converts PDF → images, runs OCR/validation on each page,
 * saves results to DB, and returns a structured result array.
 */
function processSubmission(int $submissionId, string $uuid, string $pdfPath, string $aiModel = 'gemini-flash-latest'): array {
    $pdo       = getDB();
    $outputDir = UPLOAD_PAGES . $uuid;
    $pages     = pdfToImages($pdfPath, $outputDir);

    if ($pages === null || empty($pages)) {
        $gsFound = findGhostscript();
        $msg = $gsFound === null
            ? 'Ghostscript is not installed on this server.'
            : 'Ghostscript was found but failed to convert the PDF.';

        $pdo->prepare("UPDATE submissions SET status='error', processed_at=NOW() WHERE id=?")
            ->execute([$submissionId]);
        return ['status' => 'error', 'message' => $msg, 'missing' => [], 'pages' => []];
    }

    $totalPages  = count($pages);
    $allMissing  = [];
    $pageDetails = [];

    $pageTypeMap = [];
    foreach (FORM_PAGES as $p) $pageTypeMap[$p] = 'form';
    $pageTypeMap[ID_PAGE]          = 'id_picture';
    $pageTypeMap[DOCUMENTARY_PAGE] = 'documentary';

    if ($totalPages < REQUIRED_PAGES) {
        for ($p = $totalPages + 1; $p <= REQUIRED_PAGES; $p++) {
            $allMissing[] = "Page $p: Required page missing";
        }
    }

    for ($pageNum = 1; $pageNum <= max($totalPages, REQUIRED_PAGES); $pageNum++) {
        $imagePath = $pages[$pageNum] ?? null;
        $type      = $pageTypeMap[$pageNum] ?? 'form';
        
        $metadata = [];
        if ($imagePath) {
            $metadata = extractPageMetadata($imagePath, $type, $pageNum, $aiModel);
        }

        // Use 'raw_text' from Gemini for validation if available
        $ocrText = $metadata['raw_text'] ?? '';
        // Validate using structured metadata
        $isValid = validatePage($metadata, $type, $pageNum);
        $issues  = getValidationIssues($metadata, $type, $pageNum);

        if (!$isValid && $pageNum <= REQUIRED_PAGES) {
            $issues[] = "Page $pageNum is missing or invalid.";
        }

        // Save page result to DB
        $stmt = $pdo->prepare(
            "INSERT INTO page_results 
             (submission_id, page_number, page_type, image_path, ocr_text, metadata, is_valid, issues)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $submissionId, $pageNum, $type,
            $imagePath ? ltrim(str_ireplace([BASE_DIR, '\\'], ['', '/'], $imagePath), '/') : '',
            $ocrText ?: null, json_encode($metadata),
            $isValid ? 1 : 0, json_encode($issues),
        ]);

        $pageDetails[$pageNum] = [
            'page'         => $pageNum,
            'type'         => $type,
            'valid'        => $isValid,
            'issues'       => $issues,
            'metadata'     => $metadata,
            'image_path'   => $imagePath ? ltrim(str_ireplace([BASE_DIR, '\\'], ['', '/'], $imagePath), '/') : '',
            'text_preview' => $ocrText ? mb_substr($ocrText, 0, 120) . '...' : null,
        ];
    }

    $finalStatus = empty($allMissing) ? 'pending' : 'incomplete';
    $summaryMeta = $pageDetails[1]['metadata'] ?? null;

    $pdo->prepare("UPDATE submissions SET status=?, total_pages=?, missing_pages=?, metadata=?, processed_at=NOW() WHERE id=?")
        ->execute([$finalStatus, $totalPages, json_encode($allMissing), json_encode($summaryMeta), $submissionId]);

    return [
        'status'  => $finalStatus,
        'missing' => $allMissing,
        'pages'   => array_values($pageDetails),
    ];
}
