<!DOCTYPE html>
<html>
<head>
    <title>PHP Configuration Check</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .config-item { margin: 10px 0; padding: 10px; background: #f5f5f5; }
        .path-info { color: #666; }
        .warning { color: #cc3300; }
        .success { color: #009933; }
    </style>
</head>
<body>
    <h1>PHP Configuration Information</h1>
    
    <?php
    require_once 'config/database.php';

    echo "<div class='config-item'>";
    echo "<strong>php.ini Location:</strong><br>";
    echo "<span class='path-info'>" . php_ini_loaded_file() . "</span>";
    echo "</div>";

    echo "<div class='config-item'>";
    echo "<strong>Current Settings:</strong><br>";
    echo "post_max_size = " . ini_get('post_max_size') . "<br>";
    echo "upload_max_filesize = " . ini_get('upload_max_filesize') . "<br>";
    echo "memory_limit = " . ini_get('memory_limit') . "<br>";
    echo "max_execution_time = " . ini_get('max_execution_time') . " seconds<br>";
    echo "max_input_time = " . ini_get('max_input_time') . " seconds";
    echo "</div>";

    // Database Statistics
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        echo "<div class='config-item'>";
        echo "<strong>Database Statistics:</strong><br>";
        
        // Count total users
        $stmt = $conn->prepare('SELECT COUNT(*) as count FROM users');
        $result = $stmt->execute();
        $userCount = $result->fetchArray(SQLITE3_ASSOC)['count'];
        echo "Total Users: " . $userCount . "<br>";
        
        // Count total courses
        $stmt = $conn->prepare('SELECT COUNT(*) as count FROM courses');
        $result = $stmt->execute();
        $courseCount = $result->fetchArray(SQLITE3_ASSOC)['count'];
        echo "Total Courses: " . $courseCount . "<br>";
        
        // Count total assignments
        $stmt = $conn->prepare('SELECT COUNT(*) as count FROM assignments');
        $result = $stmt->execute();
        $assignmentCount = $result->fetchArray(SQLITE3_ASSOC)['count'];
        echo "Total Assignments: " . $assignmentCount . "<br>";
        
        // Count total submissions
        $stmt = $conn->prepare('SELECT COUNT(*) as count FROM submissions');
        $result = $stmt->execute();
        $submissionCount = $result->fetchArray(SQLITE3_ASSOC)['count'];
        echo "Total Submissions: " . $submissionCount . "<br>";

        // Get database file size
        $dbPath = $conn->filename;
        $dbSize = filesize($dbPath);
        echo "Database File Size: " . round($dbSize / 1024 / 1024, 2) . " MB<br>";
        
        // Check if current POST limit is sufficient
        $postMaxSize = return_bytes(ini_get('post_max_size'));
        $recommendedSize = $dbSize * 2; // Recommend 2x current database size
        
        echo "<br>Analysis:<br>";
        if ($postMaxSize < $recommendedSize) {
            echo "<span class='warning'>Current POST limit may be too low. Recommended minimum: " . 
                 ceil($recommendedSize / 1024 / 1024) . "M</span>";
        } else {
            echo "<span class='success'>Current POST limit appears to be sufficient.</span>";
        }
        
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div class='config-item warning'>";
        echo "Error connecting to database: " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }

    echo "<div class='config-item'>";
    echo "<strong>Recommended Settings:</strong><br>";
    echo "post_max_size = 2G<br>";
    echo "upload_max_filesize = 2G<br>";
    echo "memory_limit = 2G<br>";
    echo "max_execution_time = 300<br>";
    echo "max_input_time = 300";
    echo "</div>";

    echo "<div class='config-item'>";
    echo "<strong>How to Update:</strong><br>";
    echo "1. Locate your php.ini file (shown above)<br>";
    echo "2. Add or modify these lines in php.ini:<br>";
    echo "<pre>";
    echo "post_max_size = 2G\n";
    echo "upload_max_filesize = 2G\n";
    echo "memory_limit = 2G\n";
    echo "max_execution_time = 300\n";
    echo "max_input_time = 300";
    echo "</pre>";
    echo "3. Restart your XAMPP Apache server";
    echo "</div>";

    // Helper function to convert PHP size strings to bytes
    function return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;
        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }
        return $val;
    }
    ?>
</body>
</html> 