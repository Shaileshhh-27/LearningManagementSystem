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

// Get the lecture ID and course ID from URL parameters
$lectureId = $_GET['id'] ?? 0;
$courseId = $_GET['course_id'] ?? 0;

// Check if the lecture exists and belongs to this teacher's course
$stmt = $conn->prepare('
    SELECT l.* FROM lectures l
    JOIN courses c ON l.course_id = c.id
    WHERE l.id = :lecture_id AND c.teacher_id = :teacher_id
');
$stmt->bindValue(':lecture_id', $lectureId, SQLITE3_INTEGER);
$stmt->bindValue(':teacher_id', $_SESSION['user_id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$lecture = $result->fetchArray(SQLITE3_ASSOC);

if (!$lecture) {
    $_SESSION['error'] = 'Lecture not found or you do not have permission to delete it.';
    header('Location: dashboard.php');
    exit();
}

try {
    // Get video path to delete the file
    $videoPath = $lecture['video_path'];
    
    // Delete the lecture from the database
    $stmt = $conn->prepare('DELETE FROM lectures WHERE id = :lecture_id');
    $stmt->bindValue(':lecture_id', $lectureId, SQLITE3_INTEGER);
    
    if ($stmt->execute()) {
        // Also delete the video file if it exists
        if (file_exists($videoPath)) {
            unlink($videoPath);
        }
        
        $_SESSION['success'] = 'Lecture deleted successfully.';
    } else {
        throw new Exception('Failed to delete lecture from database.');
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

// Redirect back to the course page or upload lecture page
if ($courseId) {
    header('Location: upload_lecture.php?course_id=' . $courseId);
} else {
    header('Location: dashboard.php');
}
exit();
?> 