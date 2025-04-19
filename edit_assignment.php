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

$assignmentId = $_GET['id'] ?? 0;
$teacherId = $_SESSION['user_id'];

// Fetch assignment details
$stmt = $conn->prepare('
    SELECT a.*, c.title as course_title, c.id as course_id 
    FROM assignments a 
    JOIN courses c ON a.course_id = c.id 
    WHERE a.id = :id AND c.teacher_id = :teacher_id
');
$stmt->bindValue(':id', $assignmentId, SQLITE3_INTEGER);
$stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);
$result = $stmt->execute();
$assignment = $result->fetchArray(SQLITE3_ASSOC);

if (!$assignment) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $dueDate = $_POST['due_date'] ?? '';
    
    if (empty($title) || empty($description) || empty($dueDate)) {
        $error = 'All fields are required.';
    } else {
        try {
            $stmt = $conn->prepare('
                UPDATE assignments 
                SET title = :title, description = :description, due_date = :due_date 
                WHERE id = :id
            ');
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':description', $description, SQLITE3_TEXT);
            $stmt->bindValue(':due_date', $dueDate, SQLITE3_TEXT);
            $stmt->bindValue(':id', $assignmentId, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                $_SESSION['success_message'] = 'Assignment updated successfully.';
                header('Location: dashboard.php');
                exit();
            } else {
                $error = 'Failed to update assignment.';
            }
        } catch (Exception $e) {
            $error = 'Error updating assignment: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Assignment</title>
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
            color: #333;
            margin-bottom: 20px;
            text-align: center;
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
            font-size: 16px;
        }

        button:hover {
            background: #1976D2;
        }

        .error {
            color: #f44336;
            margin-bottom: 20px;
            padding: 10px;
            background: #ffebee;
            border-radius: 4px;
        }

        .success {
            color: #4CAF50;
            margin-bottom: 20px;
            padding: 10px;
            background: #E8F5E9;
            border-radius: 4px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #2196F3;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

        <h1>Edit Assignment</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="course">Course</label>
                <input type="text" id="course" value="<?php echo htmlspecialchars($assignment['course_title']); ?>" readonly>
            </div>

            <div class="form-group">
                <label for="title">Assignment Title</label>
                <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($assignment['title']); ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" required><?php echo htmlspecialchars($assignment['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="due_date">Due Date</label>
                <input type="datetime-local" id="due_date" name="due_date" 
                       value="<?php echo date('Y-m-d\TH:i', strtotime($assignment['due_date'])); ?>" required>
            </div>

            <button type="submit">Update Assignment</button>
        </form>
    </div>
</body>
</html> 