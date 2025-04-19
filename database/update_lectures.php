<?php
require_once '../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Read and execute the SQL file
    $sql = file_get_contents('update_lectures.sql');
    $conn->exec($sql);
    
    echo "Lectures table updated successfully.\n";
} catch (Exception $e) {
    echo "Error updating lectures table: " . $e->getMessage() . "\n";
}
?> 