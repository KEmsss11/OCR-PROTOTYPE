<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/process.php';

// Disable error display to prevent warnings from corrupting JSON
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Increase limits for OCR processing
set_time_limit(300); // 5 minutes
ini_set('memory_limit', '512M');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// Set EXTRACTION_ENGINE based on POST
if (isset($_POST['engine']) && in_array($_POST['engine'], ['paddle', 'gemini'])) {
    if (!defined('EXTRACTION_ENGINE')) {
        define('EXTRACTION_ENGINE', $_POST['engine']);
    }
}

// Validate file present
if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['error' => 'No file uploaded. Please select a PDF file.']);
    exit;
}

$file = $_FILES['pdf'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errMessages = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension blocked the upload.',
    ];
    echo json_encode(['error' => $errMessages[$file['error']] ?? 'Unknown upload error.']);
    exit;
}

// Validate file size
if ($file['size'] > MAX_FILE_SIZE_B) {
    echo json_encode(['error' => 'File too large. Maximum allowed size is ' . MAX_FILE_SIZE_MB . ' MB.']);
    exit;
}

// Validate MIME type
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);
if ($mimeType !== 'application/pdf') {
    echo json_encode(['error' => 'Invalid file type. Only PDF files are accepted.']);
    exit;
}

// Generate UUID and save file
$uuid      = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
    mt_rand(0, 0xffff), mt_rand(0, 0xffff),
    mt_rand(0, 0xffff),
    mt_rand(0, 0x0fff) | 0x4000,
    mt_rand(0, 0x3fff) | 0x8000,
    mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
);
$destPath  = UPLOAD_PRIVATE . $uuid . '.pdf';

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['error' => 'Failed to save uploaded file. Check server permissions.']);
    exit;
}

// Insert submission record
$pdo  = getDB();
$stmt = $pdo->prepare(
    "INSERT INTO submissions (uuid, original_filename, status) VALUES (?, ?, 'processing')"
);
$stmt->execute([$uuid, basename($file['name'])]);
$submissionId = (int) $pdo->lastInsertId();

// Process the PDF
$result = processSubmission($submissionId, $uuid, $destPath);

// Return result
echo json_encode(array_merge(['uuid' => $uuid], $result));
