<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/ocr.php';
require_once __DIR__ . '/validate.php';

/**
 * Auto-detect the Ghostscript executable on Windows.
 * Scans common install directories so users don't have to set exact paths.
 */
function findGhostscript(): string|false {
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

    return false;
}

/**
 * Convert a PDF to per-page PNG images using Ghostscript.
 * Returns array of image file paths, or false on failure.
 */
function pdfToImages(string $pdfPath, string $outputDir): array|false {
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $gsBin = findGhostscript();
    if ($gsBin === false) {
        error_log('Ghostscript not found on this system.');
        return false;
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
        return false;
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

/**
 * Main processing function.
 * Converts PDF → images, runs OCR/validation on each page,
 * saves results to DB, and returns a structured result array.
 */
function processSubmission(int $submissionId, string $uuid, string $pdfPath): array {
    $pdo       = getDB();
    $outputDir = UPLOAD_PAGES . $uuid;
    $pages     = pdfToImages($pdfPath, $outputDir);

    if ($pages === false || empty($pages)) {
        // Check if it's a missing Ghostscript issue
        $gsFound = findGhostscript();
        $msg = $gsFound === false
            ? 'Ghostscript is not installed on this server. Please download and install it from https://www.ghostscript.com/ then restart Apache.'
            : 'Ghostscript was found but failed to convert the PDF. The file may be corrupted, password-protected, or an unsupported format.';

        $pdo->prepare("UPDATE submissions SET status='error', processed_at=NOW() WHERE id=?")
            ->execute([$submissionId]);
        return [
            'status'  => 'error',
            'message' => $msg,
            'missing' => [],
            'pages'   => [],
        ];
    }

    $totalPages  = count($pages);
    $allMissing  = [];
    $pageDetails = [];

    // Determine expected page types
    $pageTypeMap = [];
    foreach (FORM_PAGES as $p)   $pageTypeMap[$p] = 'form';
    $pageTypeMap[ID_PAGE]          = 'id_picture';
    $pageTypeMap[DOCUMENTARY_PAGE] = 'documentary';

    // --- Check required page count ---
    if ($totalPages < REQUIRED_PAGES) {
        for ($p = $totalPages + 1; $p <= REQUIRED_PAGES; $p++) {
            $label = match(true) {
                in_array($p, FORM_PAGES)   => "Page $p: Form page missing",
                $p === ID_PAGE             => "Page $p: ID picture page missing",
                $p === DOCUMENTARY_PAGE    => "Page $p: Documentary photo page missing",
                default                    => "Page $p: Required page missing",
            };
            $allMissing[] = $label;
        }
    }

    // --- Process each existing page up to 6 ---
    // We only care if the page exists. If it exists, it's valid.
    for ($pageNum = 1; $pageNum <= max($totalPages, REQUIRED_PAGES); $pageNum++) {
        $imagePath = $pages[$pageNum] ?? null;
        $type      = $pageTypeMap[$pageNum] ?? 'form';
        $isValid   = ($imagePath !== null);
        $issues    = [];

        if (!$isValid && $pageNum <= REQUIRED_PAGES) {
            // This is already handled in $allMissing above, but we track per-page status here too
            $issues[] = "Page $pageNum is missing from the uploaded document.";
        }

        // Save page result to DB
        $stmt = $pdo->prepare(
            "INSERT INTO page_results 
             (submission_id, page_number, page_type, image_path, ocr_text, is_valid, issues)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $submissionId,
            $pageNum,
            $type,
            $imagePath ? str_replace(BASE_DIR, '', $imagePath) : '',
            null, // No OCR text anymore
            $isValid ? 1 : 0,
            json_encode($issues),
        ]);

        $pageDetails[$pageNum] = [
            'page'   => $pageNum,
            'type'   => $type,
            'valid'  => $isValid,
            'issues' => $issues,
            'text_preview' => null,
        ];
    }

    $finalStatus = empty($allMissing) ? 'pending' : 'incomplete';

    // Update submission record
    $pdo->prepare(
        "UPDATE submissions 
         SET status=?, total_pages=?, missing_pages=?, processed_at=NOW() 
         WHERE id=?"
    )->execute([$finalStatus, $totalPages, json_encode($allMissing), $submissionId]);

    return [
        'status'  => $finalStatus,
        'missing' => $allMissing,
        'pages'   => array_values($pageDetails),
    ];
}
