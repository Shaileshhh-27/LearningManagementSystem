<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';

session_start();
$auth = new Auth();

// Only allow admin access
if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'admin') {
    header('Location: index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

$courseId = $_GET['id'] ?? 0;

try {
    // Start transaction
    $conn->exec('BEGIN');

    // Check if the course exists and get its details
    $stmt = $conn->prepare('SELECT title, status FROM courses WHERE id = :id');
    $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $course = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$course) {
        throw new Exception('Course not found');
    }
    
    // Delete enrollments first
    $stmt = $conn->prepare('DELETE FROM enrollments WHERE course_id = :course_id');
    $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
    $stmt->execute();
    
    // Delete related records (assignments, submissions, etc.)
    $stmt = $conn->prepare('DELETE FROM submissions WHERE assignment_id IN (SELECT id FROM assignments WHERE course_id = :course_id)');
    $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
    $stmt->execute();

    $stmt = $conn->prepare('DELETE FROM assignments WHERE course_id = :course_id');
    $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
    $stmt->execute();

    // Finally, delete the course
    $stmt = $conn->prepare('DELETE FROM courses WHERE id = :id');
    $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
    
    if ($stmt->execute()) {
        $conn->exec('COMMIT');
        $_SESSION['success'] = 'Course "' . htmlspecialchars($course['title']) . '" deleted successfully!';
    } else {
        throw new Exception('Failed to delete course');
    }
} catch (Exception $e) {
    $conn->exec('ROLLBACK');
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header('Location: admin_dashboard.php?section=courses');
exit(); 