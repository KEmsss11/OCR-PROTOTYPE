<?php
require_once __DIR__ . '/db.php';
try {
    $pdo = getDB();
    $sql = "CREATE TABLE IF NOT EXISTS field_templates (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        field_key VARCHAR(50) NOT NULL,
        label VARCHAR(100) NOT NULL,
        x1 FLOAT NOT NULL,
        y1 FLOAT NOT NULL,
        x2 FLOAT NOT NULL,
        y2 FLOAT NOT NULL,
        page_number INT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY idx_field_page (field_key, page_number)
    ) ENGINE=InnoDB;";
    $pdo->exec($sql);
    echo "Migration Successful!";
} catch (Exception $e) {
    echo "Migration Failed: " . $e->getMessage();
}
