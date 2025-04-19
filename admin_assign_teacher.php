<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';

// Only start session if one hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();

// Only allow admin access
if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'admin') {
    header('Location: index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

$courseId = $_GET['id'] ?? 0;
$preselectedTeacherId = $_GET['teacher_id'] ?? null;

// Verify the course exists and doesn't have a teacher assigned
try {
    $stmt = $conn->prepare('
        SELECT c.*, 
               (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrolled_students
        FROM courses c 
        WHERE c.id = :id
    ');
    $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $course = $result->fetchArray(SQLITE3_ASSOC);

    if (!$course) {
        $_SESSION['error'] = 'Course not found.';
        header('Location: admin_dashboard.php?section=courses');
        exit();
    }

    // If the course already has a teacher assigned, redirect back
    if (!empty($course['teacher_id'])) {
        $_SESSION['error'] = 'This course already has a teacher assigned.';
        header('Location: admin_dashboard.php?section=courses');
        exit();
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Database error: ' . $e->getMessage();
    header('Location: admin_dashboard.php?section=courses');
    exit();
}

// Fetch all available teachers
$stmt = $conn->prepare('SELECT id, username, email FROM users WHERE role = "teacher" AND verified = 1');
$result = $stmt->execute();
$teachers = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $teachers[] = $row;
}

// Get teacher name if preselected
$preselectedTeacherName = '';
if ($preselectedTeacherId) {
    foreach ($teachers as $teacher) {
        if ($teacher['id'] == $preselectedTeacherId) {
            $preselectedTeacherName = $teacher['username'];
            break;
        }
    }
}

// If a teacher ID is provided and it's a GET request, automatically assign the teacher
if ($preselectedTeacherId && $_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['auto_assign'])) {
    // Verify the teacher exists
    $teacherExists = false;
    foreach ($teachers as $teacher) {
        if ($teacher['id'] == $preselectedTeacherId) {
            $teacherExists = true;
            break;
        }
    }
    
    if ($teacherExists) {
        // Auto-submit the form with the preselected teacher
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['teacher_id'] = $preselectedTeacherId;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacherId = $_POST['teacher_id'] ?? '';
    
    if (empty($teacherId)) {
        $error = 'Please select a teacher to assign.';
    } else {
        try {
            // Check if the course has enough students to start
            $minStudentsReached = ($course['enrolled_students'] >= $course['min_students']);
            
            // Update the course with the assigned teacher
            $stmt = $conn->prepare('
                UPDATE courses 
                SET teacher_id = :teacher_id,
                    status = :status,
                    start_date = :start_date,
                    end_date = :end_date
                WHERE id = :id
            ');
            
            $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
            $stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);
            
            // If minimum students reached, set status to active and calculate dates
            if ($minStudentsReached) {
                $startDate = date('Y-m-d'); // Today
                $endDate = date('Y-m-d', strtotime("+{$course['validity_days']} days"));
                $status = 'active';
            } else {
                $startDate = null;
                $endDate = null;
                $status = 'pending';
            }
            
            $stmt->bindValue(':status', $status, SQLITE3_TEXT);
            $stmt->bindValue(':start_date', $startDate, SQLITE3_TEXT);
            $stmt->bindValue(':end_date', $endDate, SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                $success = 'Teacher assigned successfully!';
                if ($minStudentsReached) {
                    $success .= ' The course has been activated and will run for ' . $course['validity_days'] . ' days.';
                } else {
                    $success .= ' The course will be activated once the minimum number of students (' . $course['min_students'] . ') enroll.';
                }
                
                // If coming from assign_to page, redirect back to that page
                if (isset($_GET['assign_to'])) {
                    $_SESSION['success'] = $success;
                    header('Location: admin_dashboard.php?section=courses&assign_to=' . $_GET['assign_to']);
                    exit();
                }
            } else {
                throw new Exception('Failed to assign teacher');
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Assign Teacher</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            padding: 20px;
            background: #f4f4f4;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        h1 {
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        button {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background: #45a049;
        }

        .error {
            color: #f44336;
            margin-bottom: 10px;
            padding: 10px;
            background: #ffebee;
            border-radius: 4px;
        }

        .success {
            color: #4CAF50;
            margin-bottom: 10px;
            padding: 10px;
            background: #e8f5e9;
            border-radius: 4px;
        }

        .course-info {
            margin-bottom: 20px;
            padding: 15px;
            background: #e3f2fd;
            border-radius: 4px;
        }

        .course-info p {
            margin-bottom: 5px;
        }

        .nav-links {
            margin-bottom: 20px;
            display: flex;
            gap: 20px;
        }
        
        .nav-link {
            color: #2196F3;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        
        .nav-link:hover {
            background-color: #e3f2fd;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-links">
            <a href="admin_dashboard.php" class="nav-link">← Back to Dashboard</a>
            <a href="admin_dashboard.php?section=courses" class="nav-link">← Back to Courses</a>
        </div>
        
        <h1>Assign Teacher to Course</h1>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success">
                <?php echo htmlspecialchars($success); ?>
                <br>
                <a href="admin_dashboard.php?section=courses" class="nav-link">Return to Courses</a>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="course-info">
                <h3>Course Information</h3>
                <p><strong>Title:</strong> <?php echo htmlspecialchars($course['title']); ?></p>
                <p><strong>Description:</strong> <?php echo htmlspecialchars($course['description'] ?? 'No description'); ?></p>
                <p><strong>Validity:</strong> <?php echo htmlspecialchars($course['validity_days']); ?> days</p>
                             <p><strong>Status:</strong> <?php echo ucfirst(htmlspecialchars($course['status'])); ?></p>
                
                
            </div>

            <form method="POST">
                <div class="form-group">
                    <label for="teacher_id">Select Teacher</label>
                    <select id="teacher_id" name="teacher_id" required>
                        <option value="">Choose a teacher</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>" <?php echo ($preselectedTeacherId == $teacher['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst($teacher['username'])); ?> (<?php echo htmlspecialchars($teacher['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit">Assign Teacher</button>
            </form>
        </div>
    </div>
</body>
</html> 