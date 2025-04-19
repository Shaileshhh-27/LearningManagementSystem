<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';
require_once 'includes/user_avatar.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();

if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$userRole = $auth->getUserRole();

// Redirect admin to admin dashboard
if ($userRole === 'admin') {
    header('Location: admin_dashboard.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$userId = $_SESSION['user_id'];

// Initialize variables
$courses = [];
$totalStudents = 0;
$totalAssignments = 0;
$recentAssignments = [];

if ($userRole === 'teacher') {
    // Get teacher's courses with student and assignment counts
    $stmt = $conn->prepare('
        SELECT c.*, 
               (SELECT COUNT(*) FROM enrollments e WHERE e.course_id = c.id) as student_count,
               (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.id) as assignment_count
        FROM courses c 
        WHERE c.teacher_id = :teacher_id
        ORDER BY c.created_at DESC
    ');
    $stmt->bindValue(':teacher_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
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
    $stmt->bindValue(':teacher_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $totalStudents = $result->fetchArray(SQLITE3_ASSOC)['total_students'];

    // Get total assignments
    $stmt = $conn->prepare('
        SELECT COUNT(*) as total_assignments
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        WHERE c.teacher_id = :teacher_id
    ');
    $stmt->bindValue(':teacher_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $totalAssignments = $result->fetchArray(SQLITE3_ASSOC)['total_assignments'];

    // Get recent assignments with submission counts
    $stmt = $conn->prepare('
        SELECT a.*, c.title as course_title,
               (SELECT COUNT(*) FROM submissions s WHERE s.assignment_id = a.id) as submission_count
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        WHERE c.teacher_id = :teacher_id
        ORDER BY a.created_at DESC
        LIMIT 5
    ');
    $stmt->bindValue(':teacher_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $recentAssignments[] = $row;
    }
} else {
    // Student dashboard code here
    // Get enrolled courses
    $stmt = $conn->prepare('
        SELECT c.*, u.username as teacher_name,
               (SELECT COUNT(*) FROM assignments a WHERE a.course_id = c.id) as assignment_count
        FROM enrollments e
        JOIN courses c ON e.course_id = c.id
        JOIN users u ON c.teacher_id = u.id
        WHERE e.student_id = :student_id
    ');
    $stmt->bindValue(':student_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $courses[] = $row;
    }

    // Get all assignments for enrolled courses
    $stmt = $conn->prepare('
        SELECT a.*, c.title as course_title,
               CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END as submitted
        FROM assignments a
        JOIN courses c ON a.course_id = c.id
        JOIN enrollments e ON c.id = e.course_id
        LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = :student_id
        WHERE e.student_id = :student_id
        ORDER BY a.due_date ASC
    ');
    $stmt->bindValue(':student_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $pendingAssignmentsList = [];
    $completedAssignmentsList = [];
    $pendingAssignments = 0;
    $completedAssignments = 0;
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($row['submitted']) {
            $completedAssignments++;
            $completedAssignmentsList[] = $row;
        } else {
            $pendingAssignments++;
            $pendingAssignmentsList[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f4f4;
            line-height: 1.6;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            gap: 20px;
        }

        .sidebar {
            width: 250px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            height: fit-content;
            position: sticky;
            top: 20px;
        }

        .main-content {
            flex: 1;
        }

        .welcome-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .welcome-header h1 {
            color: #333;
            margin-bottom: 10px;
        }

        .welcome-header p {
            color: #666;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            color: #666;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: #2196F3;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin-bottom: 5px;
            border-radius: 4px;
            color: #666;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            font-weight: 500;
        }

        .nav-item:hover {
            background: #f5f5f5;
            color: #2196F3;
        }

        .nav-item.active {
            background: #2196F3;
            color: white;
        }

        .nav-item i {
            margin-right: 10px;
            font-size: 1.2em;
        }

        .section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .section-header h2 {
            color: #333;
            font-size: 1.5rem;
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

        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .course-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .course-card:hover {
            transform: translateY(-5px);
        }

        .course-header {
            background: #2196F3;
            color: white;
            padding: 15px;
        }

        .course-content {
            padding: 15px;
        }

        .course-footer {
            padding: 15px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        th {
            background: #f8f9fa;
            font-weight: bold;
        }

        tr:hover {
            background: #f5f5f5;
        }

        .logout {
            color: #f44336;
            text-decoration: none;
            font-weight: bold;
        }

        .logout:hover {
            text-decoration: underline;
        }

        .notification {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            background: #e8f5e9;
            color: #2e7d32;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div style="margin-bottom: 20px; display: flex; align-items: center;">
                <?php
                // Get user data with avatar
                $stmt = $conn->prepare('SELECT id, username, avatar FROM users WHERE id = :id');
                $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $userData = $result->fetchArray(SQLITE3_ASSOC);
                ?>
                <div style="margin-right: 10px;">
                    <?php echo getUserAvatar($userData, 40); ?>
                </div>
                <div>
                    <h2 style="color: #333; margin-bottom: 5px;">
                        <?php echo $userRole === 'teacher' ? 'Teacher Panel' : 'Student Panel'; ?>
                    </h2>
                    <p style="color: #666; font-size: 0.9em;">
                        Welcome, <?php echo htmlspecialchars($auth->getUsername()); ?>
                    </p>
                </div>
            </div>

            <?php if ($userRole === 'teacher'): ?>
                <a href="#my-courses" class="nav-item active">
                    <i class="fas fa-book"></i> My Courses
                </a>
                <a href="#assignments" class="nav-item">
                    <i class="fas fa-tasks"></i> Assignments
                </a>
                <a href="#students" class="nav-item">
                    <i class="fas fa-user-graduate"></i> Students
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-circle"></i> My Profile
                </a>
            <?php else: ?>
                <a href="#enrolled-courses" class="nav-item active">
                    <i class="fas fa-book"></i> My Courses
                </a>
                <a href="#assignments" class="nav-item">
                    <i class="fas fa-tasks"></i> Assignments
                </a>
                <a href="browse_courses.php" class="nav-item">
                    <i class="fas fa-search"></i> Browse Courses
                </a>
                <a href="profile.php" class="nav-item">
                    <i class="fas fa-user-circle"></i> My Profile
                </a>
            <?php endif; ?>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                <a href="logout.php" class="nav-item" style="color: #f44336;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <div class="main-content">
            <?php if (isset($_SESSION['notifications'])): ?>
                <?php foreach ($_SESSION['notifications'] as $notification): ?>
                    <div class="notification">
                        <?php echo htmlspecialchars($notification); ?>
                    </div>
                <?php endforeach; ?>
                <?php unset($_SESSION['notifications']); ?>
            <?php endif; ?>

            <div class="welcome-header">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <?php echo getUserAvatar($userData, 60); ?>
                    <div>
                        <h1>Welcome back, <?php echo htmlspecialchars(ucfirst($auth->getUsername())); ?>!</h1>
                        <p>Here's an overview of your learning journey</p>
                    </div>
                </div>
            </div>

            <div class="stats">
                <?php if ($userRole === 'teacher'): ?>
                    <div class="stat-card">
                        <h3>Total Courses</h3>
                        <div class="number"><?php echo count($courses); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Students</h3>
                        <div class="number"><?php echo $totalStudents; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Total Assignments</h3>
                        <div class="number"><?php echo $totalAssignments; ?></div>
                    </div>
                <?php else: ?>
                    <div class="stat-card">
                        <h3>Enrolled Courses</h3>
                        <div class="number"><?php echo count($courses); ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Pending Assignments</h3>
                        <div class="number"><?php echo $pendingAssignments; ?></div>
                    </div>
                    <div class="stat-card">
                        <h3>Completed Assignments</h3>
                        <div class="number"><?php echo $completedAssignments; ?></div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="section" id="courses-section">
                <div class="section-header">
                    <h2><?php echo $userRole === 'teacher' ? 'My Courses' : 'Enrolled Courses'; ?></h2>
                </div>

                <?php if (empty($courses)): ?>
                    <p style="text-align: center; padding: 20px;">
                        <?php echo $userRole === 'teacher' ? 'No courses assigned to you yet.' : 'You haven\'t enrolled in any courses yet.'; ?>
                    </p>
                <?php else: ?>
                    <div class="course-grid">
                        <?php foreach ($courses as $course): ?>
                            <div class="course-card">
                                <div class="course-header">
                                    <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                                </div>
                                <div class="course-content">
                                    <p><?php echo htmlspecialchars(substr($course['description'], 0, 100)) . '...'; ?></p>
                                </div>
                                <div class="course-footer">
                                    <span><?php echo count($course['students'] ?? []); ?> students</span>
                                    <a href="course.php?id=<?php echo $course['id']; ?>" class="btn">View Course</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($userRole === 'teacher'): ?>
                <div class="section" id="assignments-section" style="display: none;">
                    <div class="section-header">
                        <h2>Recent Assignments</h2>
                        <a href="create_assignment.php" class="btn">Create Assignment</a>
                    </div>
                    <?php if (empty($recentAssignments)): ?>
                        <p style="text-align: center; padding: 20px;">No assignments created yet.</p>
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
                                    <td><?php echo date('M d, Y', strtotime($assignment['due_date'])); ?></td>
                                    <td><?php echo $assignment['submission_count']; ?></td>
                                    <td class="actions">
                                        <a href="view_submissions.php?id=<?php echo $assignment['id']; ?>&return=dashboard" class="btn">View Submissions</a>
                                        <a href="edit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn">Edit</a>
                                        <form method="POST" action="delete_assignment.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this assignment?');">
                                            <input type="hidden" name="assignment_id" value="<?php echo $assignment['id']; ?>">
                                            <input type="hidden" name="return_to" value="dashboard">
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </div>
                <div class="section" id="students-section" style="display: none;">
                    <div class="section-header">
                        <h2>Students Overview</h2>
                    </div>
                    <table>
                        <tr>
                            <th>Course</th>
                            <th>Enrolled Students</th>
                            <th>Actions</th>
                        </tr>
                        <?php foreach ($courses as $course): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($course['title']); ?></td>
                                <td><?php echo $course['student_count']; ?></td>
                                <td>
                                    <a href="course.php?id=<?php echo $course['id']; ?>#students" class="btn">View Students</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php else: ?>
                <div class="section" id="assignments-section" style="display: none;">
                    <div class="section-header">
                        <h2>All Assignments</h2>
                    </div>
                    <div class="section" style="box-shadow: none; padding: 0;">
                        <div class="section-header">
                            <h3>Pending Assignments</h3>
                        </div>
                        <?php if (empty($pendingAssignmentsList)): ?>
                            <p style="text-align: center; padding: 20px;">No pending assignments.</p>
                        <?php else: ?>
                            <table>
                                <tr>
                                    <th>Title</th>
                                    <th>Course</th>
                                    <th>Due Date</th>
                                    <th>Actions</th>
                                </tr>
                                <?php foreach ($pendingAssignmentsList as $assignment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['course_title']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($assignment['due_date'])); ?></td>
                                        <td>
                                            <a href="submit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn">Submit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>
                    </div>
                    
                    <div class="section" style="box-shadow: none; padding: 0; margin-top: 20px;">
                        <div class="section-header">
                            <h3>Completed Assignments</h3>
                        </div>
                        <?php if (empty($completedAssignmentsList)): ?>
                            <p style="text-align: center; padding: 20px;">No completed assignments.</p>
                        <?php else: ?>
                            <table>
                                <tr>
                                    <th>Title</th>
                                    <th>Course</th>
                                    <th>Submitted Date</th>
                                    <th>Actions</th>
                                </tr>
                                <?php foreach ($completedAssignmentsList as $assignment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                                        <td><?php echo htmlspecialchars($assignment['course_title']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($assignment['due_date'])); ?></td>
                                        <td>
                                            <a href="submit_assignment.php?id=<?php echo $assignment['id']; ?>" class="btn">View Submission</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get all nav items
        const navItems = document.querySelectorAll('.nav-item');
        
        // Get all sections
        const sections = {
            'my-courses': document.getElementById('courses-section'),
            'enrolled-courses': document.getElementById('courses-section'),
            'assignments': document.getElementById('assignments-section'),
            'students': document.getElementById('students-section')
        };
        
        // Add click event to each nav item
        navItems.forEach(item => {
            item.addEventListener('click', function(e) {
                // If it's a link to another page, let it proceed
                if (!this.getAttribute('href').startsWith('#')) {
                    return;
                }
                
                e.preventDefault();
                
                // Remove active class from all nav items
                navItems.forEach(nav => nav.classList.remove('active'));
                
                // Add active class to clicked item
                this.classList.add('active');
                
                // Hide all sections
                Object.values(sections).forEach(section => {
                    if (section) {
                        section.style.display = 'none';
                    }
                });
                
                // Show the selected section
                const sectionId = this.getAttribute('href').substring(1);
                const sectionToShow = sections[sectionId];
                if (sectionToShow) {
                    sectionToShow.style.display = 'block';
                }
            });
        });
    });
    </script>
</body>
</html>