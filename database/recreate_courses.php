<?php
require_once '../config/database.php';

function executeSQL($conn, $sql, $description) {
    $result = $conn->exec($sql);
    if ($result === false) {
        throw new Exception("Error in $description: " . $conn->lastErrorMsg());
    }
    echo "Successfully executed: $description<br>";
}

function makeUniqueCourseTitle($conn, $title, $teacherId) {
    $counter = 1;
    $originalTitle = $title;
    
    while (true) {
        // Check if this title exists for this teacher
        $stmt = $conn->prepare('SELECT COUNT(*) as count FROM courses WHERE title = :title AND teacher_id = :teacher_id');
        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
        
        if ($count == 0) {
            return $title;
        }
        
        $title = $originalTitle . ' (' . $counter . ')';
        $counter++;
    }
}

try {
    $db = new Database();
    $conn = $db->getConnection();

    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; background: #fff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);'>";
    echo "<h2>Database Update Progress</h2>";

    // Begin transaction
    executeSQL($conn, 'BEGIN TRANSACTION', 'Begin Transaction');

    // Backup existing courses
    $stmt = $conn->prepare('SELECT * FROM courses');
    if (!$stmt) {
        throw new Exception("Error preparing SELECT statement: " . $conn->lastErrorMsg());
    }
    $result = $stmt->execute();
    $courses = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $courses[] = $row;
    }
    echo "Backed up " . count($courses) . " existing courses<br>";

    // Drop existing tables
    $conn->exec('DROP TABLE IF EXISTS submissions');
    $conn->exec('DROP TABLE IF EXISTS assignments');
    $conn->exec('DROP TABLE IF EXISTS enrollments');
    $conn->exec('DROP TABLE IF EXISTS courses');

    // Create courses table
    $conn->exec('
        CREATE TABLE courses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            teacher_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            start_date DATE DEFAULT CURRENT_DATE,
            end_date DATE DEFAULT (date("now", "+3 months")),
            status VARCHAR(20) DEFAULT "active" CHECK (status IN ("active", "inactive", "expired")),
            FOREIGN KEY (teacher_id) REFERENCES users(id),
            UNIQUE(title, teacher_id)
        )
    ');

    // Create enrollments table
    $conn->exec('
        CREATE TABLE enrollments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            course_id INTEGER NOT NULL,
            enrollment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES users(id),
            FOREIGN KEY (course_id) REFERENCES courses(id)
        )
    ');

    // Create assignments table
    $conn->exec('
        CREATE TABLE assignments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            course_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            due_date DATETIME NOT NULL,
            assignment_file TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (course_id) REFERENCES courses(id)
        )
    ');

    // Create submissions table
    $conn->exec('
        CREATE TABLE submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            assignment_id INTEGER NOT NULL,
            student_id INTEGER NOT NULL,
            submission_text TEXT,
            submission_file TEXT,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            grade FLOAT,
            feedback TEXT,
            FOREIGN KEY (assignment_id) REFERENCES assignments(id),
            FOREIGN KEY (student_id) REFERENCES users(id)
        )
    ');

    // Track restored courses to handle duplicates
    $restoredCourses = [];

    // Restore backed up courses
    foreach ($courses as $course) {
        // Make the title unique for this teacher
        $uniqueTitle = makeUniqueCourseTitle($conn, $course['title'], $course['teacher_id']);
        
        $stmt = $conn->prepare('
            INSERT INTO courses (
                id, title, description, teacher_id, 
                start_date, end_date, status, created_at
            ) VALUES (
                :id, :title, :description, :teacher_id,
                :start_date, :end_date, :status, :created_at
            )
        ');
        
        if (!$stmt) {
            throw new Exception("Error preparing INSERT statement: " . $conn->lastErrorMsg());
        }
        
        $stmt->bindValue(':id', $course['id'], SQLITE3_INTEGER);
        $stmt->bindValue(':title', $uniqueTitle, SQLITE3_TEXT);
        $stmt->bindValue(':description', $course['description'], SQLITE3_TEXT);
        $stmt->bindValue(':teacher_id', $course['teacher_id'], SQLITE3_INTEGER);
        $stmt->bindValue(':start_date', $course['start_date'] ?? $currentDate, SQLITE3_TEXT);
        $stmt->bindValue(':end_date', $course['end_date'] ?? $endDate, SQLITE3_TEXT);
        $stmt->bindValue(':status', $course['status'] ?? 'active', SQLITE3_TEXT);
        $stmt->bindValue(':created_at', $course['created_at'] ?? $currentDate, SQLITE3_TEXT);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to restore course: " . $conn->lastErrorMsg());
        }
        
        if ($uniqueTitle !== $course['title']) {
            echo "Restored course with modified title: " . htmlspecialchars($course['title']) . " → " . htmlspecialchars($uniqueTitle) . "<br>";
        } else {
            echo "Restored course: " . htmlspecialchars($uniqueTitle) . "<br>";
        }
    }

    // Verify the table structure
    $result = $conn->query("PRAGMA table_info(courses)");
    echo "<h3>New Table Structure:</h3>";
    echo "<pre>";
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        print_r($row);
        echo "\n";
    }
    echo "</pre>";

    // Show final course list
    $result = $conn->query("SELECT title, teacher_id FROM courses ORDER BY teacher_id, title");
    echo "<h3>Final Course List:</h3>";
    echo "<pre>";
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        echo "Teacher ID: " . $row['teacher_id'] . " - Course: " . htmlspecialchars($row['title']) . "\n";
    }
    echo "</pre>";

    // Commit transaction
    executeSQL($conn, 'COMMIT', 'Commit Transaction');
    
    echo "<div style='color: #4CAF50; margin: 20px 0;'>Database update completed successfully!</div>";
    echo "<a href='../admin_dashboard.php' style='display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Return to Dashboard</a>";
    echo "</div>";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->exec('ROLLBACK');
    echo "<div style='color: #f44336; margin: 20px 0;'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<a href='../admin_dashboard.php' style='display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;'>Return to Dashboard</a>";
    echo "</div>";
}
?> 