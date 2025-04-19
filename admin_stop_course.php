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
    $stmt = $conn->prepare('SELECT title, status FROM courses WHERE id = :id');
    $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $course = $result->fetchArray(SQLITE3_ASSOC);
    
    if (!$course) {
        throw new Exception('Course not found');
    }
    
    // Update the course status to inactive
    $stmt = $conn->prepare('UPDATE courses SET status = "inactive" WHERE id = :id');
    $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Course "' . htmlspecialchars($course['title']) . '" has been stopped.';
    } else {
        throw new Exception('Failed to stop course');
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header('Location: admin_dashboard.php?section=courses');
exit();
?> 