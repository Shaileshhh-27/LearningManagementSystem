<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

try {
    // Read and execute the SQL file
    $sql = file_get_contents(__DIR__ . '/update_courses.sql');
    $conn->exec($sql);
    echo "Courses table updated successfully!";
} catch (Exception $e) {
    echo "Error updating courses table: " . $e->getMessage();
}
?> 