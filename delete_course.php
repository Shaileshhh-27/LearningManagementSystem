<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';

session_start();
$auth = new Auth();

// Check if user is logged in and is a teacher
if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'teacher') {
    header('Location: index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

$courseId = $_POST['id'] ?? 0;

// Check if the course belongs to the user
$stmt = $conn->prepare('SELECT teacher_id FROM courses WHERE id = :course_id');
$stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
$result = $stmt->execute();
$course = $result->fetchArray(SQLITE3_ASSOC);

if ($course && $course['teacher_id'] == $_SESSION['user_id']) {
    // Prepare and execute the delete statement
    $stmt = $conn->prepare('DELETE FROM courses WHERE id = :course_id');
    $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Course deleted successfully!';
    } else {
        $_SESSION['error'] = 'Failed to delete the course.';
    }
} else {
    $_SESSION['error'] = 'You do not have permission to delete this course.';
}

header('Location: browse_courses.php');
exit();
?> 