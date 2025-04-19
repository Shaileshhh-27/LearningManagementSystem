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
    // Get the course details
    $stmt = $conn->prepare('SELECT title, teacher_id FROM courses WHERE id = :id');
    $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $course = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$course) {
        throw new Exception('Course not found');
    }
    
    if (!$course['teacher_id']) {
        throw new Exception('No teacher assigned to this course');
    }
    
    // Set the course status back to pending and remove teacher
    $stmt = $conn->prepare('UPDATE courses SET teacher_id = NULL, status = "pending" WHERE id = :id');
    $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Teacher unassigned from course "' . htmlspecialchars($course['title']) . '" successfully.';
    } else {
        throw new Exception('Failed to unassign teacher');
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header('Location: admin_dashboard.php?section=courses');
exit();
?> 