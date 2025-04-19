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
    
    // Set the course status to active and update start_date and end_date
    $currentDate = date('Y-m-d');
    $endDate = date('Y-m-d', strtotime("+90 days")); // Default 90 days validity
    
    // Get validity days from the course
    $stmt = $conn->prepare('SELECT validity_days FROM courses WHERE id = :id');
    $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $validityData = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($validityData && isset($validityData['validity_days'])) {
        $validityDays = $validityData['validity_days'];
        $endDate = date('Y-m-d', strtotime("+{$validityDays} days"));
    }
    
    $stmt = $conn->prepare('UPDATE courses SET status = "active", start_date = :start_date, end_date = :end_date WHERE id = :id');
    $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
    $stmt->bindValue(':start_date', $currentDate, SQLITE3_TEXT);
    $stmt->bindValue(':end_date', $endDate, SQLITE3_TEXT);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Course "' . htmlspecialchars($course['title']) . '" has been started! Validity period: ' . $currentDate . ' to ' . $endDate;
    } else {
        throw new Exception('Failed to start course');
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
}

header('Location: admin_dashboard.php?section=courses');
exit();
?> 