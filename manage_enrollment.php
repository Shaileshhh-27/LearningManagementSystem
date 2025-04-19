<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';

session_start();
$auth = new Auth();

// Only teachers can manage enrollments
if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'teacher') {
    header('Location: index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

$action = $_POST['action'] ?? '';
$courseId = $_POST['course_id'] ?? 0;
$teacherId = $_SESSION['user_id'];

// Verify that the teacher owns this course
$stmt = $conn->prepare('SELECT * FROM courses WHERE id = :id AND teacher_id = :teacher_id');
$stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
$stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);
$result = $stmt->execute();
$course = $result->fetchArray(SQLITE3_ASSOC);

if (!$course) {
    $_SESSION['error'] = 'You do not have permission to manage this course.';
    header('Location: dashboard.php');
    exit();
}

switch ($action) {
    case 'remove':
        $studentId = $_POST['student_id'] ?? 0;
        
        // Remove the student from the course
        $stmt = $conn->prepare('DELETE FROM enrollments WHERE student_id = :student_id AND course_id = :course_id');
        $stmt->bindValue(':student_id', $studentId, SQLITE3_INTEGER);
        $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Student removed from the course successfully.';
            
            // Add notification for the student
            $stmt = $conn->prepare('SELECT username FROM users WHERE id = :id');
            $stmt->bindValue(':id', $studentId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $student = $result->fetchArray(SQLITE3_ASSOC);
            
            if ($student) {
                $_SESSION['notifications'][] = "You have been removed from the course: {$course['title']}";
            }
        } else {
            $_SESSION['error'] = 'Failed to remove student from the course.';
        }
        break;

    case 'add':
        $studentEmail = $_POST['student_email'] ?? '';
        
        // First, find the student by email
        $stmt = $conn->prepare('SELECT * FROM users WHERE email = :email AND role = "student" AND verified = 1');
        $stmt->bindValue(':email', $studentEmail, SQLITE3_TEXT);
        $result = $stmt->execute();
        $student = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!$student) {
            $_SESSION['error'] = 'Student not found or not verified.';
            break;
        }
        
        // Check if student is already enrolled
        $stmt = $conn->prepare('SELECT * FROM enrollments WHERE student_id = :student_id AND course_id = :course_id');
        $stmt->bindValue(':student_id', $student['id'], SQLITE3_INTEGER);
        $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if ($result->fetchArray(SQLITE3_ASSOC)) {
            $_SESSION['error'] = 'Student is already enrolled in this course.';
            break;
        }
        
        // Enroll the student
        $stmt = $conn->prepare('INSERT INTO enrollments (student_id, course_id) VALUES (:student_id, :course_id)');
        $stmt->bindValue(':student_id', $student['id'], SQLITE3_INTEGER);
        $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Student added to the course successfully.';
            // Add notification for the student
            $_SESSION['notifications'][] = "You have been enrolled in the course: {$course['title']}";
        } else {
            $_SESSION['error'] = 'Failed to add student to the course.';
        }
        break;

    default:
        $_SESSION['error'] = 'Invalid action.';
}

// Redirect back to the course page
header("Location: course.php?id=$courseId");
exit(); 