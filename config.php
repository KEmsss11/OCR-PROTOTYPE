<?php
// ============================================================
// OCR Document Verification System — Configuration
// ============================================================

// --- Database ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'ocr_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// --- Paths ---
define('BASE_DIR',         __DIR__);
define('UPLOAD_PRIVATE',   BASE_DIR . '/uploads/private/');
define('UPLOAD_PAGES',     BASE_DIR . '/uploads/pages/');

// --- Executables (Windows XAMPP paths — adjust if needed) ---
define('TESSERACT_BIN',    'C:/Program Files/Tesseract-OCR/tesseract.exe');
define('GHOSTSCRIPT_BIN',  'C:/Program Files/gs/gs10.04.0/bin/gswin64c.exe');

// --- Upload limits ---
define('MAX_FILE_SIZE_MB', 20);
define('MAX_FILE_SIZE_B',  MAX_FILE_SIZE_MB * 1024 * 1024);

// --- Page Structure ---
// Pages 1-4 => form fields
// Page 5    => ID picture
// Page 6    => Documentary photo
define('REQUIRED_PAGES',   6);
define('FORM_PAGES',       [1, 2, 3, 4]);
define('ID_PAGE',          5);
define('DOCUMENTARY_PAGE', 6);

// --- Form OCR Validation ---
// Keywords that MUST appear (any one of them) on EACH form page.
// Adjust these to match your actual form layout.
define('FORM_KEYWORDS', [
    1 => ['name', 'surname', 'first name', 'last name', 'applicant'],
    2 => ['date', 'address', 'contact', 'phone', 'email'],
    3 => ['signature', 'signed', 'witness', 'notary', 'acknowledge'],
    4 => ['purpose', 'reason', 'description', 'remarks', 'noted'],
]);

// Minimum non-whitespace character count for OCR to consider a form page "filled"
define('FORM_MIN_CHARS', 30);

// Minimum non-white pixel ratio to consider an image page "non-blank" (0.0–1.0)
define('IMAGE_MIN_PIXEL_RATIO', 0.05);
