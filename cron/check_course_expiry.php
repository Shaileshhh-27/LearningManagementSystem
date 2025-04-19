<?php
/**
 * This script checks for expired courses and updates their status
 * It should be run as a cron job, e.g., daily
 * 
 * Example cron entry (daily at midnight):
 * 0 0 * * * php /path/to/check_course_expiry.php
 */

// Set the correct path to your project root
$projectRoot = dirname(dirname(__FILE__));

// Include necessary files
require_once $projectRoot . '/config/database.php';

// Initialize database connection
$db = new Database();
$conn = $db->getConnection();

try {
    // Start transaction
    $conn->exec('BEGIN');
    
    // Get current date
    $currentDate = date('Y-m-d');
    
    // Find active courses that have passed their end date
    $stmt = $conn->prepare('
        SELECT id, title, end_date
        FROM courses
        WHERE status = "active"
        AND end_date < :current_date
    ');
    $stmt->bindValue(':current_date', $currentDate, SQLITE3_TEXT);
    $result = $stmt->execute();
    
    $expiredCourses = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $expiredCourses[] = $row;
    }
    
    // Update expired courses
    if (!empty($expiredCourses)) {
        $updateStmt = $conn->prepare('
            UPDATE courses
            SET status = "expired"
            WHERE id = :id
        ');
        
        foreach ($expiredCourses as $course) {
            $updateStmt->bindValue(':id', $course['id'], SQLITE3_INTEGER);
            $updateStmt->execute();
            
            echo "Course expired: " . $course['title'] . " (ID: " . $course['id'] . ", End date: " . $course['end_date'] . ")\n";
        }
        
        echo "Total courses expired: " . count($expiredCourses) . "\n";
    } else {
        echo "No courses have expired.\n";
    }
    
    // Commit transaction
    $conn->exec('COMMIT');
    
    echo "Course expiry check completed successfully at " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($conn)) {
        $conn->exec('ROLLBACK');
    }
    
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
} 