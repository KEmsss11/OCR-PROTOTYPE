-- OCR Document Verification System
-- Database: ocr_system

CREATE DATABASE IF NOT EXISTS ocr_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ocr_system;

CREATE TABLE IF NOT EXISTS submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) NOT NULL UNIQUE,
    original_filename VARCHAR(255) NOT NULL,
    total_pages INT DEFAULT 0,
    status ENUM('processing', 'pending', 'incomplete', 'error') DEFAULT 'processing',
    missing_pages JSON NULL COMMENT 'JSON array of missing/failed page descriptions',
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME NULL,
    INDEX idx_uuid (uuid),
    INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS page_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submission_id INT UNSIGNED NOT NULL,
    page_number INT NOT NULL,
    page_type ENUM('form', 'id_picture', 'documentary') NOT NULL,
    image_path VARCHAR(512) NOT NULL,
    ocr_text MEDIUMTEXT NULL,
    is_valid TINYINT(1) DEFAULT 0,
    issues JSON NULL COMMENT 'JSON array of issue descriptions for this page',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
    INDEX idx_submission (submission_id)
) ENGINE=InnoDB;
