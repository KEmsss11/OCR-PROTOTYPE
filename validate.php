<?php
require_once __DIR__ . '/config.php';

/**
 * Validate pages 1–4 (form pages) using OCR text.
 */
function validateFormPage(string $text, int $pageNum): array {
    $issues = [];

    // Check minimum text length
    $cleaned = preg_replace('/\s+/', ' ', $text);
    if (strlen($cleaned) < FORM_MIN_CHARS) {
        $issues[] = "Page $pageNum appears blank or has insufficient text (less than " . FORM_MIN_CHARS . " characters detected).";
    }

    // Check for expected keywords (case-insensitive)
    $keywords = FORM_KEYWORDS[$pageNum] ?? [];
    if (!empty($keywords)) {
        $lowerText = strtolower($text);
        $found = false;
        foreach ($keywords as $kw) {
            if (str_contains($lowerText, strtolower($kw))) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $kwList = implode('", "', $keywords);
            $issues[] = "Page $pageNum: Could not detect expected form fields. Expected keywords like \"$kwList\".";
        }
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
