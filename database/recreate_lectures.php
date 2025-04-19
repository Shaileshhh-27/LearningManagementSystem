<?php
require_once '../config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Start transaction
    $conn->exec('BEGIN TRANSACTION');
    
    // 1. Backup existing data
    $lectures = [];
    $result = $conn->query('SELECT * FROM lectures');
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $lectures[] = $row;
    }
    
    // 2. Drop existing table
    $conn->exec('DROP TABLE IF EXISTS lectures');
    
    // 3. Create new table with correct schema
    $conn->exec('
        CREATE TABLE lectures (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER,
            title VARCHAR(100) NOT NULL,
            description TEXT,
            video_path VARCHAR(255) NOT NULL,
            thumbnail_path VARCHAR(255),
            upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES courses(id)
        )
    ');
    
    // 4. Restore data
    foreach ($lectures as $lecture) {
        $stmt = $conn->prepare('
            INSERT INTO lectures (
                id, course_id, title, description, video_path, 
                thumbnail_path, upload_date
            ) VALUES (
                :id, :course_id, :title, :description, :video_path,
                :thumbnail_path, :upload_date
            )
        ');
        
        $stmt->bindValue(':id', $lecture['id'], SQLITE3_INTEGER);
        $stmt->bindValue(':course_id', $lecture['course_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':title', $lecture['title'], SQLITE3_TEXT);
        $stmt->bindValue(':description', $lecture['description'], SQLITE3_TEXT);
        $stmt->bindValue(':video_path', $lecture['video_path'], SQLITE3_TEXT);
        $stmt->bindValue(':thumbnail_path', null, SQLITE3_NULL);
        $stmt->bindValue(':upload_date', $lecture['upload_date'], SQLITE3_TEXT);
        
        $stmt->execute();
    }
    
    // Commit transaction
    $conn->exec('COMMIT');
    echo "Lectures table recreated successfully with thumbnail_path column.\n";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->exec('ROLLBACK');
    echo "Error recreating lectures table: " . $e->getMessage() . "\n";
}
?> 