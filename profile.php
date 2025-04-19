<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';

session_start();
$auth = new Auth();

// Check if user is logged in
if (!$auth->isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Check database connection
if (!$conn) {
    die("Database connection failed. Please try again later.");
}

$userId = $_SESSION['user_id'];
$userRole = $auth->getUserRole();

// Get user information
$stmt = $conn->prepare('SELECT * FROM users WHERE id = :id');
if (!$stmt) {
    die("Failed to prepare statement. Please try again later.");
}
$stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$user = $result->fetchArray(SQLITE3_ASSOC);

if (!$user) {
    $_SESSION['error'] = 'User not found.';
    header('Location: index.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $fullname = trim($_POST['fullname'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        // Validate input
        if (empty($username)) {
            $error_message = 'Username is required.';
        } elseif (empty($email)) {
            $error_message = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Invalid email format.';
        } else {
            // Check if username or email already exists (excluding current user)
            $stmt = $conn->prepare('SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :id');
            $stmt->bindValue(':username', $username, SQLITE3_TEXT);
            $stmt->bindValue(':email', $email, SQLITE3_TEXT);
            $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
            $result = $stmt->execute();
            
            if ($result->fetchArray(SQLITE3_ASSOC)) {
                $error_message = 'Username or email already exists.';
            } else {
                // Update user profile
                $stmt = $conn->prepare('UPDATE users SET username = :username, email = :email, fullname = :fullname, bio = :bio WHERE id = :id');
                $stmt->bindValue(':username', $username, SQLITE3_TEXT);
                $stmt->bindValue(':email', $email, SQLITE3_TEXT);
                $stmt->bindValue(':fullname', $fullname, SQLITE3_TEXT);
                $stmt->bindValue(':bio', $bio, SQLITE3_TEXT);
                $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
                
                if ($stmt->execute()) {
                    $success_message = 'Profile updated successfully.';
                    
                    // Refresh user data
                    $stmt = $conn->prepare('SELECT * FROM users WHERE id = :id');
                    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $user = $result->fetchArray(SQLITE3_ASSOC);
                } else {
                    $error_message = 'Failed to update profile.';
                }
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate input
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = 'All password fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'New passwords do not match.';
        } elseif (strlen($new_password) < 6) {
            $error_message = 'Password must be at least 6 characters long.';
        } elseif (!password_verify($current_password, $user['password'])) {
            $error_message = 'Current password is incorrect.';
        } else {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare('UPDATE users SET password = :password WHERE id = :id');
            $stmt->bindValue(':password', $hashed_password, SQLITE3_TEXT);
            $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                $success_message = 'Password changed successfully.';
            } else {
                $error_message = 'Failed to change password.';
            }
        }
    } elseif (isset($_POST['remove_avatar'])) {
        // Handle avatar removal
        $stmt = $conn->prepare('SELECT avatar FROM users WHERE id = :id');
        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
        $result = $stmt->execute();
        $userData = $result->fetchArray(SQLITE3_ASSOC);
        
        if (!empty($userData['avatar'])) {
            // Delete the file from the server
            $avatarPath = 'uploads/avatars/' . $userData['avatar'];
            if (file_exists($avatarPath)) {
                unlink($avatarPath);
            }
            
            // Update the user record to remove the avatar reference
            $stmt = $conn->prepare('UPDATE users SET avatar = NULL WHERE id = :id');
            $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
            
            if ($stmt->execute()) {
                $success_message = 'Profile picture removed successfully.';
                
                // Refresh user data
                $stmt = $conn->prepare('SELECT * FROM users WHERE id = :id');
                $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $user = $result->fetchArray(SQLITE3_ASSOC);
            } else {
                $error_message = 'Failed to remove profile picture.';
            }
        }
    } elseif (isset($_POST['upload_avatar']) && isset($_FILES['avatar'])) {
        $file = $_FILES['avatar'];
        
        // Check for errors
        if ($file['error'] === UPLOAD_ERR_OK) {
            // Validate file type
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = mime_content_type($file['tmp_name']);
            
            if (!in_array($file_type, $allowed_types)) {
                $error_message = 'Only JPG, PNG, and GIF images are allowed.';
            } else {
                // Create uploads directory if it doesn't exist
                $upload_dir = 'uploads/avatars/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $filename = 'avatar_' . $userId . '_' . time() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
                $target_file = $upload_dir . $filename;
                
                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'], $target_file)) {
                    // Update user avatar in database
                    $stmt = $conn->prepare('UPDATE users SET avatar = :avatar WHERE id = :id');
                    $stmt->bindValue(':avatar', $filename, SQLITE3_TEXT);
                    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
                    
                    if ($stmt->execute()) {
                        $success_message = 'Avatar uploaded successfully.';
                        
                        // Refresh user data
                        $stmt = $conn->prepare('SELECT * FROM users WHERE id = :id');
                        $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
                        $result = $stmt->execute();
                        $user = $result->fetchArray(SQLITE3_ASSOC);
                    } else {
                        $error_message = 'Failed to update avatar in database.';
                    }
                } else {
                    $error_message = 'Failed to upload avatar.';
                }
            }
        } else {
            $error_message = 'Error uploading file: ' . $file['error'];
        }
    }
}

// Get additional user information based on role
$roleSpecificInfo = [];

if ($userRole === 'teacher') {
    // Get courses taught by this teacher
    $stmt = $conn->prepare('
        SELECT c.*, COUNT(e.student_id) as student_count 
        FROM courses c 
        LEFT JOIN enrollments e ON c.id = e.course_id 
        WHERE c.teacher_id = :teacher_id 
        GROUP BY c.id
    ');
    $stmt->bindValue(':teacher_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $courses = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $courses[] = $row;
    }
    
    $roleSpecificInfo['courses'] = $courses;
    $roleSpecificInfo['course_count'] = count($courses);
} elseif ($userRole === 'student') {
    // Get courses enrolled by this student
    $stmt = $conn->prepare('
        SELECT c.*, u.username as teacher_name 
        FROM enrollments e 
        JOIN courses c ON e.course_id = c.id 
        LEFT JOIN users u ON c.teacher_id = u.id 
        WHERE e.student_id = :student_id
    ');
    $stmt->bindValue(':student_id', $userId, SQLITE3_INTEGER);
    $result = $stmt->execute();
    
    $enrolledCourses = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $enrolledCourses[] = $row;
    }
    
    $roleSpecificInfo['enrolled_courses'] = $enrolledCourses;
    $roleSpecificInfo['course_count'] = count($enrolledCourses);
} elseif ($userRole === 'admin') {
    // Get admin-specific statistics
    $stats = [];
    
    // Total users
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM users');
    $result = $stmt->execute();
    $stats['total_users'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    // Total courses
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM courses');
    $result = $stmt->execute();
    $stats['total_courses'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    // Total teachers
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM users WHERE role = "teacher" AND verified = 1');
    $result = $stmt->execute();
    $stats['total_teachers'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    // Total students
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM users WHERE role = "student" AND verified = 1');
    $result = $stmt->execute();
    $stats['total_students'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    // Pending verifications
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM users WHERE verified = 0');
    $result = $stmt->execute();
    $stats['pending_verifications'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    $roleSpecificInfo['stats'] = $stats;
}

// Determine which dashboard to return to based on user role
$dashboardUrl = 'dashboard.php';
if ($userRole === 'admin') {
    $dashboardUrl = 'admin_dashboard.php';
} elseif ($userRole === 'teacher') {
    $dashboardUrl = 'teacher_dashboard.php';
}

// Page title
$pageTitle = 'User Profile';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            color: #333;
            font-size: 24px;
        }
        
        .header .actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            display: inline-block;
            background: #2196F3;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #1976D2;
        }
        
        .btn-secondary {
            background: #757575;
        }
        
        .btn-secondary:hover {
            background: #616161;
        }
        
        .profile-container {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .profile-sidebar {
            flex: 1;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
            max-width: 300px;
        }
        
        .profile-content {
            flex: 3;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .avatar-container {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #f0f0f0;
        }
        
        .user-info {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .user-info h2 {
            margin-bottom: 5px;
            color: #333;
        }
        
        .user-info .role {
            display: inline-block;
            background: #e3f2fd;
            color: #1976D2;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-bottom: 10px;
        }
        
        .user-stats {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #eee;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .tab.active {
            border-bottom: 2px solid #2196F3;
            color: #2196F3;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .course-list {
            margin-top: 20px;
        }
        
        .course-item {
            background: #f9f9f9;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 10px;
        }
        
        .course-item h3 {
            margin-bottom: 5px;
            color: #333;
        }
        
        .course-item p {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .course-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #888;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card i {
            font-size: 2rem;
            color: #2196F3;
            margin-bottom: 10px;
        }
        
        .stat-card h4 {
            color: #666;
            margin-bottom: 10px;
            font-size: 1rem;
        }
        
        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #333;
        }
        
        @media (max-width: 768px) {
            .profile-container {
                flex-direction: column;
            }
            
            .profile-sidebar {
                max-width: 100%;
            }
        }
        
        /* Add these new styles for the user avatar */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .profile-header h2 {
            margin: 0;
            font-size: 24px;
            color: #333;
        }

        .welcome-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .welcome-section img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }

        .welcome-text {
            flex-grow: 1;
        }

        .welcome-text h2 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }

        .welcome-text p {
            margin: 5px 0 0;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="welcome-section">
            <?php if (!empty($user['avatar'])): ?>
                <img src="uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" alt="User Avatar">
            <?php else: ?>
                <img src="assets/images/generate_default_avatar.php?name=<?php echo urlencode($user['username']); ?>&size=60" alt="Default Avatar">
            <?php endif; ?>
            <div class="welcome-text">
                <h2>Welcome back, <?php echo htmlspecialchars(ucfirst($user['username'])); ?>!</h2>
                <p>Here's an overview of your learning journey</p>
            </div>
        </div>
        
        <div class="header">
            <div style="display: flex; align-items: center;">
                <h1>User Profile</h1>
            </div>
            <div class="actions">
                <a href="<?php echo $dashboardUrl; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="profile-container">
            <div class="profile-sidebar">
                <div class="avatar-container">
                    <?php if (!empty($user['avatar'])): ?>
                        <img src="uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" alt="Profile Avatar" class="avatar">
                    <?php else: ?>
                        <img src="assets/images/generate_default_avatar.php?name=<?php echo urlencode($user['username']); ?>&size=150" alt="Default Avatar" class="avatar">
                    <?php endif; ?>
                    
                    <div style="margin-top: 10px; display: flex; justify-content: center; gap: 10px;">
                        <form method="POST" enctype="multipart/form-data">
                            <input type="file" name="avatar" id="avatar" style="display: none;" onchange="this.form.submit()">
                            <input type="hidden" name="upload_avatar" value="1">
                            <label for="avatar" class="btn" style="cursor: pointer; font-size: 0.8rem;">
                                <i class="fas fa-camera"></i> Change Photo
                            </label>
                        </form>
                        
                        <?php if (!empty($user['avatar'])): ?>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to remove your profile picture?');">
                                <input type="hidden" name="remove_avatar" value="1">
                                <button type="submit" class="btn" style="font-size: 0.8rem; background-color: #f44336;">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="user-info">
                    <h2><?php echo htmlspecialchars($user['username']); ?></h2>
                    <span class="role"><?php echo ucfirst(htmlspecialchars($user['role'])); ?></span>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
                
                <div class="user-stats">
                    <div class="stat-item">
                        <span>Member Since</span>
                        <span><?php echo date('M d, Y', strtotime($user['created_at'] ?? 'now')); ?></span>
                    </div>
                    
                    <?php if ($userRole === 'teacher' || $userRole === 'student'): ?>
                        <div class="stat-item">
                            <span>Courses</span>
                            <span><?php echo $roleSpecificInfo['course_count']; ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="stat-item">
                        <span>Status</span>
                        <span><?php echo $user['verified'] ? 'Verified' : 'Pending'; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="profile-content">
                <div class="tabs">
                    <div class="tab active" data-tab="profile">Profile Information</div>
                    <div class="tab" data-tab="password">Change Password</div>
                    <?php if ($userRole === 'teacher'): ?>
                        <div class="tab" data-tab="courses">My Courses</div>
                    <?php elseif ($userRole === 'student'): ?>
                        <div class="tab" data-tab="enrollments">My Enrollments</div>
                    <?php elseif ($userRole === 'admin'): ?>
                        <div class="tab" data-tab="stats">System Statistics</div>
                    <?php endif; ?>
                </div>
                
                <div class="tab-content active" id="profile-tab">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="fullname">Full Name</label>
                            <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="bio">Bio</label>
                            <textarea id="bio" name="bio"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn">Update Profile</button>
                    </form>
                </div>
                
                <div class="tab-content" id="password-tab">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn">Change Password</button>
                    </form>
                </div>
                
                <?php if ($userRole === 'teacher'): ?>
                    <div class="tab-content" id="courses-tab">
                        <h3>Courses You Teach</h3>
                        
                        <?php if (empty($roleSpecificInfo['courses'])): ?>
                            <p>You are not teaching any courses yet.</p>
                        <?php else: ?>
                            <div class="course-list">
                                <?php foreach ($roleSpecificInfo['courses'] as $course): ?>
                                    <div class="course-item">
                                        <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                                        <p><?php echo htmlspecialchars($course['description']); ?></p>
                                        <div class="course-meta">
                                            <span>
                                                <i class="fas fa-users"></i> 
                                                <?php echo $course['student_count']; ?> Students
                                            </span>
                                            <span>
                                                <i class="fas fa-calendar"></i> 
                                                <?php echo isset($course['start_date']) ? date('M d, Y', strtotime($course['start_date'])) : 'N/A'; ?>
                                            </span>
                                            <a href="course.php?id=<?php echo $course['id']; ?>" class="btn" style="font-size: 0.8rem;">
                                                View Course
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($userRole === 'student'): ?>
                    <div class="tab-content" id="enrollments-tab">
                        <h3>Your Enrolled Courses</h3>
                        
                        <?php if (empty($roleSpecificInfo['enrolled_courses'])): ?>
                            <p>You are not enrolled in any courses yet.</p>
                        <?php else: ?>
                            <div class="course-list">
                                <?php foreach ($roleSpecificInfo['enrolled_courses'] as $course): ?>
                                    <div class="course-item">
                                        <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                                        <p><?php echo htmlspecialchars($course['description']); ?></p>
                                        <div class="course-meta">
                                            <span>
                                                <i class="fas fa-chalkboard-teacher"></i> 
                                                <?php echo htmlspecialchars(ucfirst($course['teacher_name'] ?? 'No teacher assigned')); ?>
                                            </span>
                                            <span>
                                                <i class="fas fa-calendar"></i> 
                                                <?php echo isset($course['start_date']) ? date('M d, Y', strtotime($course['start_date'])) : 'N/A'; ?>
                                            </span>
                                            <a href="course.php?id=<?php echo $course['id']; ?>" class="btn" style="font-size: 0.8rem;">
                                                View Course
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($userRole === 'admin'): ?>
                    <div class="tab-content" id="stats-tab">
                        <h3>System Statistics</h3>
                        <div class="stats-grid">
                            <div class="stat-card">
                                <i class="fas fa-users"></i>
                                <h4>Total Users</h4>
                                <p class="stat-number"><?php echo $roleSpecificInfo['stats']['total_users']; ?></p>
                            </div>
                            <div class="stat-card">
                                <i class="fas fa-book"></i>
                                <h4>Total Courses</h4>
                                <p class="stat-number"><?php echo $roleSpecificInfo['stats']['total_courses']; ?></p>
                            </div>
                            <div class="stat-card">
                                <i class="fas fa-chalkboard-teacher"></i>
                                <h4>Total Teachers</h4>
                                <p class="stat-number"><?php echo $roleSpecificInfo['stats']['total_teachers']; ?></p>
                            </div>
                            <div class="stat-card">
                                <i class="fas fa-user-graduate"></i>
                                <h4>Total Students</h4>
                                <p class="stat-number"><?php echo $roleSpecificInfo['stats']['total_students']; ?></p>
                            </div>
                            <div class="stat-card">
                                <i class="fas fa-clock"></i>
                                <h4>Pending Verifications</h4>
                                <p class="stat-number"><?php echo $roleSpecificInfo['stats']['pending_verifications']; ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            // Show first tab by default
            if (tabs.length > 0 && tabContents.length > 0) {
                tabs[0].classList.add('active');
                tabContents[0].classList.add('active');
            }
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Show corresponding content
                    const tabId = this.getAttribute('data-tab');
                    const tabContent = document.getElementById(tabId + '-tab');
                    if (tabContent) {
                        tabContent.classList.add('active');
                    }
                });
            });
            
            // Handle file input change for avatar upload
            const avatarInput = document.getElementById('avatar');
            if (avatarInput) {
                avatarInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        // Auto-submit the form when a file is selected
                        this.closest('form').submit();
                    }
                });
            }
        });
    </script>
</body>
</html> 