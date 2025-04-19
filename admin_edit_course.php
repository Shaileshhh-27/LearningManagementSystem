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

// Add this right after the opening PHP tag
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: admin_dashboard.php');
    exit();
}

// First, verify the courses table has the required columns
try {
    $result = $conn->query("PRAGMA table_info(courses)");
    $columns = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $columns[] = $row['name'];
    }

    // Check if we need to add missing columns
    $missingColumns = false;
    $requiredColumns = ['validity_days', 'eligibility_criteria', 'min_students', 'max_students', 'status'];
    
    foreach ($requiredColumns as $column) {
        if (!in_array($column, $columns)) {
            $missingColumns = true;
            break;
        }
    }

    if ($missingColumns) {
        // Redirect to recreate_courses.php with return URL
        $return_url = urlencode($_SERVER['REQUEST_URI']);
        header('Location: database/recreate_courses.php?return=' . $return_url);
        exit();
    }

    // Fetch course details with proper column handling
    $stmt = $conn->prepare('
        SELECT 
            c.*,
            u.username as teacher_name,
            (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrolled_students
        FROM courses c 
        LEFT JOIN users u ON c.teacher_id = u.id 
        WHERE c.id = :id
    ');

    if ($stmt === false) {
        throw new Exception("Error preparing statement: " . $conn->lastErrorMsg());
    }

    $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $course = $result->fetchArray(SQLITE3_ASSOC);

    if (!$course) {
        header('Location: admin_create_course.php');
        exit();
    }

    // Set default values for missing fields
    $course['validity_days'] = $course['validity_days'] ?? 90;
    $course['min_students'] = $course['min_students'] ?? 5;
    $course['max_students'] = $course['max_students'] ?? 30;
    $course['eligibility_criteria'] = $course['eligibility_criteria'] ?? '';
    $course['status'] = $course['status'] ?? 'pending';
    $course['enrolled_students'] = $course['enrolled_students'] ?? 0;

} catch (Exception $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()) . 
        "<br><a href='admin_dashboard.php'>Return to Dashboard</a>");
}

// Fetch all teachers
$stmt = $conn->prepare('SELECT id, username, email FROM users WHERE role = "teacher" AND verified = 1');
$result = $stmt->execute();
$teachers = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $teachers[] = $row;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $teacherId = $_POST['teacher_id'] ?? $course['teacher_id'];
    $validityDays = $_POST['validity_days'] ?? 90;
    $eligibilityCriteria = $_POST['eligibility_criteria'] ?? '';
    $minStudents = $_POST['min_students'] ?? 5;
    $maxStudents = $_POST['max_students'] ?? 30;
    $status = $_POST['status'] ?? 'pending';
    
    if (empty($title) || empty($validityDays) || empty($minStudents) || empty($maxStudents)) {
        $error = 'Course title, validity days, minimum and maximum students are required';
    } else {
        try {
            // Check for duplicate course name (excluding current course)
            $stmt = $conn->prepare('
                SELECT COUNT(*) as count 
                FROM courses 
                WHERE title = :title 
                AND id != :id
            ');
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            if ($result->fetchArray(SQLITE3_ASSOC)['count'] > 0) {
                throw new Exception('A course with this name already exists');
            }

            // Determine if we need to update start/end dates
            $startDate = $course['start_date'];
            $endDate = $course['end_date'];
            
            // If status is changing to active and we have a teacher, set dates
            if ($status === 'active' && $teacherId && ($course['status'] !== 'active' || empty($startDate))) {
                $startDate = date('Y-m-d'); // Today
                $endDate = date('Y-m-d', strtotime("+{$validityDays} days"));
            }

            // Update the course
            $stmt = $conn->prepare('
                UPDATE courses 
                SET title = :title,
                    description = :description,
                    teacher_id = :teacher_id,
                    validity_days = :validity_days,
                    eligibility_criteria = :eligibility_criteria,
                    min_students = :min_students,
                    max_students = :max_students,
                    status = :status,
                    start_date = :start_date,
                    end_date = :end_date
                WHERE id = :id
            ');
            
            $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':description', $description, SQLITE3_TEXT);
            $stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);
            $stmt->bindValue(':validity_days', $validityDays, SQLITE3_INTEGER);
            $stmt->bindValue(':eligibility_criteria', $eligibilityCriteria, SQLITE3_TEXT);
            $stmt->bindValue(':min_students', $minStudents, SQLITE3_INTEGER);
            $stmt->bindValue(':max_students', $maxStudents, SQLITE3_INTEGER);
            $stmt->bindValue(':status', $status, SQLITE3_TEXT);
            $stmt->bindValue(':start_date', $startDate, SQLITE3_TEXT);
            $stmt->bindValue(':end_date', $endDate, SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                $success = 'Course updated successfully!';
                
                // Refresh course data
                $stmt = $conn->prepare('
                    SELECT 
                        c.*,
                        u.username as teacher_name,
                        (SELECT COUNT(*) FROM enrollments WHERE course_id = c.id) as enrolled_students
                    FROM courses c 
                    LEFT JOIN users u ON c.teacher_id = u.id 
                    WHERE c.id = :id
                ');
                $stmt->bindValue(':id', $courseId, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $course = $result->fetchArray(SQLITE3_ASSOC);
                
                // Set default values for missing fields after refresh
                $course['validity_days'] = $course['validity_days'] ?? 90;
                $course['min_students'] = $course['min_students'] ?? 5;
                $course['max_students'] = $course['max_students'] ?? 30;
                $course['eligibility_criteria'] = $course['eligibility_criteria'] ?? '';
                $course['status'] = $course['status'] ?? 'pending';
                $course['enrolled_students'] = $course['enrolled_students'] ?? 0;
            } else {
                throw new Exception('Failed to update course');
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
    <title>Admin - Edit Course</title>
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

        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        textarea {
            height: 100px;
            resize: vertical;
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

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #2196F3;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
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
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-row .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        .help-text {
            font-size: 0.8rem;
            color: #666;
            margin-top: 5px;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="nav-links">
            <a href="admin_dashboard.php" class="nav-link">← Back to Dashboard</a>
            <a href="admin_create_course.php" class="nav-link">← Back to Courses</a>
        </div>
        
        <h1>Edit Course</h1>

        <?php if ($error): ?>
            <div class="error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success">
                <?php echo htmlspecialchars($success); ?>
                <br>
                <a href="admin_create_course.php" class="nav-link">Return to Courses</a>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="course-info">
                <h3>Current Status</h3>
                <p><strong>Enrolled Students:</strong> <?php echo $course['enrolled_students']; ?> / <?php echo $course['min_students']; ?> min / <?php echo $course['max_students']; ?> max</p>
                
                <?php if ($course['status'] === 'active' && !empty($course['start_date']) && !empty($course['end_date'])): ?>
                    <p><strong>Start Date:</strong> <?php echo date('Y-m-d', strtotime($course['start_date'])); ?></p>
                    <p><strong>End Date:</strong> <?php echo date('Y-m-d', strtotime($course['end_date'])); ?></p>
                    <p><strong>Days Remaining:</strong> 
                        <?php 
                            $daysRemaining = ceil((strtotime($course['end_date']) - time()) / (60 * 60 * 24));
                            echo max(0, $daysRemaining);
                        ?> days
                    </p>
                <?php endif; ?>
            </div>
            
            <form method="POST">
                <div class="form-group">
                    <label for="title">Course Title</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($course['title']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="description">Course Description</label>
                    <textarea id="description" name="description"><?php echo htmlspecialchars($course['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="validity_days">Validity (Days)</label>
                        <input type="number" id="validity_days" name="validity_days" min="1" value="<?php echo htmlspecialchars($course['validity_days']); ?>" required>
                        <p class="help-text">Number of days the course will be valid after activation</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="min_students">Minimum Students</label>
                        <input type="number" id="min_students" name="min_students" min="1" value="<?php echo htmlspecialchars($course['min_students']); ?>" required>
                        <p class="help-text">Minimum students required to start the course</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_students">Maximum Students</label>
                        <input type="number" id="max_students" name="max_students" min="1" value="<?php echo htmlspecialchars($course['max_students']); ?>" required>
                        <p class="help-text">Maximum students allowed in the course</p>
                    </div>
                </div>

                <div class="form-group">
                    <label for="eligibility_criteria">Eligibility Criteria</label>
                    <textarea id="eligibility_criteria" name="eligibility_criteria" placeholder="Enter any eligibility requirements for students to enroll in this course"><?php echo htmlspecialchars($course['eligibility_criteria'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="teacher_id">Assign Teacher</label>
                    <select id="teacher_id" name="teacher_id">
                        <option value="">No Teacher Assigned</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>" <?php echo ($teacher['id'] == $course['teacher_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($teacher['username']); ?> (<?php echo htmlspecialchars($teacher['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" required>
                        <option value="pending" <?php echo ($course['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="active" <?php echo ($course['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($course['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        <option value="expired" <?php echo ($course['status'] == 'expired') ? 'selected' : ''; ?>>Expired</option>
                        <option value="cancelled" <?php echo ($course['status'] == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <button type="submit">Update Course</button>
            </form>
        </div>
    </div>
</body>
</html> 