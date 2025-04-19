<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';

session_start();
$auth = new Auth();

// Only students and teachers should access the enrollment page
if (!$auth->isLoggedIn() || !in_array($auth->getUserRole(), ['student', 'teacher'])) {
    header('Location: dashboard.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$userId = $_SESSION['user_id'];

$courseId = $_GET['id'] ?? 0; // Define courseId from GET request if available

// Fetch courses that the student is not enrolled in
$stmt = $conn->prepare('SELECT c.id, c.title, c.description, u.username as teacher_name FROM courses c 
                        JOIN users u ON c.teacher_id = u.id 
                        WHERE c.id NOT IN (SELECT course_id FROM enrollments WHERE student_id = :student_id)');
$stmt->bindValue(':student_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

$courses = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $courses[] = $row;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $courseId = $_POST['course_id'] ?? 0;
    
    // First fetch the course details
    $stmt = $conn->prepare('SELECT c.*, u.username as teacher_name FROM courses c 
                           JOIN users u ON c.teacher_id = u.id 
                           WHERE c.id = :course_id');
    $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $course = $result->fetchArray(SQLITE3_ASSOC);

    if (!$course) {
        $error = 'Invalid course selected.';
    } else {
        // Enroll the student in the selected course
        $stmt = $conn->prepare('INSERT INTO enrollments (student_id, course_id) VALUES (:student_id, :course_id)');
        $stmt->bindValue(':student_id', $userId, SQLITE3_INTEGER);
        $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            $success = 'Successfully enrolled in the course!';
            // Add notification for course enrollment
            $_SESSION['notifications'][] = "You have enrolled in the course: {$course['title']}!";
            
            // Notify the teacher about the enrollment
            $teacherId = $course['teacher_id'];
            $_SESSION['notifications'][] = "Student with ID $userId has enrolled in your course: {$course['title']}";
        } else {
            $error = 'Failed to enroll in the course.';
        }
    }
}

// Fetch assignments
$stmt = $conn->prepare('SELECT a.id, a.title, a.description, a.due_date FROM assignments a WHERE a.course_id = :course_id');
$stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
$result = $stmt->execute();
$assignments = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $assignments[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Courses</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
        }

        .error, .success {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .error {
            background-color: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .success {
            background-color: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .course-list {
            list-style: none;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .course-item {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .course-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .course-item h3 {
            color: #2196F3;
            margin-bottom: 10px;
            font-size: 1.25rem;
        }

        .course-item p {
            color: #666;
            margin-bottom: 15px;
        }

        .teacher-info {
            color: #666;
            margin-bottom: 15px;
            font-style: italic;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #1976D2;
        }

        .actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        @media (max-width: 768px) {
            .course-list {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Available Courses</h1>
            <a href="dashboard.php" class="btn">Back to Dashboard</a>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (empty($courses)): ?>
            <div class="course-item" style="text-align: center;">
                <p>No available courses at the moment. Check back later!</p>
            </div>
        <?php else: ?>
            <ul class="course-list">
                <?php foreach ($courses as $course): ?>
                    <li class="course-item">
                        <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                        <p><?php echo htmlspecialchars($course['description']); ?></p>
                        <div class="teacher-info">
                            <i class="fas fa-chalkboard-teacher"></i>
                            Instructor: <?php echo htmlspecialchars($course['teacher_name']); ?>
                        </div>
                        <div class="actions">
                            <form method="POST" action="">
                                <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                <button type="submit" class="btn">Enroll</button>
                            </form>
                            <?php if ($auth->getUserRole() === 'teacher'): ?>
                                <form method="POST" action="delete_course.php" style="display:inline;">
                                    <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this course?');">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</body>
</html>