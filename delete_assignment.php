<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();

// Verify teacher authentication
if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'teacher') {
    header('Location: index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$teacherId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assignment_id'])) {
    $assignmentId = $_POST['assignment_id'];
    
    try {
        // Verify the assignment belongs to a course taught by this teacher
        $stmt = $conn->prepare('
            SELECT a.* FROM assignments a
            JOIN courses c ON a.course_id = c.id
            WHERE a.id = :assignment_id AND c.teacher_id = :teacher_id
        ');
        $stmt->bindValue(':assignment_id', $assignmentId, SQLITE3_INTEGER);
        $stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if ($result->fetchArray(SQLITE3_ASSOC)) {
            // Delete related submissions first
            $stmt = $conn->prepare('DELETE FROM submissions WHERE assignment_id = :assignment_id');
            $stmt->bindValue(':assignment_id', $assignmentId, SQLITE3_INTEGER);
            $stmt->execute();
            
            // Delete the assignment
            $stmt = $conn->prepare('DELETE FROM assignments WHERE id = :assignment_id');
            $stmt->bindValue(':assignment_id', $assignmentId, SQLITE3_INTEGER);
            $stmt->execute();
            
            $_SESSION['success_message'] = 'Assignment deleted successfully.';
        } else {
            $_SESSION['error_message'] = 'You do not have permission to delete this assignment.';
        }
    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error deleting assignment: ' . $e->getMessage();
    }
}

$returnTo = $_POST['return_to'] ?? 'dashboard';
$returnUrl = $returnTo === 'course' ? 'course.php?id=' . $courseId : 'dashboard.php';
header('Location: ' . $returnUrl);
exit(); 