<?php
// This is a compatibility file to ensure both approaches work
require_once __DIR__ . '/../config/database.php';

// Create a database connection if one doesn't exist
if (!isset($conn)) {
    $db = new Database();
    $conn = $db->getConnection();
}
?> 