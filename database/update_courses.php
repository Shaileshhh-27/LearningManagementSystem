<?php
require_once '../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Read the SQL file
    $sql = file_get_contents('update_courses.sql');
    
    // Execute each SQL statement
    $conn->exec($sql);
    
    echo "Database updated successfully.\n";
} catch (Exception $e) {
    echo "Error updating database: " . $e->getMessage() . "\n";
} 