<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Start transaction
    $conn->exec('BEGIN');
    
    // Add missing columns
    $conn->exec('ALTER TABLE assignments ADD COLUMN IF NOT EXISTS teacher_id INTEGER');
    $conn->exec('ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS status VARCHAR(20)');
    $conn->exec('ALTER TABLE courses ADD COLUMN IF NOT EXISTS updated_at DATETIME');
    $conn->exec('ALTER TABLE submissions ADD COLUMN IF NOT EXISTS submitted BOOLEAN DEFAULT 0');
    
    // Commit changes
    $conn->exec('COMMIT');
    
    echo "Database structure updated successfully";
} catch (Exception $e) {
    $conn->exec('ROLLBACK');
    echo "Error updating database: " . $e->getMessage();
}