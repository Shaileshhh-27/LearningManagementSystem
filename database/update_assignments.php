<?php
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Read the SQL file
$sql = file_get_contents(__DIR__ . '/update_assignments.sql');

// Execute the SQL
$result = $conn->exec($sql);

echo "Assignments table updated successfully!";
?> 