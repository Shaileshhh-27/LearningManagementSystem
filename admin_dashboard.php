<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';

session_start();
$auth = new Auth();

// If not logged in or not an admin, redirect to login page
if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'admin') {
    header('Location: index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Initialize variables
$totalCourses = 0;
$activeCourses = 0;
$totalTeachers = 0;
$totalStudents = 0;
$totalUsers = 0;
$activeUsers = 0;
$unverifiedUsers = [];
$recentCourses = [];
$teacherCourses = [];
$teachersWithoutCourses = [];
$nearExpirationCourses = [];
$enrolledStudents = [];
$allCourses = [];

// Get filter values from GET parameters
$roleFilter = $_GET['role_filter'] ?? 'all';
$courseFilter = $_GET['course_filter'] ?? 'all';
$validityFilter = $_GET['validity_filter'] ?? 'all';
$studentFilter = $_GET['student_filter'] ?? '';
$courseIdFilter = $_GET['course_id'] ?? 'all';

// Get current section
$section = $_GET['section'] ?? 'students';

// Add refresh parameter to force data reload
$refresh = isset($_GET['refresh']) ? $_GET['refresh'] : null;

try {
    // 1. Total courses
    $stmt = $conn->prepare('SELECT COUNT(DISTINCT id) as count FROM courses');
    $result = $stmt->execute();
    $totalCourses = $result->fetchArray(SQLITE3_ASSOC)['count'];

    // 2. Active courses (where status is active)
    $stmt = $conn->prepare('SELECT COUNT(DISTINCT id) as count FROM courses WHERE status = "active"');
    $result = $stmt->execute();
    $activeCourses = $result->fetchArray(SQLITE3_ASSOC)['count'];

    // 3. Total teachers (with cache busting)
    $stmt = $conn->prepare('SELECT COUNT(DISTINCT id) as count FROM users WHERE role = "teacher" AND verified = 1');
    $result = $stmt->execute();
    $totalTeachers = $result->fetchArray(SQLITE3_ASSOC)['count'];

    // 4. Total students
    $stmt = $conn->prepare('SELECT COUNT(DISTINCT id) as count FROM users WHERE role = "student" AND verified = 1');
    $result = $stmt->execute();
    $totalStudents = $result->fetchArray(SQLITE3_ASSOC)['count'];

    // 5. Total users (including admin)
    $stmt = $conn->prepare('SELECT COUNT(DISTINCT id) as count FROM users');
    $result = $stmt->execute();
    $totalUsers = $result->fetchArray(SQLITE3_ASSOC)['count'];

    // 6. Active (verified) users
    $stmt = $conn->prepare('SELECT COUNT(DISTINCT id) as count FROM users WHERE verified = 1');
    $result = $stmt->execute();
    $activeUsers = $result->fetchArray(SQLITE3_ASSOC)['count'];

    // Fetch unverified users
    $stmt = $conn->prepare('SELECT DISTINCT * FROM users WHERE verified = 0');
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $unverifiedUsers[] = $row;
    }

    // Fetch recent courses with no duplicates
    $stmt = $conn->prepare('
        SELECT DISTINCT c.*, u.username as teacher_name 
        FROM courses c 
        JOIN users u ON c.teacher_id = u.id 
        ORDER BY c.created_at DESC LIMIT 5
    ');
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if (!isset($row['status'])) {
            $row['status'] = 'active';
        }
        $recentCourses[] = $row;
    }

    // Build the teacher query based on filters
    $teacherQuery = '
        SELECT DISTINCT
            u.id as teacher_id,
            u.username as teacher_name,
            u.email as teacher_email,
            COUNT(DISTINCT c.id) as course_count,
            GROUP_CONCAT(DISTINCT c.title) as course_titles,
            MIN(c.end_date) as nearest_expiration
        FROM users u
        LEFT JOIN courses c ON u.id = c.teacher_id
        WHERE u.role = "teacher" AND u.verified = 1
    ';

    // Add filter conditions
    if ($courseFilter === 'assigned') {
        $teacherQuery .= ' AND c.id IS NOT NULL';
    } elseif ($courseFilter === 'unassigned') {
        $teacherQuery .= ' AND c.id IS NULL';
    }

    if ($validityFilter === 'expiring') {
        $teacherQuery .= ' AND c.end_date <= date("now", "+30 days")';
    } elseif ($validityFilter === 'active') {
        $teacherQuery .= ' AND c.status = "active"';
    }

    $teacherQuery .= ' GROUP BY u.id';

    $stmt = $conn->prepare($teacherQuery);
    $result = $stmt->execute();
    
    // Clear existing arrays
    $teacherCourses = [];
    $teachersWithoutCourses = [];
    $nearExpirationCourses = [];
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        if ($row['course_count'] > 0) {
            $teacherCourses[] = $row;
            if ($row['nearest_expiration'] && strtotime($row['nearest_expiration']) < strtotime('+30 days')) {
                $nearExpirationCourses[] = $row;
            }
        } else {
            $teachersWithoutCourses[] = $row;
        }
    }

    // Modify the student query to prevent duplicates and handle filters
    $studentQuery = '
        SELECT DISTINCT
            u.id, 
            u.username, 
            u.email, 
            u.verified,
            GROUP_CONCAT(DISTINCT c.title) as enrolled_courses,
            GROUP_CONCAT(DISTINCT c.id) as course_ids,
            COUNT(DISTINCT e.course_id) as course_count
        FROM users u
        LEFT JOIN enrollments e ON u.id = e.student_id
        LEFT JOIN courses c ON e.course_id = c.id
        WHERE u.role = "student"
    ';

    if ($studentFilter) {
        $studentQuery .= ' AND (u.username LIKE :search OR u.email LIKE :search)';
    }

    if ($courseIdFilter !== 'all') {
        $studentQuery .= ' AND c.id = :course_id';
    }

    $studentQuery .= ' GROUP BY u.id ORDER BY u.username ASC';
    
    $stmt = $conn->prepare($studentQuery);
    
    if ($studentFilter) {
        $searchTerm = "%$studentFilter%";
        $stmt->bindValue(':search', $searchTerm, SQLITE3_TEXT);
    }
    
    if ($courseIdFilter !== 'all') {
        $stmt->bindValue(':course_id', $courseIdFilter, SQLITE3_INTEGER);
    }
    
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $enrolledStudents[] = $row;
    }

    // Fetch all active courses for dropdowns
    $stmt = $conn->prepare('SELECT DISTINCT id, title FROM courses WHERE status = "active" ORDER BY title ASC');
    $result = $stmt->execute();
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $allCourses[] = $row;
    }

} catch (Exception $e) {
    error_log('Database error: ' . $e->getMessage());
}

// Handle user verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_user'])) {
    $userId = $_POST['user_id'];
    try {
        $stmt = $conn->prepare('UPDATE users SET verified = 1 WHERE id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $stmt->execute();
        $_SESSION['success'] = 'User approved successfully.';
        header('Location: admin_dashboard.php?section=pending');
        exit();
    } catch (Exception $e) {
        error_log('Error verifying user: ' . $e->getMessage());
    }
}

// Handle user rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_user'])) {
    $userId = $_POST['user_id'];
    try {
        $stmt = $conn->prepare('DELETE FROM users WHERE id = :id AND verified = 0');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $stmt->execute();
        $_SESSION['success'] = 'User rejected successfully.';
        header('Location: admin_dashboard.php?section=pending');
        exit();
    } catch (Exception $e) {
        error_log('Error rejecting user: ' . $e->getMessage());
    }
}

// Handle teacher deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_teacher'])) {
    $teacherId = $_POST['teacher_id'];
    try {
        // First, reassign or delete courses associated with this teacher
        $stmt = $conn->prepare('UPDATE courses SET teacher_id = NULL WHERE teacher_id = :teacher_id');
        $stmt->bindValue(':teacher_id', $teacherId, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Then delete the teacher
        $stmt = $conn->prepare('DELETE FROM users WHERE id = :id AND role = "teacher"');
        $stmt->bindValue(':id', $teacherId, SQLITE3_INTEGER);
        $stmt->execute();
        
        $_SESSION['success'] = 'Teacher deleted successfully. Any courses have been unassigned.';
        
        // Force a refresh of the page to update counts and lists
        header('Location: admin_dashboard.php?section=teachers&refresh=' . time());
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error deleting teacher: ' . $e->getMessage();
        header('Location: admin_dashboard.php?section=teachers');
        exit();
    }
}

// Handle student enrollment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['enroll_student'])) {
        $studentId = $_POST['student_id'];
        $courseId = $_POST['course_id'];
        
        $stmt = $conn->prepare('INSERT INTO enrollments (student_id, course_id) VALUES (:student_id, :course_id)');
        $stmt->bindValue(':student_id', $studentId, SQLITE3_INTEGER);
        $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Student enrolled successfully.';
        } else {
            $_SESSION['error'] = 'Failed to enroll student.';
        }
        
        header('Location: admin_dashboard.php');
        exit();
    }
    
    if (isset($_POST['cancel_enrollment'])) {
        $studentId = $_POST['student_id'];
        $courseId = $_POST['course_id'];
        
        $stmt = $conn->prepare('DELETE FROM enrollments WHERE student_id = :student_id AND course_id = :course_id');
        $stmt->bindValue(':student_id', $studentId, SQLITE3_INTEGER);
        $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Enrollment cancelled successfully.';
        } else {
            $_SESSION['error'] = 'Failed to cancel enrollment.';
        }
        
        header('Location: admin_dashboard.php');
        exit();
    }
    
    if (isset($_POST['delete_student'])) {
        $studentId = $_POST['student_id'];
        
        // First delete enrollments
        $stmt = $conn->prepare('DELETE FROM enrollments WHERE student_id = :student_id');
        $stmt->bindValue(':student_id', $studentId, SQLITE3_INTEGER);
        $stmt->execute();
        
        // Then delete the user
        $stmt = $conn->prepare('DELETE FROM users WHERE id = :id AND role = "student"');
        $stmt->bindValue(':id', $studentId, SQLITE3_INTEGER);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Student deleted successfully.';
        } else {
            $_SESSION['error'] = 'Failed to delete student.';
        }
        
        header('Location: admin_dashboard.php');
        exit();
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $userId = $_POST['user_id'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (empty($newPassword) || strlen($newPassword) < 6) {
            $_SESSION['error'] = 'Password must be at least 6 characters long.';
        } elseif ($newPassword !== $confirmPassword) {
            $_SESSION['error'] = 'Passwords do not match.';
        } else {
            try {
                // Hash the new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                // Update the user's password
                $stmt = $conn->prepare('UPDATE users SET password = :password WHERE id = :id');
                $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT);
                $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = 'Password changed successfully.';
                } else {
                    $_SESSION['error'] = 'Failed to change password.';
                }
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error: ' . $e->getMessage();
            }
        }
        
        header('Location: admin_dashboard.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            padding: 20px;
            background: #f4f4f4;
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

        .header h1 {
            color: #333;
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

        .stat-card .sub-text {
            font-size: 0.9rem;
            color: #888;
            margin-top: 5px;
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
            position: relative;
            overflow: hidden;
        }

        .nav-item:hover {
            background: #f5f5f5;
            color: #2196F3;
            text-decoration: none;
        }

        .nav-item.active {
            background: #2196F3;
            color: white;
            text-decoration: none;
        }

        .nav-item i {
            margin-right: 10px;
            font-size: 1.2em;
            min-width: 20px;
            text-align: center;
        }

        .tab-navigation {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .tab-button {
            padding: 12px 24px;
            background: #f5f5f5;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            color: #666;
            transition: all 0.3s ease;
            flex: 1;
            text-align: center;
        }

        .tab-button:hover {
            background: #e0e0e0;
        }

        .tab-button.active {
            background: #2196F3;
            color: white;
        }

        .dashboard-section {
            display: none;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .dashboard-section.active {
            display: block;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }

        .section-header h2 {
            color: #333;
            font-size: 1.5rem;
            font-weight: 500;
        }

        .search-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .search-input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: #45a049;
        }

        .btn-blue {
            background: #2196F3;
        }

        .btn-blue:hover {
            background: #1976D2;
        }

        .btn-red {
            background: #f44336;
        }

        .btn-red:hover {
            background: #d32f2f;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            border: none;
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background-color: #f8f9fa;
            font-weight: 500;
            color: #333;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .status-active {
            display: inline-block;
            padding: 4px 12px;
            background-color: #e8f5e9;
            color: #2e7d32;
            font-weight: 500;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .status-inactive {
            display: inline-block;
            padding: 4px 12px;
            background-color: #ffebee;
            color: #c62828;
            font-weight: 500;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .status-pending {
            display: inline-block;
            padding: 4px 12px;
            background-color: #fff8e1;
            color: #f57c00;
            font-weight: 500;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .success-message, .error-message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            text-align: center;
        }

        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
        }

        .logout {
            color: #f44336;
            text-decoration: none;
            font-weight: bold;
        }

        .logout:hover {
            text-decoration: underline;
        }
        
        /* Course badge styles */
        .course-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .course-badge {
            background: #e3f2fd;
            color: #1976D2;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 150px;
        }
        
        .course-count {
            background: #1976D2;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            width: 500px;
            max-width: 90%;
            position: relative;
        }
        
        .close {
            color: #aaa;
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
        }
        
        #password-user-info {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #e3f2fd;
            border-radius: 4px;
            color: #1976D2;
        }
        
        #teacher-name {
            font-weight: bold;
        }
        
        .course-list {
            list-style: none;
            padding: 0;
        }
        
        .course-list li {
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .course-list li:last-child {
            border-bottom: none;
        }
        
        .course-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: 15px;
            gap: 10px;
        }
        
        .compact-actions {
            display: flex;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        .dropdown-container {
            position: relative;
            display: inline-block;
        }
        
        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            min-width: 180px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            z-index: 100;
            border-radius: 4px;
            padding: 5px 0;
            max-height: 300px;
            overflow-y: auto;
        }
        
        /* Add a media query to handle dropdown positioning on smaller screens */
        @media screen and (max-width: 768px) {
            .dropdown-menu {
                right: auto;
                left: 0;
            }
        }
        
        /* Make sure dropdown stays in viewport */
        .dropdown-container:last-child .dropdown-menu {
            right: 0;
            left: auto;
        }
        
        /* For dropdowns near the bottom of the page */
        .dropdown-container.bottom-aligned .dropdown-menu {
            bottom: 100%;
            top: auto;
        }
        
        .dropdown-menu a {
            color: #333;
            padding: 8px 12px;
            text-decoration: none;
            display: block;
            font-size: 0.9rem;
        }
        
        .dropdown-menu a:hover {
            background-color: #f5f5f5;
        }
        
        .show {
            display: block;
        }
        
        /* Action button styles */
        .actions-btn {
            background-color: #4CAF50;
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            position: relative;
        }
        
        .actions-btn:hover {
            background-color: #45a049;
        }
        
        .actions-btn i {
            font-size: 0.8rem;
        }
        
        /* Student management specific styles */
        #students .search-bar {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        #students .search-input {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px 15px;
            font-size: 0.9rem;
        }
        
        #students select {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px 15px;
            font-size: 0.9rem;
            background-color: white;
        }
        
        #students .btn-blue {
            padding: 10px 20px;
            font-size: 0.9rem;
        }
        
        #students table th {
            font-weight: 500;
            color: #333;
            border-bottom: 1px solid #ddd;
        }
        
        #students table td {
            vertical-align: middle;
            padding: 12px 15px;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div style="margin-bottom: 20px; display: flex; align-items: center;">
                <?php
                // Get admin's avatar
                $adminId = $_SESSION['user_id'];
                $stmt = $conn->prepare('SELECT avatar FROM users WHERE id = :id');
                $stmt->bindValue(':id', $adminId, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $adminData = $result->fetchArray(SQLITE3_ASSOC);
                $avatarUrl = !empty($adminData['avatar']) ? "uploads/avatars/" . htmlspecialchars($adminData['avatar']) : "https://via.placeholder.com/40";
                ?>
                <div style="margin-right: 10px;">
                    <img src="<?php echo $avatarUrl; ?>" alt="Admin Avatar" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                </div>
                <div>
                    <h2 style="color: #333; margin-bottom: 5px;">Admin Panel</h2>
                    <p style="color: #666; font-size: 0.9em;">Welcome, Admin</p>
                </div>
            </div>
            
            <a href="?section=students" class="nav-item <?php echo $section == 'students' ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i> Students
            </a>
            <a href="?section=teachers" class="nav-item <?php echo $section == 'teachers' ? 'active' : ''; ?>">
                <i class="fas fa-chalkboard-teacher"></i> Teachers
            </a>
            <a href="profile.php" class="nav-item" style="cursor: pointer; text-decoration: none;">
                <i class="fas fa-user-circle"></i> My Profile
            </a>
            <a href="?section=courses" class="nav-item <?php echo $section == 'courses' ? 'active' : ''; ?>">
                <i class="fas fa-book"></i> Courses
            </a>
            <a href="?section=pending" class="nav-item <?php echo $section == 'pending' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> Pending Approvals
            </a>
            <a href="?section=reports" class="nav-item <?php echo $section == 'reports' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                <a href="logout.php" class="nav-item" style="color: #f44336;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <div class="main-content">
            <div class="header" id="top">
                <h1>Dashboard Overview</h1>
            </div>

            <div class="stats">
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <div class="number"><?php echo $totalUsers; ?></div>
                    <div class="sub-text">Active: <?php echo $activeUsers; ?></div>
                </div>
                <div class="stat-card">
                    <h3>Teachers</h3>
                    <div class="number"><?php echo $totalTeachers; ?></div>
                    <div class="sub-text">Verified Teachers</div>
                </div>
                <div class="stat-card">
                    <h3>Students</h3>
                    <div class="number"><?php echo $totalStudents; ?></div>
                    <div class="sub-text">Enrolled Students</div>
                </div>
                <div class="stat-card">
                    <h3>Courses</h3>
                    <div class="number"><?php echo $totalCourses; ?></div>
                    <div class="sub-text">Active: <?php echo $activeCourses; ?></div>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                    <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>

            <!-- Student Management Section -->
            <div id="students" class="dashboard-section active">
                <div class="section-header">
                    <h2>Student Management</h2>
                </div>
                
                <div class="search-bar">
                    <form method="GET" action="admin_dashboard.php" style="width: 100%; display: flex; gap: 10px;">
                        <input type="hidden" name="section" value="students">
                        <input type="text" name="student_filter" 
                               placeholder="Search students by name or email" 
                               value="<?php echo htmlspecialchars($studentFilter); ?>" 
                               class="search-input">
                        <select name="course_id" style="padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="all">All Courses</option>
                            <?php foreach ($allCourses as $course): ?>
                                <option value="<?php echo $course['id']; ?>" <?php echo ($courseIdFilter == $course['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-blue">Search</button>
                    </form>
                </div>

                <?php if (!empty($enrolledStudents)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 60px;">S.No</th>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Status</th>
                                <th>Enrolled Courses</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrolledStudents as $index => $student): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst($student['username'])); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td>
                                        <span class="status-<?php echo $student['verified'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $student['verified'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($student['enrolled_courses']): ?>
                                            <?php 
                                                $courseTitles = explode(',', $student['enrolled_courses']);
                                                $courseIds = explode(',', $student['course_ids']);
                                                $totalCourses = count($courseTitles);
                                                $displayLimit = 2; // Show only 2 courses directly
                                            ?>
                                            <div class="course-badges">
                                                <?php for ($i = 0; $i < min($displayLimit, $totalCourses); $i++): ?>
                                                    <span class="course-badge" title="<?php echo htmlspecialchars($courseTitles[$i]); ?>">
                                                        <?php echo htmlspecialchars($courseTitles[$i]); ?>
                                                    </span>
                                                <?php endfor; ?>
                                                
                                                <?php if ($totalCourses > $displayLimit): ?>
                                                    <span class="course-count" onclick="showCoursesModal('<?php echo $student['id']; ?>')">
                                                        +<?php echo $totalCourses - $displayLimit; ?> more
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- Hidden data for modal -->
                                            <div id="student-courses-<?php echo $student['id']; ?>" style="display: none;" 
                                                 data-student-name="<?php echo htmlspecialchars($student['username']); ?>"
                                                 data-student-id="<?php echo $student['id']; ?>">
                                                <?php for ($i = 0; $i < $totalCourses; $i++): ?>
                                                    <div data-course-id="<?php echo $courseIds[$i]; ?>" 
                                                         data-course-title="<?php echo htmlspecialchars($courseTitles[$i]); ?>"></div>
                                                <?php endfor; ?>
                                            </div>
                                        <?php else: ?>
                                            <em>No courses</em>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="compact-actions">
                                            <div class="dropdown-container">
                                                <button class="actions-btn" onclick="toggleDropdown('dropdown-<?php echo $student['id']; ?>')">
                                                    Actions <i class="fas fa-caret-down"></i>
                                                </button>
                                                <div id="dropdown-<?php echo $student['id']; ?>" class="dropdown-menu">
                                                    <a href="#" onclick="showEnrollModal('<?php echo $student['id']; ?>', '<?php echo htmlspecialchars($student['username']); ?>')">
                                                        <i class="fas fa-plus"></i> Add to Course
                                                    </a>
                                                    <?php if ($student['course_count'] > 0): ?>
                                                        <a href="#" onclick="showCoursesModal('<?php echo $student['id']; ?>')">
                                                            <i class="fas fa-list"></i> Manage Courses
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="#" onclick="showPasswordModal('<?php echo $student['id']; ?>', '<?php echo htmlspecialchars($student['username']); ?>')">
                                                        <i class="fas fa-key"></i> Change Password
                                                    </a>
                                                    <a href="#" onclick="if(confirm('Are you sure you want to delete this student?')) { deleteStudent('<?php echo $student['id']; ?>'); }">
                                                        <i class="fas fa-trash"></i> Delete Student
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Hidden form for delete action -->
                                        <form id="delete-form-<?php echo $student['id']; ?>" method="POST" style="display: none;">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <input type="hidden" name="delete_student" value="1">
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; padding: 20px;">No students found.</p>
                <?php endif; ?>
            </div>

            <!-- Modals -->
            <div id="courses-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="modal-title">Courses for <span id="modal-student-name"></span></div>
                        <span class="close-modal" onclick="closeModal('courses-modal')">&times;</span>
                    </div>
                    <ul id="courses-list" class="course-list">
                        <!-- Course list will be populated by JavaScript -->
                    </ul>
                </div>
            </div>
            
            <div id="enroll-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="modal-title">Add <span id="enroll-student-name"></span> to Course</div>
                        <span class="close-modal" onclick="closeModal('enroll-modal')">&times;</span>
                    </div>
                    <form id="enroll-form" method="POST">
                        <input type="hidden" id="enroll-student-id" name="student_id">
                        <div class="form-group">
                            <label for="course-select">Select Course</label>
                            <select id="course-select" name="course_id" required style="width: 100%; padding: 8px; margin-top: 5px;">
                                <option value="">-- Select a Course --</option>
                                <!-- Options will be populated by JavaScript -->
                            </select>
                        </div>
                        <div class="course-actions">
                            <button type="button" class="btn" onclick="closeModal('enroll-modal')">Cancel</button>
                            <button type="submit" name="enroll_student" class="btn btn-blue">Enroll</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Password Change Modal -->
            <div id="password-modal" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="modal-title">Change Password for <span id="password-user-name"></span></div>
                        <span class="close-modal" onclick="closeModal('password-modal')">&times;</span>
                    </div>
                    <form id="password-form" method="POST">
                        <input type="hidden" id="password-user-id" name="user_id">
                        <div class="form-group">
                            <label for="new-password">New Password</label>
                            <input type="password" id="new-password" name="new_password" required 
                                   style="width: 100%; padding: 8px; margin-top: 5px;" 
                                   minlength="6" placeholder="Minimum 6 characters">
                        </div>
                        <div class="form-group">
                            <label for="confirm-password">Confirm Password</label>
                            <input type="password" id="confirm-password" name="confirm_password" required 
                                   style="width: 100%; padding: 8px; margin-top: 5px;"
                                   minlength="6" placeholder="Confirm new password">
                        </div>
                        <div class="course-actions">
                            <button type="button" class="btn" onclick="closeModal('password-modal')">Cancel</button>
                            <button type="submit" name="change_password" class="btn btn-blue">Change Password</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Other sections -->
            <div id="teachers" class="dashboard-section">
                <?php 
                define('INCLUDED', true);
                if (file_exists('sections/teacher_management.php')) {
                    include 'sections/teacher_management.php';
                } else {
                    echo '<div class="section-header"><h2>Teacher Management</h2></div>';
                    echo '<p style="text-align: center; padding: 20px;">Teacher management section is currently unavailable.</p>';
                }
                ?>
            </div>

            <!-- Courses Section -->
            <div id="courses" class="dashboard-section">
                <?php 
                if (file_exists('sections/course_management.php')) {
                    include 'sections/course_management.php';
                } else {
                    echo '<div class="section-header"><h2>Course Management</h2></div>';
                    echo '<p style="text-align: center; padding: 20px;">Course management section is currently unavailable.</p>';
                }
                ?>
            </div>

            <div id="pending" class="dashboard-section">
                <?php 
                if (file_exists('sections/pending_approvals.php')) {
                    include 'sections/pending_approvals.php';
                } else {
                    echo '<div class="section-header"><h2>Pending Approvals</h2></div>';
                    echo '<p style="text-align: center; padding: 20px;">Pending approvals section is currently unavailable.</p>';
                }
                ?>
            </div>
            
            <!-- Reports Section -->
            <div id="reports-section" class="dashboard-section">
                <div class="section-header">
                    <h2>Report Generation</h2>
                </div>
                <p>Generate and analyze different types of reports.</p>
                
                <div class="report-cards-container" style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 20px; margin-bottom: 30px;">
                    <!-- Student Enrollment Report Card -->
                    <div class="report-card" style="flex: 1; min-width: 300px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <div style="display: flex; align-items: center; margin-bottom: 15px;">
                            <i class="fas fa-user-graduate" style="font-size: 2rem; color: #2196F3; margin-right: 15px;"></i>
                            <h3 style="margin: 0;">Student Enrollment Report</h3>
                        </div>
                        <p>Generate a report of student enrollments across all courses or filter by specific course.</p>
                        
                        <form id="enrollment-report-form" action="generate_report.php" method="GET" target="_blank">
                            <input type="hidden" name="type" value="enrollment">
                            <input type="hidden" name="format" value="json">
                            
                            <div style="margin-bottom: 15px;">
                                <label for="course_id" style="display: block; margin-bottom: 5px; font-weight: 500;">Course</label>
                                <select name="course_id" id="course_id" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="all">All Courses</option>
                                    <?php foreach ($allCourses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label for="date_from" style="display: block; margin-bottom: 5px; font-weight: 500;">From Date</label>
                                <input type="date" name="date_from" id="date_from" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label for="date_to" style="display: block; margin-bottom: 5px; font-weight: 500;">To Date</label>
                                <input type="date" name="date_to" id="date_to" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            
                            <button type="button" onclick="generateReport('enrollment-report-form')" class="btn btn-blue" style="width: 100%;">Generate Report</button>
                        </form>
                    </div>
                    
                    <!-- Course Performance Report Card -->
                    <div class="report-card" style="flex: 1; min-width: 300px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <div style="display: flex; align-items: center; margin-bottom: 15px;">
                            <i class="fas fa-chart-line" style="font-size: 2rem; color: #4CAF50; margin-right: 15px;"></i>
                            <h3 style="margin: 0;">Course Performance Report</h3>
                        </div>
                        <p>Analyze course performance including enrollment rates, completion rates, and student feedback.</p>
                        
                        <form id="performance-report-form" action="generate_report.php" method="GET" target="_blank">
                            <input type="hidden" name="type" value="performance">
                            <input type="hidden" name="format" value="json">
                            
                            <div style="margin-bottom: 15px;">
                                <label for="course_id_perf" style="display: block; margin-bottom: 5px; font-weight: 500;">Course</label>
                                <select name="course_id" id="course_id_perf" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="all">All Courses</option>
                                    <?php foreach ($allCourses as $course): ?>
                                        <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label for="period" style="display: block; margin-bottom: 5px; font-weight: 500;">Time Period</label>
                                <select name="period" id="period" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="all_time">All Time</option>
                                    <option value="last_month">Last Month</option>
                                    <option value="last_quarter">Last Quarter</option>
                                    <option value="last_year">Last Year</option>
                                </select>
                            </div>
                            
                            <button type="button" onclick="generateReport('performance-report-form')" class="btn btn-blue" style="width: 100%; margin-top: 47px;">Generate Report</button>
                        </form>
                    </div>
                    
                    <!-- Teacher Activity Report Card -->
                    <div class="report-card" style="flex: 1; min-width: 300px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <div style="display: flex; align-items: center; margin-bottom: 15px;">
                            <i class="fas fa-chalkboard-teacher" style="font-size: 2rem; color: #FF9800; margin-right: 15px;"></i>
                            <h3 style="margin: 0;">Teacher Activity Report</h3>
                        </div>
                        <p>Review teacher activities including course creation, assignment grading, and student interactions.</p>
                        
                        <form id="teacher-report-form" action="generate_report.php" method="GET" target="_blank">
                            <input type="hidden" name="type" value="teacher">
                            <input type="hidden" name="format" value="json">
                            
                            <div style="margin-bottom: 15px;">
                                <label for="teacher_id" style="display: block; margin-bottom: 5px; font-weight: 500;">Teacher</label>
                                <select name="teacher_id" id="teacher_id" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                                    <option value="all">All Teachers</option>
                <?php 
                                    $stmt = $conn->prepare('SELECT id, username FROM users WHERE role = "teacher" AND verified = 1');
                                    $result = $stmt->execute();
                                    while ($teacher = $result->fetchArray(SQLITE3_ASSOC)): 
                                    ?>
                                        <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['username']); ?></option>
                                    <?php endwhile; ?>
                                </select>
            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label for="date_from_teacher" style="display: block; margin-bottom: 5px; font-weight: 500;">From Date</label>
                                <input type="date" name="date_from" id="date_from_teacher" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label for="date_to_teacher" style="display: block; margin-bottom: 5px; font-weight: 500;">To Date</label>
                                <input type="date" name="date_to" id="date_to_teacher" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            
                            <button type="button" onclick="generateReport('teacher-report-form')" class="btn btn-blue" style="width: 100%;">Generate Report</button>
                        </form>
                    </div>
                </div>
                
                <div class="report-display-container" style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px; display: none;" id="report-display">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <h3 id="report-title" style="margin: 0;">Report Results</h3>
                        <div style="display: flex; gap: 10px;">
                            <button onclick="downloadReport('csv')" class="btn" style="background: #4CAF50; color: white; border: none; padding: 8px 15px; border-radius: 4px;">
                                <i class="fas fa-file-csv"></i> CSV
                            </button>
                            <button onclick="downloadReport('excel')" class="btn" style="background: #2196F3; color: white; border: none; padding: 8px 15px; border-radius: 4px;">
                                <i class="fas fa-file-excel"></i> Excel
                            </button>
                        </div>
                    </div>
                    
                    <!-- Loading Indicator -->
                    <div id="reportLoadingIndicator" style="display: none; justify-content: center; align-items: center; padding: 30px; flex-direction: column;">
                        <div style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 30px; height: 30px; animation: spin 2s linear infinite;"></div>
                        <p style="margin-top: 15px;">Loading report data...</p>
                    </div>
                    
                    <!-- Advanced Filters Section -->
                    <div class="filter-container" style="margin-bottom: 20px; background: #f9f9f9; padding: 15px; border-radius: 6px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h4 style="margin: 0;">Filters</h4>
                            <button onclick="toggleFilters()" class="btn" style="background: #607D8B; color: white; border: none; padding: 5px 10px; border-radius: 4px; font-size: 0.8rem;">
                                <i class="fas fa-filter"></i> <span id="toggle-filter-text">Show Filters</span>
                            </button>
                        </div>
                        
                        <div id="advanced-filters" style="display: none; margin-top: 10px;">
                            <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 10px;">
                                <!-- Text search filter -->
                                <div style="flex: 1; min-width: 200px;">
                                    <label for="report-search" style="display: block; margin-bottom: 5px; font-weight: 500;">Search</label>
                                    <input type="text" id="report-search" placeholder="Search in all fields..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                                
                                <!-- Status filter -->
                                <div style="flex: 1; min-width: 200px;" id="status-filter-container">
                                    <label for="status-filter" style="display: block; margin-bottom: 5px; font-weight: 500;">Status</label>
                                    <select id="status-filter" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        <option value="all">All Statuses</option>
                                    </select>
                                </div>
                                
                                <!-- Course filter (for enrollment & teacher reports) -->
                                <div style="flex: 1; min-width: 200px;" id="course-filter-container">
                                    <label for="course-filter" style="display: block; margin-bottom: 5px; font-weight: 500;">Course</label>
                                    <select id="course-filter" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        <option value="all">All Courses</option>
                                        <?php foreach ($allCourses as $course): ?>
                                            <option value="<?php echo htmlspecialchars($course['title']); ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div style="display: flex; flex-wrap: wrap; gap: 15px;">
                                <!-- Date range filter -->
                                <div style="flex: 1; min-width: 200px;" id="date-filter-container">
                                    <label for="date-filter" style="display: block; margin-bottom: 5px; font-weight: 500;">Enrollment Date</label>
                                    <div style="display: flex; gap: 5px;">
                                        <input type="date" id="date-filter-from" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="From">
                                        <input type="date" id="date-filter-to" style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" placeholder="To">
                                    </div>
                                </div>
                                
                                <!-- Additional field filter (dynamic based on report type) -->
                                <div style="flex: 1; min-width: 200px;" id="additional-filter-container">
                                    <label for="additional-filter" id="additional-filter-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Additional Filter</label>
                                    <select id="additional-filter" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                        <option value="all">All</option>
                                    </select>
                                </div>
                                
                                <!-- Apply/Reset filters -->
                                <div style="flex: 1; min-width: 200px; display: flex; align-items: flex-end;">
                                    <div style="display: flex; gap: 10px; width: 100%;">
                                        <button onclick="applyFilters()" class="btn btn-blue" style="flex: 1; padding: 8px; border: none; border-radius: 4px;">Apply Filters</button>
                                        <button onclick="resetFilters()" class="btn" style="flex: 1; padding: 8px; background: #f5f5f5; color: #333; border: none; border-radius: 4px;">Reset</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="report-table-container" style="overflow-x: auto;">
                        <table id="report-table" style="width: 100%; border-collapse: collapse;">
                            <thead id="report-headers">
                                <!-- Report headers will be inserted here -->
                            </thead>
                            <tbody id="report-rows">
                                <!-- Report rows will be inserted here -->
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 15px;">
                        <div id="report-pagination" style="display: flex; gap: 5px;">
                            <!-- Pagination will be inserted here -->
                        </div>
                        <div>
                            <span>Rows per page:</span>
                            <select id="rows-per-page" onchange="changeRowsPerPage()">
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Handle section display on page load
        document.addEventListener('DOMContentLoaded', function() {
            // First, hide all sections except the default one
            document.querySelectorAll('.dashboard-section').forEach(section => {
                if (section.id !== 'students') {
                    section.style.display = 'none';
                }
            });
            
            // Get section from URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            const section = urlParams.get('section');
            
            // If section parameter exists, show that section
            if (section && ['students', 'teachers', 'courses', 'pending', 'reports'].includes(section)) {
                const sectionId = section === 'reports' ? 'reports-section' : section;
                showSection(sectionId);
            }
            
            // Add click event listeners to nav links
            document.querySelectorAll('.nav-item').forEach(link => {
                link.addEventListener('click', function(e) {
                    // Only handle section navigation links
                    if (this.getAttribute('href') && this.getAttribute('href').includes('?section=')) {
                        e.preventDefault();
                        const section = new URLSearchParams(this.getAttribute('href').split('?')[1]).get('section');
                        if (section) {
                            const sectionId = section === 'reports' ? 'reports-section' : section;
                            showSection(sectionId);
                        }
                    }
                    // Direct links like profile.php and logout.php will work normally
                });
            });
            
            // Handle window resize for dropdowns
            window.addEventListener('resize', function() {
                const openDropdowns = document.querySelectorAll('.dropdown-menu.show');
                openDropdowns.forEach(dropdown => {
                    const dropdownId = dropdown.id;
                    if (dropdown.classList.contains('show')) {
                        // Reposition the dropdown
                        const dropdownContainer = dropdown.parentElement;
                        const rect = dropdown.getBoundingClientRect();
                        const spaceBelow = window.innerHeight - rect.bottom;
                        
                        // If there's not enough space below, show dropdown above
                        if (spaceBelow < 100 && rect.top > 150) {
                            dropdownContainer.classList.add('bottom-aligned');
                        } else {
                            dropdownContainer.classList.remove('bottom-aligned');
                        }
                        
                        // Check if dropdown is going out of the right edge
                        const spaceRight = window.innerWidth - rect.right;
                        if (spaceRight < 0) {
                            dropdown.style.right = 'auto';
                            dropdown.style.left = '0';
                        } else {
                            dropdown.style.right = '';
                            dropdown.style.left = '';
                        }
                    }
                });
            });
            
            // Handle scroll events for dropdowns
            window.addEventListener('scroll', function() {
                // Close all open dropdowns when scrolling
                document.querySelectorAll('.dropdown-menu.show').forEach(dropdown => {
                    dropdown.classList.remove('show');
                    dropdown.parentElement.classList.remove('bottom-aligned');
                });
            });
            
            // Initialize search functionality for reports
            const searchInput = document.getElementById('report-search');
            if (searchInput) {
                searchInput.addEventListener('input', function(e) {
                    const searchTerm = e.target.value.toLowerCase();
                    
                    if (!reportData) return;
                    
                    if (searchTerm.trim() === '') {
                        filteredData = [...reportData.rows];
                    } else {
                        filteredData = reportData.rows.filter(row => {
                            return Object.values(row).some(value => 
                                String(value).toLowerCase().includes(searchTerm)
                            );
                        });
                    }
                    
                    // Reset to first page
                    currentPage = 1;
                    displayReport();
                });
            }
        });
        
        function showSection(sectionId) {
            // Hide all sections first
            document.querySelectorAll('.dashboard-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected section
            const selectedSection = document.getElementById(sectionId);
            if (selectedSection) {
                selectedSection.style.display = 'block';
            }
            
            // Update nav items
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            
            // Find and activate the correct nav item
            const navItem = document.querySelector(`.nav-item[href*="section=${sectionId.replace('-section', '')}"]`);
            if (navItem) {
                navItem.classList.add('active');
            }
            
            // Update URL with section parameter
            const url = new URL(window.location.href);
            url.searchParams.set('section', sectionId.replace('-section', ''));
            window.history.pushState({}, '', url);
        }
        
        // Add event listener for section changes
        document.addEventListener('sectionChanged', function(e) {
            if (e.detail.section === 'reports-section') {
                // Ensure all report components are properly initialized
                initializeReportComponents();
            }
        });
        
        // Function to initialize report components
        function initializeReportComponents() {
            // Initialize report containers if they don't exist
            const containers = ['report-headers', 'report-rows', 'report-pagination'];
            containers.forEach(containerId => {
                if (!document.getElementById(containerId)) {
                    const container = document.createElement('div');
                    container.id = containerId;
                    document.getElementById('report-preview').appendChild(container);
                }
            });
            
            // Initialize event listeners for report forms if not already initialized
            document.querySelectorAll('.report-form').forEach(form => {
                if (!form.hasAttribute('data-initialized')) {
                    const reportType = form.id.split('-')[0]; // e.g., 'enrollment' from 'enrollment-report-form'
                    const generateButton = form.querySelector('button');
                    if (generateButton) {
                        generateButton.onclick = () => generateReport(reportType);
                    }
                    form.setAttribute('data-initialized', 'true');
                }
            });
        }
        
        // Update the initial section display on page load
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const section = urlParams.get('section') || 'dashboard-section';
            showSection(section);
        });
        
        // Function to toggle dropdown menu
        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            dropdown.classList.toggle('show');
            
            // Adjust dropdown position based on viewport
            if (dropdown.classList.contains('show')) {
                const dropdownContainer = dropdown.parentElement;
                const rect = dropdown.getBoundingClientRect();
                const spaceBelow = window.innerHeight - rect.bottom;
                
                // If there's not enough space below, show dropdown above
                if (spaceBelow < 100 && rect.top > 150) {
                    dropdownContainer.classList.add('bottom-aligned');
                } else {
                    dropdownContainer.classList.remove('bottom-aligned');
                }
                
                // Check if dropdown is going out of the right edge
                const spaceRight = window.innerWidth - rect.right;
                if (spaceRight < 0) {
                    dropdown.style.right = 'auto';
                    dropdown.style.left = '0';
                }
            }
            
            // Close other dropdowns
            var dropdowns = document.getElementsByClassName('dropdown-menu');
            for (var i = 0; i < dropdowns.length; i++) {
                var openDropdown = dropdowns[i];
                if (openDropdown.id !== dropdownId && openDropdown.classList.contains('show')) {
                    openDropdown.classList.remove('show');
                    openDropdown.parentElement.classList.remove('bottom-aligned');
                }
            }
            
            // Prevent event from bubbling up
            event.stopPropagation();
        }
        
        // Close dropdowns and modals when clicking outside
        window.onclick = function(event) {
            // Handle dropdowns
            if (!event.target.matches('.actions-btn') && !event.target.matches('.actions-btn i')) {
                var dropdowns = document.getElementsByClassName('dropdown-menu');
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
                }
            }
            
            // Handle modals
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        };
        
        // Show courses modal
        function showCoursesModal(studentId) {
            const studentData = document.getElementById(`student-courses-${studentId}`);
            const studentName = studentData.getAttribute('data-student-name');
            const coursesList = document.getElementById('courses-list');
            
            // Clear previous content
            coursesList.innerHTML = '';
            
            // Set student name in modal
            document.getElementById('modal-student-name').textContent = studentName;
            
            // Get all course data
            const courses = studentData.querySelectorAll('div');
            
            // Populate course list
            courses.forEach(course => {
                const courseId = course.getAttribute('data-course-id');
                const courseTitle = course.getAttribute('data-course-title');
                
                const li = document.createElement('li');
                li.innerHTML = `
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span>${courseTitle}</span>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="student_id" value="${studentId}">
                            <input type="hidden" name="course_id" value="${courseId}">
                            <button type="submit" name="cancel_enrollment" class="btn btn-sm btn-red" 
                                    onclick="return confirm('Remove student from this course?')">
                                Remove
                            </button>
                        </form>
                    </div>
                `;
                coursesList.appendChild(li);
            });
            
            // Show modal
            document.getElementById('courses-modal').style.display = 'block';
        }
        
        // Show enroll modal
        function showEnrollModal(studentId, studentName) {
            // Set student info
            document.getElementById('enroll-student-name').textContent = studentName;
            document.getElementById('enroll-student-id').value = studentId;
            
            // Get available courses
            const studentData = document.getElementById(`student-courses-${studentId}`);
            const enrolledCourseIds = Array.from(studentData.querySelectorAll('div')).map(div => 
                div.getAttribute('data-course-id')
            );
            
            // Populate course select
            const courseSelect = document.getElementById('course-select');
            courseSelect.innerHTML = '<option value="">-- Select a Course --</option>';
            
            // Get all courses from the page
            <?php
            echo "const allCourses = " . json_encode($allCourses) . ";";
            ?>
            
            // Add only courses the student is not enrolled in
            allCourses.forEach(course => {
                if (!enrolledCourseIds.includes(course.id.toString())) {
                    const option = document.createElement('option');
                    option.value = course.id;
                    option.textContent = course.title;
                    courseSelect.appendChild(option);
                }
            });
            
            // Show modal
            document.getElementById('enroll-modal').style.display = 'block';
        }
        
        // Show password change modal
        function showPasswordModal(userId, userName) {
            // Set user info
            document.getElementById('password-user-name').textContent = userName;
            document.getElementById('password-user-id').value = userId;
            
            // Clear previous values
            document.getElementById('new-password').value = '';
            document.getElementById('confirm-password').value = '';
            
            // Show modal
            document.getElementById('password-modal').style.display = 'block';
        }
        
        // Close modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Delete student
        function deleteStudent(studentId) {
            document.getElementById(`delete-form-${studentId}`).submit();
        }
        
        // Global variables to store report data
        let reportData = null;
        let currentReport = '';
        let currentPage = 1;
        let rowsPerPage = 10;
        let filteredData = [];
        let originalData = [];
        let availableStatuses = [];
        let availableCourses = [];
        let dateFields = {};
        let additionalFields = {};
        
        // Function to toggle advanced filters visibility
        function toggleFilters() {
            const filtersDiv = document.getElementById('advanced-filters');
            const toggleText = document.getElementById('toggle-filter-text');
            
            if (filtersDiv.style.display === 'none') {
                filtersDiv.style.display = 'block';
                toggleText.textContent = 'Hide Filters';
            } else {
                filtersDiv.style.display = 'none';
                toggleText.textContent = 'Show Filters';
            }
        }
        
        // Function to apply all filters to the report data
        function applyFilters() {
            if (!reportData) return;
            
            // Get filter values
            const searchTerm = document.getElementById('report-search').value.toLowerCase();
            const statusFilter = document.getElementById('status-filter').value;
            const courseFilter = document.getElementById('course-filter').value;
            const dateFromFilter = document.getElementById('date-filter-from').value;
            const dateToFilter = document.getElementById('date-filter-to').value;
            const additionalFilter = document.getElementById('additional-filter').value;
            
            // Start with original data
            filteredData = [...originalData];
            
            // Apply search filter
            if (searchTerm.trim() !== '') {
                filteredData = filteredData.filter(row => {
                    return Object.values(row).some(value => 
                        String(value).toLowerCase().includes(searchTerm)
                    );
                });
            }
            
            // Apply status filter
            if (statusFilter !== 'all') {
                filteredData = filteredData.filter(row => {
                    return row.status && row.status.toLowerCase() === statusFilter.toLowerCase();
                });
            }
            
            // Apply course filter
            if (courseFilter !== 'all') {
                filteredData = filteredData.filter(row => {
                    // Check if course column exists
                    if (row.course) {
                        return row.course.toLowerCase() === courseFilter.toLowerCase();
                    }
                    // For teacher reports, check course_titles
                    if (row.course_titles) {
                        return row.course_titles.toLowerCase().includes(courseFilter.toLowerCase());
                    }
                    // For performance reports
                    if (row.course_title) {
                        return row.course_title.toLowerCase() === courseFilter.toLowerCase();
                    }
                    return false;
                });
            }
            
            // Apply date filters if a date field exists
            if (dateFromFilter || dateToFilter) {
                const dateField = getDateFieldForCurrentReport();
                if (dateField) {
                    filteredData = filteredData.filter(row => {
                        if (!row[dateField]) return true;
                        
                        const rowDate = new Date(row[dateField]);
                        
                        // Skip invalid dates
                        if (isNaN(rowDate.getTime())) return true;
                        
                        if (dateFromFilter && dateToFilter) {
                            const fromDate = new Date(dateFromFilter);
                            const toDate = new Date(dateToFilter);
                            toDate.setHours(23, 59, 59); // Include the whole day
                            return rowDate >= fromDate && rowDate <= toDate;
                        } else if (dateFromFilter) {
                            const fromDate = new Date(dateFromFilter);
                            return rowDate >= fromDate;
                        } else if (dateToFilter) {
                            const toDate = new Date(dateToFilter);
                            toDate.setHours(23, 59, 59); // Include the whole day
                            return rowDate <= toDate;
                        }
                        
                        return true;
                    });
                }
            }
            
            // Apply additional filter
            if (additionalFilter !== 'all' && getAdditionalFieldForCurrentReport()) {
                const additionalField = getAdditionalFieldForCurrentReport();
                filteredData = filteredData.filter(row => {
                    if (!row[additionalField]) return false;
                    return String(row[additionalField]).toLowerCase() === additionalFilter.toLowerCase();
                });
            }
            
            // Reset to first page and redisplay
            currentPage = 1;
            displayReport();
        }
        
        // Function to reset all filters
        function resetFilters() {
            document.getElementById('report-search').value = '';
            document.getElementById('status-filter').value = 'all';
            document.getElementById('course-filter').value = 'all';
            document.getElementById('date-filter-from').value = '';
            document.getElementById('date-filter-to').value = '';
            document.getElementById('additional-filter').value = 'all';
            
            // Reset filtered data to original data
            filteredData = [...originalData];
            currentPage = 1;
            displayReport();
        }
        
        // Helper function to get the appropriate date field based on report type
        function getDateFieldForCurrentReport() {
            if (currentReport === 'enrollment') return 'enrolled_date';
            if (currentReport === 'performance') return 'start_date';
            if (currentReport === 'teacher') return 'created_at';
            return null;
        }
        
        // Helper function to get the appropriate additional field based on report type
        function getAdditionalFieldForCurrentReport() {
            if (currentReport === 'enrollment') return 'student';
            if (currentReport === 'performance') return 'completion_rate';
            if (currentReport === 'teacher') return 'teacher';
            return null;
        }
        
        // Function to customize filter fields based on report type
        function customizeFiltersForReportType() {
            const dateFilterLabel = document.getElementById('date-filter-container').querySelector('label');
            const additionalFilterLabel = document.getElementById('additional-filter-label');
            const additionalFilter = document.getElementById('additional-filter');
            
            // Clear additional filter options except 'all'
            while (additionalFilter.options.length > 1) {
                additionalFilter.remove(1);
            }
            
            if (currentReport === 'enrollment') {
                dateFilterLabel.textContent = 'Enrollment Date';
                additionalFilterLabel.textContent = 'Student Name';
                
                // Populate additional filter with student names
                const students = new Set();
                originalData.forEach(row => {
                    if (row.student) students.add(row.student);
                });
                
                students.forEach(student => {
                    const option = document.createElement('option');
                    option.value = student;
                    option.textContent = student;
                    additionalFilter.appendChild(option);
                });
            } 
            else if (currentReport === 'performance') {
                dateFilterLabel.textContent = 'Start Date';
                additionalFilterLabel.textContent = 'Completion Rate';
                
                // Populate additional filter with completion rates
                const rates = new Set();
                originalData.forEach(row => {
                    if (row.completion_rate) rates.add(row.completion_rate);
                });
                
                rates.forEach(rate => {
                    const option = document.createElement('option');
                    option.value = rate;
                    option.textContent = rate;
                    additionalFilter.appendChild(option);
                });
            } 
            else if (currentReport === 'teacher') {
                dateFilterLabel.textContent = 'Activity Date';
                additionalFilterLabel.textContent = 'Teacher Name';
                
                // Populate additional filter with teacher names
                const teachers = new Set();
                originalData.forEach(row => {
                    if (row.teacher) teachers.add(row.teacher);
                });
                
                teachers.forEach(teacher => {
                    const option = document.createElement('option');
                    option.value = teacher;
                    option.textContent = teacher;
                    additionalFilter.appendChild(option);
                });
            }
        }
        
        // Function to populate status filter dropdown based on available statuses in the data
        function populateStatusFilter() {
            const statusFilter = document.getElementById('status-filter');
            
            // Clear existing options except 'all'
            while (statusFilter.options.length > 1) {
                statusFilter.remove(1);
            }
            
            // Get unique statuses from data
            const statuses = new Set();
            originalData.forEach(row => {
                if (row.status) statuses.add(row.status);
            });
            
            // Add status options
            statuses.forEach(status => {
                const option = document.createElement('option');
                option.value = status;
                option.textContent = status;
                statusFilter.appendChild(option);
            });
        }
        
        // Function to generate the report
        function generateReport(formId) {
            // Get the form
            const form = document.getElementById(formId);
            if (!form) {
                console.error('Form not found:', formId);
                return;
            }
            
            // Show loading indicator
            document.getElementById('reportLoadingIndicator').style.display = 'flex';
            
            // Show the report display container
            document.getElementById('report-display').style.display = 'block';
            
            // Get the report type from the form ID
            currentReport = formId.split('-')[0]; // e.g., 'enrollment' from 'enrollment-report-form'
            
            // Set report title
            let reportTitle = '';
            if (currentReport === 'enrollment') reportTitle = 'Student Enrollment Report';
            else if (currentReport === 'performance') reportTitle = 'Course Performance Report';
            else if (currentReport === 'teacher') reportTitle = 'Teacher Activity Report';
            
            document.getElementById('report-title').textContent = reportTitle;
            
            // Reset filters
            resetFilters();
            
            // Fetch report data
            fetch(`generate_report.php?type=${currentReport}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    // Hide loading indicator
                    document.getElementById('reportLoadingIndicator').style.display = 'none';
                    
                    if (data.error) {
                        // Show error message
                        document.getElementById('report-rows').innerHTML = 
                            `<tr><td colspan="10" style="text-align: center; padding: 20px; color: red;">${data.error}</td></tr>`;
                        return;
                    }
                    
                    // Store the data globally
                    reportData = data;
                    originalData = [...data.rows];
                    filteredData = [...data.rows];
                    currentPage = 1;
                    
                    // Show advanced filters
                    document.getElementById('advanced-filters').style.display = 'block';
                    document.getElementById('toggle-filter-text').textContent = 'Hide Filters';
                    
                    // Customize filters for this report type
                    customizeFiltersForReportType();
                    
                    // Populate status filter
                    populateStatusFilter();
                    
                    // Display the report
                    displayReport();
                })
                .catch(error => {
                    // Hide loading indicator
                    document.getElementById('reportLoadingIndicator').style.display = 'none';
                    
                    console.error('Error fetching report:', error);
                    document.getElementById('report-rows').innerHTML = 
                        `<tr><td colspan="10" style="text-align: center; padding: 20px; color: red;">Error: ${error.message}</td></tr>`;
                });
        }
        
        // Function to display the report data
        function displayReport() {
            if (!reportData) return;
            
            const headersContainer = document.getElementById('report-headers');
            const rowsContainer = document.getElementById('report-rows');
            
            // Clear previous content
            headersContainer.innerHTML = '';
            rowsContainer.innerHTML = '';
            
            // Create header row
            const headerRow = document.createElement('tr');
            Object.values(reportData.headers).forEach(header => {
                const th = document.createElement('th');
                th.textContent = header;
                th.style.padding = '12px 15px';
                th.style.backgroundColor = '#f8f9fa';
                th.style.borderBottom = '1px solid #dee2e6';
                th.style.textAlign = 'left';
                th.style.fontWeight = '500';
                headerRow.appendChild(th);
            });
            headersContainer.appendChild(headerRow);
            
            // Calculate pagination
            const startIndex = (currentPage - 1) * rowsPerPage;
            const endIndex = Math.min(startIndex + rowsPerPage, filteredData.length);
            const displayedRows = filteredData.slice(startIndex, endIndex);
            
            // Create data rows
            if (displayedRows.length === 0) {
                const noDataRow = document.createElement('tr');
                const noDataCell = document.createElement('td');
                noDataCell.textContent = 'No data available';
                noDataCell.colSpan = Object.keys(reportData.headers).length;
                noDataCell.style.textAlign = 'center';
                noDataCell.style.padding = '15px';
                noDataRow.appendChild(noDataCell);
                rowsContainer.appendChild(noDataRow);
            } else {
                displayedRows.forEach((row, rowIndex) => {
                    const tr = document.createElement('tr');
                    tr.style.borderBottom = '1px solid #eee';
                    tr.style.backgroundColor = rowIndex % 2 === 0 ? '#fff' : '#f9f9f9';
                    
                    Object.keys(reportData.headers).forEach(key => {
                        const td = document.createElement('td');
                        td.textContent = row[key] || '';
                        td.style.padding = '12px 15px';
                        // Add special styling for status cells
                        if (key === 'status') {
                            td.style.fontWeight = '500';
                            
                            // Apply different styling based on status value
                            const status = (row[key] || '').toLowerCase();
                            if (status.includes('active') || status === 'enrolled' || status.includes('complete')) {
                                td.style.color = '#2e7d32';
                            } else if (status.includes('pending') || status === 'in progress') {
                                td.style.color = '#f57c00';
                            } else if (status.includes('inactive') || status === 'failed' || status === 'not enrolled') {
                                td.style.color = '#c62828';
                            }
                        }
                        
                        tr.appendChild(td);
                    });
                    
                    rowsContainer.appendChild(tr);
                });
            }
            
            // Update pagination
            updatePagination();
        }
        
        // Function to update pagination controls
        function updatePagination() {
            const paginationContainer = document.getElementById('report-pagination');
            paginationContainer.innerHTML = '';
            
            if (!filteredData.length) return;
            
            const totalPages = Math.ceil(filteredData.length / rowsPerPage);
            
            // Previous button
            if (currentPage > 1) {
                const prevButton = createPaginationButton('Prev', () => {
                    currentPage--;
                    displayReport();
                });
                paginationContainer.appendChild(prevButton);
            }
            
            // Page numbers
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, startPage + 4);
            
            if (endPage - startPage < 4) {
                startPage = Math.max(1, endPage - 4);
            }
            
            for (let i = startPage; i <= endPage; i++) {
                const pageButton = createPaginationButton(i.toString(), () => {
                    currentPage = i;
                    displayReport();
                }, i === currentPage);
                paginationContainer.appendChild(pageButton);
            }
            
            // Next button
            if (currentPage < totalPages) {
                const nextButton = createPaginationButton('Next', () => {
                    currentPage++;
                    displayReport();
                });
                paginationContainer.appendChild(nextButton);
            }
        }
        
        // Helper function to create pagination buttons
        function createPaginationButton(text, onClick, isActive = false) {
            const button = document.createElement('button');
            button.textContent = text;
            button.style.padding = '8px 12px';
            button.style.border = 'none';
            button.style.borderRadius = '4px';
            button.style.cursor = 'pointer';
            
            if (isActive) {
                button.style.backgroundColor = '#2196F3';
                button.style.color = 'white';
            } else {
                button.style.backgroundColor = '#f5f5f5';
                button.style.color = '#333';
            }
            
            button.addEventListener('click', onClick);
            return button;
        }
        
        // Function to change rows per page
        function changeRowsPerPage() {
            rowsPerPage = parseInt(document.getElementById('rows-per-page').value, 10);
            currentPage = 1;
            displayReport();
        }
        
        // Function to download report in different formats
        function downloadReport(format) {
            if (!reportData) return;
            
            // Create a temporary form to submit the filtered data
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'generate_report.php';
            form.target = '_blank';
            
            // Add report type
            const typeInput = document.createElement('input');
            typeInput.type = 'hidden';
            typeInput.name = 'type';
            typeInput.value = currentReport;
            form.appendChild(typeInput);
            
            // Add format
            const formatInput = document.createElement('input');
            formatInput.type = 'hidden';
            formatInput.name = 'format';
            formatInput.value = format;
            form.appendChild(formatInput);
            
            // Add filtered data
            const dataInput = document.createElement('input');
            dataInput.type = 'hidden';
            dataInput.name = 'filtered_data';
            dataInput.value = JSON.stringify(filteredData);
            form.appendChild(dataInput);
            
            // Add to document, submit and remove
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
    </script>
</body>
</html>