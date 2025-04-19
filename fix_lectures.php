<?php
// Script to add the missing thumbnail_path column to the lectures table
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Add the missing column
    $conn->exec('ALTER TABLE lectures ADD COLUMN thumbnail_path VARCHAR(255)');
    
    echo "<h1>Database Update Successful</h1>";
    echo "<p>Successfully added thumbnail_path column to lectures table!</p>";
    echo "<p>You can now <a href='dashboard.php'>return to dashboard</a> and upload lectures.</p>";
} catch (Exception $e) {
    echo "<h1>Error</h1>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
    echo "<p>If you're still having issues, try using the fixed upload page instead:</p>";
    echo "<p><a href='upload_lecture_fixed.php'>Use Fixed Upload Page</a></p>";
}
?> 