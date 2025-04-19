<?php
// Script to add the missing thumbnail_path column to the lectures table
require_once 'dddd/config/database.php';

$success = false;
$error_message = '';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Add the missing column
    $conn->exec('ALTER TABLE lectures ADD COLUMN thumbnail_path VARCHAR(255)');
    $success = true;
} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Lectures Table</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            margin-top: 0;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 5px solid #28a745;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 5px solid #dc3545;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
            margin-top: 15px;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .btn-danger {
            background-color: #dc3545;
        }
        .btn-danger:hover {
            background-color: #c82333;
        }
        .icon {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($success): ?>
            <h1><i class="fas fa-check-circle icon"></i> Database Update Successful</h1>
            <div class="success">
                <p>Successfully added thumbnail_path column to lectures table!</p>
            </div>
            <p>The database structure has been updated. You can now upload lectures with thumbnail support.</p>
            <a href="dddd/dashboard.php" class="btn"><i class="fas fa-home icon"></i> Return to Dashboard</a>
        <?php else: ?>
            <h1><i class="fas fa-exclamation-triangle icon"></i> Error</h1>
            <div class="error">
                <p>Error: <?php echo $error_message; ?></p>
            </div>
            <p>There was an issue updating the database structure. Here are some alternative solutions:</p>
            
            <h3>Option 1: Modify upload_lecture.php</h3>
            <p>Edit your upload_lecture.php file and replace lines 59-65 with:</p>
            <pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto;">
$stmt = $conn->prepare('
    INSERT INTO lectures (course_id, title, description, video_path) 
    VALUES (:course_id, :title, :description, :video_path)
');
$stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
$stmt->bindValue(':title', $title, SQLITE3_TEXT);
$stmt->bindValue(':description', $description, SQLITE3_TEXT);
$stmt->bindValue(':video_path', $targetPath, SQLITE3_TEXT);</pre>
            
            <h3>Option 2: Create the table from scratch</h3>
            <p>Run this SQL to recreate the lectures table:</p>
            <pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto;">
CREATE TABLE IF NOT EXISTS lectures (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    course_id INTEGER,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    video_path VARCHAR(255) NOT NULL,
    thumbnail_path VARCHAR(255),
    upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id)
);</pre>
            
            <div>
                <a href="dddd/dashboard.php" class="btn btn-secondary"><i class="fas fa-home icon"></i> Return to Dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 