<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // Add required columns to courses table
    $queries = [
        "ALTER TABLE courses ADD COLUMN IF NOT EXISTS start_date DATE DEFAULT CURRENT_DATE",
        "ALTER TABLE courses ADD COLUMN IF NOT EXISTS end_date DATE DEFAULT (date('now', '+3 months'))",
        "ALTER TABLE courses ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'active'",
        "CREATE INDEX IF NOT EXISTS idx_unique_course_teacher ON courses(title, teacher_id)"
    ];

    foreach ($queries as $query) {
        $result = $conn->exec($query);
        if ($result === false) {
            echo "Error executing query: " . $conn->lastErrorMsg() . "\n";
        } else {
            echo "Successfully executed: " . $query . "\n";
        }
    }

    echo "Database setup completed successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?> 