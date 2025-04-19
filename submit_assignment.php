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

$assignmentId = $_GET['id'] ?? 0;
$studentId = $_SESSION['user_id'];

// Fetch assignment details
$stmt = $conn->prepare('
    SELECT a.*, c.title as course_title 
    FROM assignments a 
    JOIN courses c ON a.course_id = c.id 
    WHERE a.id = :id
');
$stmt->bindValue(':id', $assignmentId, SQLITE3_INTEGER);
$result = $stmt->execute();
$assignment = $result->fetchArray(SQLITE3_ASSOC);

if (!$assignment) {
    header('Location: dashboard.php');
    exit();
}

// Check if student is enrolled in the course
$stmt = $conn->prepare('
    SELECT * FROM enrollments 
    WHERE student_id = :student_id AND course_id = :course_id
');
$stmt->bindValue(':student_id', $studentId, SQLITE3_INTEGER);
$stmt->bindValue(':course_id', $assignment['course_id'], SQLITE3_INTEGER);
$result = $stmt->execute();
if (!$result->fetchArray(SQLITE3_ASSOC)) {
    header('Location: dashboard.php');
    exit();
}

// Check if assignment is already submitted
$stmt = $conn->prepare('
    SELECT * FROM submissions 
    WHERE assignment_id = :assignment_id AND student_id = :student_id
');
$stmt->bindValue(':assignment_id', $assignmentId, SQLITE3_INTEGER);
$stmt->bindValue(':student_id', $studentId, SQLITE3_INTEGER);
$result = $stmt->execute();
$existingSubmission = $result->fetchArray(SQLITE3_ASSOC);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submissionText = $_POST['submission_text'] ?? '';
    
    // Handle file upload
    $submissionFile = null;
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/submissions/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate unique filename
        $fileName = uniqid() . '_' . basename($_FILES['submission_file']['name']);
        $targetPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['submission_file']['tmp_name'], $targetPath)) {
            $submissionFile = $targetPath;
        } else {
            $error = 'Failed to upload file';
        }
    }
    
    if (empty($submissionText) && empty($submissionFile)) {
        $error = 'Please provide either text submission or upload a file';
    } else {
        if ($existingSubmission) {
            // Update existing submission
            $stmt = $conn->prepare('
                UPDATE submissions 
                SET submission_text = :text, 
                    submission_file = COALESCE(:file, submission_file),
                    submitted_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ');
            $stmt->bindValue(':text', $submissionText, SQLITE3_TEXT);
            $stmt->bindValue(':file', $submissionFile, $submissionFile ? SQLITE3_TEXT : SQLITE3_NULL);
            $stmt->bindValue(':id', $existingSubmission['id'], SQLITE3_INTEGER);
        } else {
            // Create new submission
            $stmt = $conn->prepare('
                INSERT INTO submissions (
                    assignment_id, student_id, submission_text, submission_file
                ) VALUES (
                    :assignment_id, :student_id, :text, :file
                )
            ');
            $stmt->bindValue(':assignment_id', $assignmentId, SQLITE3_INTEGER);
            $stmt->bindValue(':student_id', $studentId, SQLITE3_INTEGER);
            $stmt->bindValue(':text', $submissionText, SQLITE3_TEXT);
            $stmt->bindValue(':file', $submissionFile, $submissionFile ? SQLITE3_TEXT : SQLITE3_NULL);
        }
        
        if ($stmt->execute()) {
            $success = 'Assignment submitted successfully!';
            // Add notification for assignment submission
            $_SESSION['notifications'][] = "Assignment '{$assignment['title']}' submitted successfully!";
            // Refresh existing submission data
            $stmt = $conn->prepare('
                SELECT * FROM submissions 
                WHERE assignment_id = :assignment_id AND student_id = :student_id
            ');
            $stmt->bindValue(':assignment_id', $assignmentId, SQLITE3_INTEGER);
            $stmt->bindValue(':student_id', $studentId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            $existingSubmission = $result->fetchArray(SQLITE3_ASSOC);
        } else {
            $error = 'Failed to submit assignment';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS - Submit Assignment</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            padding: 2rem;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h1 {
            color: #1a73e8;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .assignment-info {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
        }

        .assignment-info h2 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .assignment-info p {
            color: #666;
            margin-bottom: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: bold;
        }

        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
            min-height: 200px;
            resize: vertical;
        }

        textarea:focus {
            outline: none;
            border-color: #1a73e8;
        }

        .file-input {
            margin-top: 0.5rem;
        }

        .file-input::file-selector-button {
            background: #1a73e8;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 1rem;
        }

        .file-input::file-selector-button:hover {
            background: #1557b0;
        }

        button {
            background: #1a73e8;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
        }

        button:hover {
            background: #1557b0;
        }

        .error {
            color: #d93025;
            text-align: center;
            margin-bottom: 1rem;
        }

        .success {
            color: #0f9d58;
            text-align: center;
            margin-bottom: 1rem;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: #1a73e8;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .existing-submission {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }

        .existing-submission h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }

        .existing-submission p {
            color: #666;
            margin-bottom: 0.5rem;
        }

        .existing-submission .meta {
            font-size: 0.9rem;
            color: #888;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Submit Assignment</h1>

        <div class="assignment-info">
            <h2><?php echo htmlspecialchars($assignment['title']); ?></h2>
            <p><strong>Course:</strong> <?php echo htmlspecialchars($assignment['course_title']); ?></p>
            <p><strong>Due Date:</strong> <?php echo date('F j, Y, g:i A', strtotime($assignment['due_date'])); ?></p>
            <p><?php echo htmlspecialchars($assignment['description']); ?></p>
        </div>

        <?php if ($existingSubmission): ?>
            <div class="existing-submission">
                <h3>Previous Submission</h3>
                <?php if ($existingSubmission['submission_text']): ?>
                    <p><?php echo nl2br(htmlspecialchars($existingSubmission['submission_text'])); ?></p>
                <?php endif; ?>
                <?php if ($existingSubmission['submission_file']): ?>
                    <p><strong>Submitted File:</strong> <?php echo basename($existingSubmission['submission_file']); ?></p>
                <?php endif; ?>
                <div class="meta">
                    Submitted: <?php echo date('F j, Y, g:i A', strtotime($existingSubmission['submitted_at'])); ?>
                </div>
                <form method="POST" action="delete_submission.php">
                    <input type="hidden" name="submission_id" value="<?php echo $existingSubmission['id']; ?>">
                    <button type="submit" onclick="return confirm('Are you sure you want to delete this submission?');">Delete Submission</button>
                </form>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="submission_text">Text Submission</label>
                <textarea id="submission_text" name="submission_text" rows="10"><?php echo $existingSubmission['submission_text'] ?? ''; ?></textarea>
            </div>

            <div class="form-group">
                <label for="submission_file">File Submission (Optional)</label>
                <input type="file" id="submission_file" name="submission_file" class="file-input">
            </div>

            <button type="submit"><?php echo $existingSubmission ? 'Update Submission' : 'Submit Assignment'; ?></button>
        </form>

        <a href="course.php?id=<?php echo $assignment['course_id']; ?>" class="back-link">Back to Course</a>
    </div>
</body>
</html> 