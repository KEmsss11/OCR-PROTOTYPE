<?php
require_once __DIR__ . '/config.php';

/**
 * Validate pages 1–4 (form pages) using OCR metadata.
 */
function validateFormPage(array $metadata, int $pageNum): array {
    $issues = [];

    if (isset($metadata['error'])) {
        $issues[] = "Page $pageNum Data Extraction Error: " . ($metadata['raw_text'] ?? 'Unknown Error');
        return ['valid' => false, 'issues' => $issues];
    }

    // Check if we successfully extracted actual structured fields
    $extractedFields = 0;
    foreach ($metadata as $key => $val) {
        if ($key !== 'raw_text' && $val && $val !== 'Not Detected') {
            $extractedFields++;
        }
    }

    $text = $metadata['raw_text'] ?? '';
    $cleaned = preg_replace('/\s+/', ' ', $text);
    
    // If no fields were found AND there is no readable raw text, mark as missing
    if ($extractedFields === 0 && strlen($cleaned) < FORM_MIN_CHARS) {
        $issues[] = "Page $pageNum appears to have very little or no readable text.";
    }

    return [
        'valid'  => empty($issues),
        'issues' => $issues,
    ];
}


/**
 * Validate an image page (page 5 or 6) by checking it is not blank.
 * Uses GD to sample pixel brightness across the image.
 */
function validateImagePage(string $imagePath, int $pageNum, string $label): array {
    $issues = [];

    if (!file_exists($imagePath)) {
        return ['valid' => false, 'issues' => ["Page $pageNum ($label): Image file not found."]];
    }

    // Check if GD extension is loaded
    if (!function_exists('imagecreatefrompng')) {
        return [
            'valid' => false, 
            'issues' => ["Page $pageNum ($label): PHP GD extension is not enabled on this server. Please enable 'extension=gd' in php.ini and restart Apache."]
        ];
    }

    // Load image with GD
    $info = @getimagesize($imagePath);
    if ($info === false) {
        return ['valid' => false, 'issues' => ["Page $pageNum ($label): Unable to read image file."]];
    }

    $img = null;
    switch ($info[2]) {
        case IMAGETYPE_PNG:  $img = @imagecreatefrompng($imagePath);  break;
        case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($imagePath); break;
        case IMAGETYPE_BMP:  $img = @imagecreatefrombmp($imagePath);  break;
        default:
            $issues[] = "Page $pageNum ($label): Unsupported image type for blank detection.";
            return ['valid' => false, 'issues' => $issues];
    }

    if (!$img) {
        return ['valid' => false, 'issues' => ["Page $pageNum ($label): Could not load image for analysis."]];
    }

    $width  = imagesx($img);
    $height = imagesy($img);
    $total  = $width * $height;

    if ($total === 0) {
        imagedestroy($img);
        return ['valid' => false, 'issues' => ["Page $pageNum ($label): Image has zero pixels."]];
    }

    // Sample up to 1000 pixels for performance
    $sampleRate = max(1, (int)($total / 1000));
    $nonWhite   = 0;
    $sampled    = 0;

    for ($y = 0; $y < $height; $y += $sampleRate) {
        for ($x = 0; $x < $width; $x += $sampleRate) {
            $rgb = imagecolorat($img, $x, $y);
            $r   = ($rgb >> 16) & 0xFF;
            $g   = ($rgb >> 8)  & 0xFF;
            $b   =  $rgb        & 0xFF;
            // Consider pixel "non-white" if any channel is below 240
            if ($r < 240 || $g < 240 || $b < 240) {
                $nonWhite++;
            }
            $sampled++;
        }
    }

    imagedestroy($img);

    $ratio = $sampled > 0 ? $nonWhite / $sampled : 0;

    if ($ratio < IMAGE_MIN_PIXEL_RATIO) {
        $pct = round(IMAGE_MIN_PIXEL_RATIO * 100);
        $issues[] = "Page $pageNum ($label): Image appears blank or nearly white. Less than {$pct}% of pixels contain content.";
    }

    return [
        'valid'  => empty($issues),
        'issues' => $issues,
    ];
}

/**
 * Route validation to the appropriate function based on type and metadata.
 */
function validatePage(array $metadata, string $type, int $pageNum): bool {
    $text = $metadata['raw_text'] ?? '';
    
    if ($type === 'form') {
        $res = validateFormPage($metadata, $pageNum);
        return $res['valid'];
    }
    
    if ($type === 'id_picture' || $type === 'documentary') {
        $docType = $metadata['document_type'] ?? '';
        return !empty($docType) && $docType !== 'Not Detected';
    }

    return !empty(trim($text));
}

/**
 * Get detailed issues for a page using structured metadata.
 */
function getValidationIssues(array $metadata, string $type, int $pageNum): array {
    $text = $metadata['raw_text'] ?? '';
    
    if ($type === 'form') {
        $res = validateFormPage($metadata, $pageNum);
        return $res['issues'];
    }
    
    if ($type === 'id_picture' || $type === 'documentary') {
        $issues = [];
        $docType = $metadata['document_type'] ?? '';
        if (empty($docType) || $docType === 'Not Detected') {
            $issues[] = "Page $pageNum: Could not clearly identify what type of document/image this is.";
        }
        return $issues;
    }

    
    if (empty(trim($text)) && $type !== 'documentary') {
        return ["No readable text detected on this $type page."];
    }
    
    return [];
}
