<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Read and execute the SQL file
$sql = file_get_contents(__DIR__ . '/activity_log.sql');
$conn->exec($sql);

echo "Activity log table created successfully!";
?> 