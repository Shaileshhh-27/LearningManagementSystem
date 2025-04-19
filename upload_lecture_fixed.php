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

// Get the course ID from URL parameter
$courseId = $_GET['course_id'] ?? 0;

// Check if the course belongs to this teacher
$stmt = $conn->prepare('SELECT * FROM courses WHERE id = :course_id AND teacher_id = :teacher_id');
$stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
$stmt->bindValue(':teacher_id', $_SESSION['user_id'], SQLITE3_INTEGER);
$result = $stmt->execute();
$course = $result->fetchArray(SQLITE3_ASSOC);

if (!$course) {
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
    } else if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a video file to upload.';
    } else {
        // Handle file upload
        $uploadDir = 'uploads/lectures/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = uniqid() . '_' . basename($_FILES['video']['name']);
        $targetPath = $uploadDir . $fileName;
        
        // Check file type
        $allowedTypes = array(
            'video/mp4',
            'video/webm',
            'video/ogg'
        );
        
        if (in_array($_FILES['video']['type'], $allowedTypes)) {
            if (move_uploaded_file($_FILES['video']['tmp_name'], $targetPath)) {
                // Successfully uploaded the file, now save lecture info
                $stmt = $conn->prepare('
                    INSERT INTO lectures (course_id, title, description, video_path) 
                    VALUES (:course_id, :title, :description, :video_path)
                ');
                $stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
                $stmt->bindValue(':title', $title, SQLITE3_TEXT);
                $stmt->bindValue(':description', $description, SQLITE3_TEXT);
                $stmt->bindValue(':video_path', $targetPath, SQLITE3_TEXT);
                
                if ($stmt->execute()) {
                    $success = 'Lecture uploaded successfully!';
                } else {
                    $error = 'Failed to save lecture information';
                }
            } else {
                $error = 'Failed to upload video file';
            }
        } else {
            $error = 'Invalid file type. Please upload MP4, WebM, or OGG video files.';
        }
    }
}

// Get existing lectures for this course
$stmt = $conn->prepare('SELECT * FROM lectures WHERE course_id = :course_id ORDER BY upload_date DESC');
$stmt->bindValue(':course_id', $courseId, SQLITE3_INTEGER);
$result = $stmt->execute();

$lectures = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $lectures[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Lecture - <?php echo htmlspecialchars($course['title']); ?></title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/teacher.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <div class="container">
        <h1>Upload Lecture for <?php echo htmlspecialchars($course['title']); ?></h1>
        
        <a href="course.php?id=<?php echo $courseId; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Course
        </a>
        
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
                <h2>Upload New Lecture</h2>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title">Lecture Title</label>
                        <input type="text" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="4"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="video">Video File (MP4, WebM, OGG)</label>
                        <input type="file" id="video" name="video" accept="video/*" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Upload Lecture</button>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                <h2>Existing Lectures</h2>
            </div>
            <div class="card-body">
                <?php if (empty($lectures)): ?>
                    <p>No lectures have been uploaded for this course yet.</p>
                <?php else: ?>
                    <div class="lecture-list">
                        <?php foreach ($lectures as $lecture): ?>
                            <div class="lecture-item">
                                <div class="lecture-info">
                                    <h3><?php echo htmlspecialchars($lecture['title']); ?></h3>
                                    <p><?php echo htmlspecialchars($lecture['description']); ?></p>
                                    <div class="lecture-meta">
                                        <span><i class="fas fa-calendar-alt"></i> <?php echo date('M d, Y', strtotime($lecture['upload_date'])); ?></span>
                                    </div>
                                </div>
                                <div class="lecture-actions">
                                    <a href="view_lecture.php?id=<?php echo $lecture['id']; ?>" class="btn btn-small">
                                        <i class="fas fa-play-circle"></i> View
                                    </a>
                                    <a href="edit_lecture.php?id=<?php echo $lecture['id']; ?>" class="btn btn-small">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <button class="btn btn-small btn-danger" onclick="deleteLecture(<?php echo $lecture['id']; ?>)">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
    function deleteLecture(lectureId) {
        if (confirm('Are you sure you want to delete this lecture?')) {
            window.location.href = 'delete_lecture.php?id=' + lectureId + '&course_id=<?php echo $courseId; ?>';
        }
    }
    </script>
</body>
</html> 