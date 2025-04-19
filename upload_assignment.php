<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();

if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'teacher') {
    header('Location: index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$teacherId = $_SESSION['user_id'];

// Get teacher's courses
$stmt = $conn->prepare('SELECT * FROM courses WHERE teacher_id = :teacher_id');
$stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);
$result = $stmt->execute();

$courses = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $courses[] = $row;
}

// Pre-select course if provided in URL
$selectedCourseId = $_GET['course_id'] ?? '';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId = $_POST['course_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $dueDate = $_POST['due_date'] ?? '';
    
    if (empty($courseId) || empty($title) || empty($dueDate)) {
        $error = 'Course, title and due date are required';
    } else {
        // Verify the course belongs to this teacher
        $stmt = $conn->prepare('SELECT id FROM courses WHERE id = :id AND teacher_id = :teacher_id');
        $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
        $stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        
        if ($result->fetchArray(SQLITE3_ASSOC)) {
            $stmt = $conn->prepare('
                INSERT INTO assignments (course_id, title, description, due_date) 
                VALUES (:course_id, :title, :description, :due_date)
            ');
            $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':description', $description, SQLITE3_TEXT);
            $stmt->bindValue(':due_date', $dueDate, SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                $success = 'Assignment created successfully!';
                // Clear form data after successful submission
                $selectedCourseId = '';
            } else {
                $error = 'Failed to create assignment';
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
    <title>Upload Assignment</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            background: #f4f4f4;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #666;
        }

        input[type="text"],
        input[type="datetime-local"],
        select,
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        textarea {
            height: 150px;
            resize: vertical;
        }

        button {
            background: #2196F3;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }

        button:hover {
            background: #1976D2;
        }

        .error {
            color: #dc3545;
            background: #f8d7da;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .success {
            color: #28a745;
            background: #d4edda;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .nav-links {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .nav-link {
            color: #2196F3;
            text-decoration: none;
        }

        .nav-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-links">
            <a href="teacher_dashboard.php" class="nav-link">← Back to Dashboard</a>
        </div>

        <h1>Upload Assignment</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success">
                <?php echo htmlspecialchars($success); ?>
                <br>
                <a href="teacher_dashboard.php" class="nav-link">Return to Dashboard</a>
            </div>
        <?php endif; ?>

        <?php if (empty($courses)): ?>
            <div class="error">You don't have any courses assigned to you. Please contact the administrator.</div>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label for="course_id">Select Course</label>
                    <select id="course_id" name="course_id" required>
                        <option value="">Choose a course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['id']; ?>" 
                                    <?php echo $course['id'] == $selectedCourseId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="title">Assignment Title</label>
                    <input type="text" id="title" name="title" required>
                </div>

                <div class="form-group">
                    <label for="description">Assignment Description</label>
                    <textarea id="description" name="description"></textarea>
                </div>

                <div class="form-group">
                    <label for="due_date">Due Date</label>
                    <input type="datetime-local" id="due_date" name="due_date" required>
                </div>

                <button type="submit">Create Assignment</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html> 