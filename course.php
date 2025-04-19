<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';

session_start();
$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

$courseId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];
$role = $auth->getUserRole();

// Handle enrollment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'remove') {
        $studentId = $_POST['student_id'];
        $courseId = $_POST['course_id'];
        
        $stmt = $conn->prepare('DELETE FROM enrollments WHERE student_id = :student_id AND course_id = :course_id');
        $stmt->bindValue(':student_id', $studentId, SQLITE3_INTEGER);
        $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Student removed from course successfully.';
        } else {
            $_SESSION['error'] = 'Failed to remove student from course.';
        }
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $courseId);
        exit();
    }
    
    if ($action === 'add') {
        $studentEmail = $_POST['student_email'];
        $courseId = $_POST['course_id'];
        
        // First find the student by email
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = :email AND role = "student"');
        $stmt->bindValue(':email', $studentEmail, SQLITE3_TEXT);
        $result = $stmt->execute();
        $student = $result->fetchArray(SQLITE3_ASSOC);
        
        if ($student) {
            // Check if already enrolled
            $stmt = $conn->prepare('SELECT * FROM enrollments WHERE student_id = :student_id AND course_id = :course_id');
            $stmt->bindValue(':student_id', $student['id'], SQLITE3_INTEGER);
            $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($result->fetchArray(SQLITE3_ASSOC)) {
                $_SESSION['error'] = 'Student is already enrolled in this course.';
            } else {
                // Enroll the student
                $stmt = $conn->prepare('INSERT INTO enrollments (student_id, course_id) VALUES (:student_id, :course_id)');
                $stmt->bindValue(':student_id', $student['id'], SQLITE3_INTEGER);
                $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Student enrolled successfully.';
                } else {
                    $_SESSION['error'] = 'Failed to enroll student.';
                }
            }
        } else {
            $_SESSION['error'] = 'Student not found with the provided email.';
        }
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?id=' . $courseId);
        exit();
    }
}

// Fetch course details
$stmt = $conn->prepare('SELECT * FROM courses WHERE id = :id');
$stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
$result = $stmt->execute();
$course = $result->fetchArray(SQLITE3_ASSOC);

// Check if the course exists
if (!$course) {
    echo '<h2>Course Not Found</h2>';
    echo '<p>The requested course does not exist. Please check the URL or contact support.</p>';
    exit();
}

// Check if user has access to this course
$hasAccess = false;
if ($role === 'teacher') {
    $hasAccess = $course['teacher_id'] == $userId;
} else {
    $stmt = $conn->prepare('SELECT * FROM enrollments WHERE course_id = :course_id AND student_id = :student_id');
    $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
    $stmt->bindValue(':student_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $hasAccess = $result->fetchArray(SQLITE3_ASSOC) !== false;
}

if (!$hasAccess) {
    header('Location: dashboard.php');
    exit();
}

// Fetch lectures
$stmt = $conn->prepare('SELECT * FROM lectures WHERE course_id = :course_id ORDER BY upload_date DESC');
$stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
$result = $stmt->execute();
$lectures = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $lectures[] = $row;
}

// Fetch assignments
$stmt = $conn->prepare('SELECT * FROM assignments WHERE course_id = :course_id ORDER BY due_date ASC');
$stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
$result = $stmt->execute();
$assignments = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $assignments[] = $row;
}

// Fetch enrolled students
$stmt = $conn->prepare('SELECT u.id, u.username, u.email FROM enrollments e JOIN users u ON e.student_id = u.id WHERE e.course_id = :course_id');
$stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
$result = $stmt->execute();
$students = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $students[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS - <?php echo htmlspecialchars($course['title']); ?></title>
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
            color: #333;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #1a73e8;
            font-size: 2rem;
            margin: 0;
        }

        .actions {
            display: flex;
            gap: 1rem;
        }

        .section {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .section h2 {
            color: #1a73e8;
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section h2 .icon {
            font-size: 1.2em;
        }

        .item-list {
            list-style: none;
        }

        .item {
            padding: 1.5rem;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .item:hover {
            border-color: #1a73e8;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .item h3 {
            color: #1a73e8;
            margin-bottom: 0.5rem;
            font-size: 1.2rem;
        }

        .item p {
            color: #666;
            margin-bottom: 1rem;
        }

        .item .meta {
            color: #888;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #1a73e8;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            transition: background 0.3s ease;
        }

        .btn:hover {
            background: #1557b0;
        }

        .back-link {
            color: #1a73e8;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        th {
            background: #f8f9fa;
            color: #1a73e8;
            font-weight: 500;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-card h3 {
            color: #666;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .stat-card .number {
            color: #1a73e8;
            font-size: 2rem;
            font-weight: bold;
        }

        .teacher-actions {
            margin-top: 1.5rem;
            display: flex;
            gap: 1rem;
        }

        .logout {
            color: #d93025;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            transition: background 0.3s ease;
        }

        .logout:hover {
            background: #fee2e2;
        }

        .video-container {
            position: relative;
            width: 100%;
            margin: 15px 0;
            background: #000;
            border-radius: 8px;
            overflow: hidden;
        }

        video {
            display: block;
            max-width: 100%;
            border-radius: 8px;
        }

        video::-webkit-media-controls {
            background-color: rgba(0, 0, 0, 0.5);
        }

        .item {
            overflow: hidden;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

        <div class="header">
            <h1><?php echo htmlspecialchars($course['title']); ?></h1>
            <?php if ($role === 'teacher' && $course['teacher_id'] == $userId): ?>
                <div class="actions">
                    <a href="upload_lecture.php?course_id=<?php echo $courseId; ?>" class="btn">Upload Lecture</a>
                    <a href="create_assignment.php?course_id=<?php echo $courseId; ?>" class="btn">Create Assignment</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message" style="background: #e8f5e9; color: #2e7d32; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message" style="background: #ffebee; color: #c62828; padding: 15px; margin-bottom: 20px; border-radius: 4px;">
                <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-card">
                <h3>Enrolled Students</h3>
                <div class="number"><?php echo count($students); ?></div>
            </div>
            <div class="stat-card">
                <h3>Assignments</h3>
                <div class="number"><?php echo count($assignments); ?></div>
            </div>
            <div class="stat-card">
                <h3>Lectures</h3>
                <div class="number"><?php echo count($lectures); ?></div>
            </div>
        </div>

        <div class="section">
            <h2><span class="icon">📚</span> Course Description</h2>
            <p><?php echo htmlspecialchars($course['description']); ?></p>
        </div>

        <div class="section">
            <h2><span class="icon">📺</span> Lectures</h2>
            <?php if (empty($lectures)): ?>
                <p>No lectures available yet.</p>
            <?php else: ?>
                <ul class="item-list">
                    <?php foreach ($lectures as $lecture): ?>
                        <li class="item">
                            <h3><?php echo htmlspecialchars($lecture['title']); ?></h3>
                            <div class="video-container">
                                <video width="100%" controls preload="metadata" poster="<?php echo str_replace('.mp4', '_thumb.jpg', htmlspecialchars($lecture['video_path'])); ?>">
                                    <source src="<?php echo htmlspecialchars($lecture['video_path']); ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                            <p><?php echo htmlspecialchars($lecture['description']); ?></p>
                            <div class="meta">
                                Uploaded: <?php echo date('F j, Y', strtotime($lecture['upload_date'])); ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2><span class="icon">📝</span> Assignments</h2>
            <?php if (empty($assignments)): ?>
                <p>No assignments available yet.</p>
            <?php else: ?>
                <ul class="item-list">
                    <?php foreach ($assignments as $assignment): ?>
                        <li class="item">
                            <h3><?php echo htmlspecialchars($assignment['title']); ?></h3>
                            <p><?php echo htmlspecialchars($assignment['description']); ?></p>
                            <?php if (!empty($assignment['assignment_file'])): ?>
                                <a href="<?php echo htmlspecialchars($assignment['assignment_file']); ?>" class="btn" download>Download Assignment File</a>
                            <?php endif; ?>
                            <div class="meta">
                                Due: <?php echo date('F j, Y', strtotime($assignment['due_date'])); ?>
                            </div>
                            <?php if ($role === 'student'): ?>
                                <a href="submit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn">Submit Assignment</a>
                            <?php else: ?>
                                <a href="view_submissions.php?id=<?php echo $assignment['id']; ?>" class="btn">View Submissions</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <?php if ($role === 'teacher'): ?>
        <div class="section">
            <h2><span class="icon">👥</span> Enrolled Students</h2>
            <?php if (empty($students)): ?>
                <p>No students enrolled yet.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($student['username']); ?></td>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                    <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to remove this student from the course?');">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <div class="add-student-section" style="margin-top: 20px;">
                    <h3>Add Student to Course</h3>
                    <form method="POST" class="add-student-form">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="course_id" value="<?php echo $courseId; ?>">
                        <div class="form-group" style="display: flex; gap: 10px; align-items: flex-end;">
                            <div style="flex-grow: 1;">
                                <label for="student_email">Student Email:</label>
                                <input type="email" id="student_email" name="student_email" required 
                                       placeholder="Enter student's email">
                            </div>
                            <button type="submit" class="btn">Add Student</button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>