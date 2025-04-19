<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';

session_start();
$auth = new Auth();

// Check if user is logged in and is a teacher
if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'teacher') {
    header('Location: index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Get the lecture ID from URL parameter
$lectureId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];

// Check if the lecture exists and belongs to this teacher's course
$stmt = $conn->prepare('
    SELECT l.*, c.id as course_id, c.title as course_title, c.teacher_id 
    FROM lectures l
    JOIN courses c ON l.course_id = c.id
    WHERE l.id = :lecture_id AND c.teacher_id = :teacher_id
');
$stmt->bindValue(':lecture_id', $lectureId, SQLITE3_INTEGER);
$stmt->bindValue(':teacher_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();
$lecture = $result->fetchArray(SQLITE3_ASSOC);

if (!$lecture) {
    header('Location: dashboard.php');
    exit();
}

// Process form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($title)) {
        $error = 'Please enter a title for the lecture.';
    } else {
        try {
            // Update lecture information
            $stmt = $conn->prepare('
                UPDATE lectures 
                SET title = :title, description = :description
                WHERE id = :lecture_id
            ');
            $stmt->bindValue(':lecture_id', $lectureId, SQLITE3_INTEGER);
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':description', $description, SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                $success = 'Lecture updated successfully!';
                
                // Refresh lecture data
                $stmt = $conn->prepare('
                    SELECT l.*, c.id as course_id, c.title as course_title 
                    FROM lectures l
                    JOIN courses c ON l.course_id = c.id
                    WHERE l.id = :lecture_id
                ');
                $stmt->bindValue(':lecture_id', $lectureId, SQLITE3_INTEGER);
                $result = $stmt->execute();
                $lecture = $result->fetchArray(SQLITE3_ASSOC);
            } else {
                $error = 'Failed to update lecture information';
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Lecture - <?php echo htmlspecialchars($lecture['title']); ?></title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/teacher.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <div class="container">
        <h1>Edit Lecture</h1>
        
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> &gt;
            <a href="course.php?id=<?php echo $lecture['course_id']; ?>"><?php echo htmlspecialchars($lecture['course_title']); ?></a> &gt;
            <a href="view_lecture.php?id=<?php echo $lecture['id']; ?>"><?php echo htmlspecialchars($lecture['title']); ?></a> &gt;
            Edit
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Edit Lecture Information</h2>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label for="title">Lecture Title</label>
                        <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($lecture['title']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4"><?php echo htmlspecialchars($lecture['description']); ?></textarea>
                    </div>
                    
                    <div class="video-preview">
                        <h3>Current Video</h3>
                        <video controls width="400">
                            <source src="<?php echo $lecture['video_path']; ?>" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        <p class="help-text">To replace the video, delete this lecture and upload a new one.</p>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Update Lecture</button>
                        <a href="view_lecture.php?id=<?php echo $lecture['id']; ?>" class="btn btn-secondary">Cancel</a>
                        <button type="button" class="btn btn-danger" style="float: right;" onclick="deleteLecture(<?php echo $lecture['id']; ?>)">
                            <i class="fas fa-trash"></i> Delete Lecture
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function deleteLecture(lectureId) {
        if (confirm('Are you sure you want to delete this lecture? This action cannot be undone.')) {
            window.location.href = 'delete_lecture.php?id=' + lectureId + '&course_id=<?php echo $lecture['course_id']; ?>';
        }
    }
    </script>
</body>
</html> 