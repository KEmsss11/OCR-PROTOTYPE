<?php
require_once __DIR__ . '/db.php';
try {
    $pdo = getDB();
    // Add metadata column to page_results
    $pdo->exec("ALTER TABLE page_results ADD COLUMN IF NOT EXISTS metadata JSON NULL AFTER ocr_text");
    // Add metadata column to submissions (for aggregate or summary data)
    $pdo->exec("ALTER TABLE submissions ADD COLUMN IF NOT EXISTS metadata JSON NULL AFTER missing_pages");
    echo "Migration Successful!";
} catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage();
}
