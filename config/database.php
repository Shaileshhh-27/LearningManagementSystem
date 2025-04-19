<?php
require_once __DIR__ . '/config.php';

class Database {
    private $db;

    public function __construct() {
        try {
            // Create database directory if it doesn't exist
            $dbDir = dirname(DB_PATH);
            if (!file_exists($dbDir)) {
                mkdir($dbDir, 0777, true);
            }
            
            $this->db = new SQLite3(DB_PATH);
            $this->db->busyTimeout(5000); // Set timeout to 5000 milliseconds
            $this->createTables();
        } catch (Exception $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }

    private function createTables() {
        // Users table - Added verified column
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                role VARCHAR(20) NOT NULL,
                verified INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        // Courses table
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS courses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(100) NOT NULL,
                description TEXT,
                teacher_id INTEGER,
                price DECIMAL(10,2) DEFAULT 0,
                validity_days INTEGER DEFAULT 90,
                min_students INTEGER DEFAULT 5,
                max_students INTEGER DEFAULT 30,
                status VARCHAR(20) DEFAULT "pending",
                start_date DATE,
                end_date DATE,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (teacher_id) REFERENCES users(id)
            )
        ');

        // Course enrollments
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS enrollments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                student_id INTEGER,
                course_id INTEGER,
                status VARCHAR(20) DEFAULT "pending",
                fees_paid INTEGER DEFAULT 0,
                enrollment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES users(id),
                FOREIGN KEY (course_id) REFERENCES courses(id)
            )
        ');

        // Lectures table
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS lectures (
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

        // Assignments table - Changed to teacher-student assignments
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS assignments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                student_id INTEGER,
                teacher_id INTEGER,
                assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES users(id),
                FOREIGN KEY (teacher_id) REFERENCES users(id)
            )
        ');

        // Course assignments table
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS course_assignments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                course_id INTEGER,
                title VARCHAR(100) NOT NULL,
                description TEXT,
                due_date DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (course_id) REFERENCES courses(id)
            )
        ');

        // Submissions table
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS submissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                assignment_id INTEGER,
                student_id INTEGER,
                submission_file VARCHAR(255),
                submission_text TEXT,
                submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                grade FLOAT,
                feedback TEXT,
                FOREIGN KEY (assignment_id) REFERENCES course_assignments(id),
                FOREIGN KEY (student_id) REFERENCES users(id)
            )
        ');
    }

    public function getConnection() {
        return $this->db;
    }
}
?>