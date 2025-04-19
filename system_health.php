<?php
require_once 'config/database.php';

function checkSystemHealth() {
    $issues = [];
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check database connection
    if (!$conn) {
        $issues[] = "Database connection failed";
        return $issues;
    }
    
    // Check required tables
    $requiredTables = ['users', 'courses', 'enrollments', 'assignments', 'submissions'];
    $result = $conn->query("SELECT name FROM sqlite_master WHERE type='table'");
    $existingTables = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $existingTables[] = $row['name'];
    }
    
    $missingTables = array_diff($requiredTables, $existingTables);
    if (!empty($missingTables)) {
        $issues[] = "Missing tables: " . implode(', ', $missingTables);
    }
    
    // Check file permissions
    $paths = [
        'error_log.txt',
        'debug_log.txt',
        'config/database.php'
    ];
    
    foreach ($paths as $path) {
        if (!is_writable($path)) {
            $issues[] = "File not writable: $path";
        }
    }
    
    return $issues;
}

$healthIssues = checkSystemHealth();
header('Content-Type: application/json');
echo json_encode(['status' => empty($healthIssues) ? 'healthy' : 'issues', 'issues' => $healthIssues]);