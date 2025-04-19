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
$stmt = $conn->prepare('
    SELECT c.*, 
           (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) as student_count,
           (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.id) as assignment_count
    FROM courses c 
    WHERE c.teacher_id = :teacher_id
    ORDER BY c.created_at DESC
');
$stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);
$result = $stmt->execute();

$courses = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $courses[] = $row;
}

// Get total number of students across all courses
$stmt = $conn->prepare('
    SELECT COUNT(DISTINCT e.student_id) as total_students
    FROM enrollments e
    JOIN courses c ON e.course_id = c.id
    WHERE c.teacher_id = :teacher_id
');
$stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);
$result = $stmt->execute();
$totalStudents = $result->fetchArray(SQLITE3_ASSOC)['total_students'];

// Get total assignments
$stmt = $conn->prepare('
    SELECT COUNT(*) as total_assignments
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    WHERE c.teacher_id = :teacher_id
');
$stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);
$result = $stmt->execute();
$totalAssignments = $result->fetchArray(SQLITE3_ASSOC)['total_assignments'];

// Get recent assignments
$stmt = $conn->prepare('
    SELECT a.*, c.title as course_title,
           (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) as submission_count
    FROM assignments a
    JOIN courses c ON a.course_id = c.id
    WHERE c.teacher_id = :teacher_id
    ORDER BY a.created_at DESC
    LIMIT 5
');
$stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);
$result = $stmt->execute();

$recentAssignments = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $recentAssignments[] = $row;
}

// Redirect to main dashboard
header('Location: dashboard.php');
exit();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard</title>
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

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-card h3 {
            color: #666;
            margin-bottom: 10px;
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #2196F3;
        }

        .section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .section h2 {
            margin-bottom: 20px;
            color: #333;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
        }

        .btn:hover {
            background: #1976D2;
        }

        .btn-danger {
            background: #dc3545;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: #f5f5f5;
        }

        .actions {
            display: flex;
            gap: 10px;
        }

        .logout {
            color: #dc3545;
            text-decoration: none;
        }

        .logout:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Teacher Dashboard</h1>
            <div class="actions">
                <a href="create_assignment.php" class="btn">Create Assignment</a>
                <a href="logout.php" class="logout">Logout</a>
            </div>
        </div>

        <div class="stats">
            <div class="stat-card">
                <h3>Total Students</h3>
                <div class="number"><?php echo $totalStudents; ?></div>
            </div>
            <div class="stat-card">
                <h3>Total Assignments</h3>
                <div class="number"><?php echo $totalAssignments; ?></div>
            </div>
            <div class="stat-card">
                <h3>Courses</h3>
                <div class="number"><?php echo count($courses); ?></div>
            </div>
        </div>

        <div class="section">
            <h2>Your Courses</h2>
            <?php if (empty($courses)): ?>
                <p>No courses assigned yet.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Course Title</th>
                        <th>Students</th>
                        <th>Assignments</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($course['title']); ?></td>
                        <td><?php echo $course['student_count']; ?></td>
                        <td><?php echo $course['assignment_count']; ?></td>
                        <td><?php echo ucfirst($course['status'] ?? 'active'); ?></td>
                        <td class="actions">
                            <a href="view_course.php?id=<?php echo $course['id']; ?>" class="btn">View</a>
                            <a href="create_assignment.php?course_id=<?php echo $course['id']; ?>" class="btn">Add Assignment</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <div class="section">
            <h2>Recent Assignments</h2>
            <?php if (empty($recentAssignments)): ?>
                <p>No assignments created yet.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Title</th>
                        <th>Course</th>
                        <th>Due Date</th>
                        <th>Submissions</th>
                        <th>Actions</th>
                    </tr>
                    <?php foreach ($recentAssignments as $assignment): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                        <td><?php echo htmlspecialchars($assignment['course_title']); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($assignment['due_date'])); ?></td>
                        <td><?php echo $assignment['submission_count']; ?></td>
                        <td class="actions">
                            <a href="view_submissions.php?id=<?php echo $assignment['id']; ?>" class="btn">View Submissions</a>
                            <form method="POST" action="delete_assignment.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this assignment?');">
                                <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                <button type="submit" class="btn btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 