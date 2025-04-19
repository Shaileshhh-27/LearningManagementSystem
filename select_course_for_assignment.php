<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';

session_start();
$auth = new Auth();

if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'teacher') {
    header('Location: index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$userId = $_SESSION['user_id'];

// Fetch courses taught by the teacher
$stmt = $conn->prepare('SELECT * FROM courses WHERE teacher_id = :teacher_id');
$stmt->bindValue(':teacher_id', $userId, SQLITE3_INTEGER);
$result = $stmt->execute();

$courses = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $courses[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Course for Assignment</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f2f5; padding: 2rem; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
        h1 { color: #1a73e8; margin-bottom: 1.5rem; text-align: center; }
        .course-list { list-style: none; padding: 0; }
        .course-item { padding: 1rem; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 1rem; }
        .btn { background: #1a73e8; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #1557b0; }
        .back-link { display: block; text-align: center; margin-top: 1rem; color: #1a73e8; text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Select Course for New Assignment</h1>

        <?php if (empty($courses)): ?>
            <p>You don't have any courses yet. <a href="create_course.php">Create a course</a> first.</p>
        <?php else: ?>
            <ul class="course-list">
                <?php foreach ($courses as $course): ?>
                    <li class="course-item">
                        <h3><?php echo htmlspecialchars($course['title']); ?></h3>
                        <p><?php echo htmlspecialchars($course['description']); ?></p>
                        <a href="create_assignment.php?course_id=<?php echo $course['id']; ?>" class="btn">Create Assignment for this Course</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <a href="dashboard.php" class="back-link">Back to Dashboard</a>
    </div>
</body>
</html>