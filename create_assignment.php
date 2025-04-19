<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';
require_once 'config/config.php';

session_start();
$auth = new Auth();

if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'teacher') {
    header('Location: index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

$courseId = $_GET['course_id'] ?? 0;
$teacherId = $_SESSION['user_id'];

// If no course_id provided, get all courses for the teacher
if (!$courseId) {
    $stmt = $conn->prepare('SELECT * FROM courses WHERE teacher_id = :teacher_id');
    $stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $courses = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $courses[] = $row;
    }
} else {
    // Verify that the teacher owns this course
    $stmt = $conn->prepare('SELECT * FROM courses WHERE id = :id AND teacher_id = :teacher_id');
    $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
    $stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $course = $result->fetchArray(SQLITE3_ASSOC);

    if (!$course) {
        header('Location: dashboard.php');
        exit();
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $dueDate = $_POST['due_date'] ?? '';
    $selectedCourseId = $_POST['course_id'] ?? $courseId;
    
    if (empty($selectedCourseId) || empty($title) || empty($dueDate)) {
        $error = 'Course, title and due date are required';
    } else {
        // Verify the course belongs to this teacher
        $stmt = $conn->prepare('SELECT id FROM courses WHERE id = :id AND teacher_id = :teacher_id');
        $stmt->bindValue(':id', $selectedCourseId, SQLITE3_INTEGER);
        $stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if ($result->fetchArray(SQLITE3_ASSOC)) {
            // Handle file upload
            $assignmentFile = null;
            if (isset($_FILES['assignment_file']) && $_FILES['assignment_file']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/assignments/';
                
                // Create directory if it doesn't exist
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                // Generate unique filename
                $fileName = uniqid() . '_' . basename($_FILES['assignment_file']['name']);
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['assignment_file']['tmp_name'], $targetPath)) {
                    $assignmentFile = $targetPath;
                } else {
                    $error = 'Failed to upload file';
                }
            }

            if (!$error) {
                $stmt = $conn->prepare('
                    INSERT INTO assignments (
                        course_id, title, description, due_date
                    ) VALUES (
                        :course_id, :title, :description, :due_date
                    )
                ');
                $stmt->bindValue(':course_id', $selectedCourseId, SQLITE3_INTEGER);
                $stmt->bindValue(':title', $title, SQLITE3_TEXT);
                $stmt->bindValue(':description', $description, SQLITE3_TEXT);
                $stmt->bindValue(':due_date', $dueDate, SQLITE3_TEXT);
                
                if ($stmt->execute()) {
                    $success = 'Assignment created successfully!';
                } else {
                    $error = 'Failed to create assignment';
                }
            }
        } else {
            $error = 'Invalid course selected';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS - Create Assignment</title>
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

        .course-info {
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #eee;
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

        input, textarea, select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        textarea {
            min-height: 150px;
            resize: vertical;
        }

        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: #1a73e8;
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

        .nav-links {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .nav-link {
            color: #1a73e8;
            text-decoration: none;
        }

        .nav-link:hover {
            text-decoration: underline;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">← Back to Dashboard</a>
            <?php if ($courseId): ?>
                <a href="course.php?id=<?php echo $courseId; ?>" class="nav-link">Back to Course</a>
            <?php endif; ?>
        </div>

        <h1>Create New Assignment</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success">
                <?php echo htmlspecialchars($success); ?>
                <br>
                <a href="dashboard.php" class="nav-link">Return to Dashboard</a>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <?php if (!$courseId): ?>
                <div class="form-group">
                    <label for="course_id">Select Course</label>
                    <select id="course_id" name="course_id" required>
                        <option value="">Choose a course</option>
                        <?php foreach ($courses as $c): ?>
                            <option value="<?php echo $c['id']; ?>">
                                <?php echo htmlspecialchars($c['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php else: ?>
                <div class="course-info">
                    <h2><?php echo htmlspecialchars($course['title']); ?></h2>
                </div>
                <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="title">Assignment Title</label>
                <input type="text" id="title" name="title" required>
            </div>

            <div class="form-group">
                <label for="description">Assignment Description</label>
                <textarea id="description" name="description" rows="6"></textarea>
            </div>

            <div class="form-group">
                <label for="due_date">Due Date</label>
                <input type="datetime-local" id="due_date" name="due_date" required>
            </div>

            <div class="form-group">
                <label for="assignment_file">Assignment File (Optional)</label>
                <input type="file" id="assignment_file" name="assignment_file" class="file-input">
            </div>

            <button type="submit">Create Assignment</button>
        </form>
    </div>
</body>
</html> 