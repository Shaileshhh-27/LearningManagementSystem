<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';

session_start();
$auth = new Auth();

if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'student') {
    header('Location: index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

$submissionId = $_POST['submission_id'] ?? 0;

// Fetch submission details
$stmt = $conn->prepare('SELECT * FROM submissions WHERE id = :id');
$stmt->bindValue(':id', $submissionId, SQLITE3_INTEGER);
$result = $stmt->execute();
$submission = $result->fetchArray(SQLITE3_ASSOC);

if (!$submission) {
    $_SESSION['error'] = 'Submission not found.';
    header('Location: dashboard.php');
    exit();
}

// Check if the submission belongs to the student
if ($submission['student_id'] !== $_SESSION['user_id']) {
    $_SESSION['error'] = 'You do not have permission to delete this submission.';
    header('Location: dashboard.php');
    exit();
}

// Fetch assignment details to check due date
$stmt = $conn->prepare('SELECT due_date FROM assignments WHERE id = :assignment_id');
$stmt->bindValue(':assignment_id', $submission['assignment_id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$assignment = $result->fetchArray(SQLITE3_ASSOC);

if (!$assignment) {
    $_SESSION['error'] = 'Assignment not found.';
    header('Location: dashboard.php');
    exit();
}

// Check if the current date is before the due date
if (strtotime($assignment['due_date']) < time()) {
    $_SESSION['error'] = 'You cannot delete this submission after the due date.';
    header('Location: dashboard.php');
    exit();
}

// Delete the submission
$stmt = $conn->prepare('DELETE FROM submissions WHERE id = :id');
$stmt->bindValue(':id', $submissionId, SQLITE3_INTEGER);
if ($stmt->execute()) {
    $_SESSION['success'] = 'Submission deleted successfully!';
} else {
    $_SESSION['error'] = 'Failed to delete submission.';
}

header('Location: dashboard.php');
exit();
?> 