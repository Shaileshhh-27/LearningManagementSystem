<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';

session_start();
$auth = new Auth();

// Only admin can create courses
if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'admin') {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $teacherId = $_SESSION['user_id'];

    if (empty($title)) {
        $error = 'Course title is required';
    } else {
        $db = new Database();
        $conn = $db->getConnection();

        $stmt = $conn->prepare('INSERT INTO courses (title, description, teacher_id) VALUES (:title, :description, :teacher_id)');
        $stmt->bindValue(':title', $title, SQLITE3_TEXT);
        $stmt->bindValue(':description', $description, SQLITE3_TEXT);
        $stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);

        if ($stmt->execute()) {
            $success = 'Course created successfully!';
        } else {
            $error = 'Failed to create course';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS - Create Course</title>
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: bold;
        }

        input, textarea {
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

        input:focus, textarea:focus {
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
            gap: 20px;
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

        <h1>Create New Course</h1>

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

        <form method="POST" action="">
            <div class="form-group">
                <label for="title">Course Title</label>
                <input type="text" id="title" name="title" required>
            </div>

            <div class="form-group">
                <label for="description">Course Description</label>
                <textarea id="description" name="description" rows="6"></textarea>
            </div>

            <button type="submit">Create Course</button>
        </form>
    </div>
</body>
</html> 