<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Include database connection
require_once 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $teacherId = $_POST['teacher_id'] ?? null;
    $validityDays = $_POST['validity_days'] ?? 90;
    $eligibilityCriteria = $_POST['eligibility_criteria'] ?? '';
    $status = $_POST['status'] ?? 'pending';
    
    // Validate input
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Course title is required.';
    }
    
    if (empty($description)) {
        $errors[] = 'Course description is required.';
    }
    
    if (empty($errors)) {
        try {
            // Insert new course
            $stmt = $conn->prepare('
                INSERT INTO courses (
                    title, 
                    description, 
                    teacher_id, 
                    validity_days, 
                    eligibility_criteria, 
                    status, 
                    created_at
                ) VALUES (
                    :title, 
                    :description, 
                    :teacher_id, 
                    :validity_days, 
                    :eligibility_criteria, 
                    :status, 
                    :created_at
                )
            ');
            
            $stmt->bindValue(':title', $title, SQLITE3_TEXT);
            $stmt->bindValue(':description', $description, SQLITE3_TEXT);
            $stmt->bindValue(':teacher_id', $teacherId, $teacherId ? SQLITE3_INTEGER : SQLITE3_NULL);
            $stmt->bindValue(':validity_days', $validityDays, SQLITE3_INTEGER);
            $stmt->bindValue(':eligibility_criteria', $eligibilityCriteria, SQLITE3_TEXT);
            $stmt->bindValue(':status', $status, SQLITE3_TEXT);
            $stmt->bindValue(':created_at', date('Y-m-d H:i:s'), SQLITE3_TEXT);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Course created successfully.';
                header('Location: admin_dashboard.php?section=courses');
                exit();
            } else {
                $errors[] = 'Failed to create course.';
            }
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

// Get all teachers for dropdown
$teacherQuery = "SELECT id, username FROM users WHERE role = 'teacher'";
$teacherResult = $conn->query($teacherQuery);
$teachers = [];
while ($teacher = $teacherResult->fetchArray(SQLITE3_ASSOC)) {
    $teachers[] = $teacher;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Course</title>
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
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            border-radius: 12px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        h1 {
            color: #1976D2;
            font-size: 2rem;
            font-weight: 700;
        }

        .btn {
            display: inline-block;
            background: #f0f0f0;
            color: #333;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 1rem;
            transition: all 0.3s;
            font-weight: 600;
        }

        .btn:hover {
            background: #e0e0e0;
            transform: translateY(-2px);
        }

        .btn-blue {
            background: #1976D2;
            color: white;
        }

        .btn-blue:hover {
            background: #1565C0;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
            font-size: 1.05rem;
        }

        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border 0.3s;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        textarea:focus,
        select:focus {
            border-color: #1976D2;
            outline: none;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.2);
        }

        textarea {
            height: 150px;
            resize: vertical;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 5px solid #c62828;
        }

        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 5px solid #2e7d32;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .grid-4 {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .required-field::after {
            content: "*";
            color: #f44336;
            margin-left: 4px;
        }
        
        .help-text {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        
        .section-title {
            font-size: 1.2rem;
            color: #1976D2;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Create New Course</h1>
            <a href="admin_dashboard.php?section=courses#top" class="btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <ul style="margin: 0; padding-left: 20px;">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="grid-2">
                <div class="form-group">
                    <label for="title" class="required-field">Course Title</label>
                    <input type="text" id="title" name="title" required 
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                    <div class="help-text">Enter a descriptive title for the course</div>
                </div>
                
                <div class="form-group">
                    <label for="teacher_id">Assign Teacher</label>
                    <select id="teacher_id" name="teacher_id">
                        <option value="">-- Select Teacher --</option>
                        <?php foreach ($teachers as $teacher): ?>
                            <option value="<?php echo $teacher['id']; ?>" <?php echo (isset($_POST['teacher_id']) && $_POST['teacher_id'] == $teacher['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($teacher['username']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="help-text">Optional - You can assign a teacher later</div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description" class="required-field">Course Description</label>
                <textarea id="description" name="description" required><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                <div class="help-text">Provide a detailed description of what students will learn</div>
            </div>
            
            <h3 class="section-title">Course Requirements</h3>
            
            <div class="form-group">
                <label for="validity_days">Course Validity (Days)</label>
                <input type="number" class="form-control" id="validity_days" name="validity_days" value="90" min="1" required>
                <div class="help-text">Number of days the course will be valid after starting</div>
            </div>
            
            <div class="form-group">
                <label for="eligibility_criteria">Eligibility Criteria</label>
                <textarea class="form-control" id="eligibility_criteria" name="eligibility_criteria" rows="3"></textarea>
                <div class="help-text">Requirements for enrolling in this course (optional)</div>
            </div>
            
            <div class="form-group">
                <label for="status">Status</label>
                <select class="form-control" id="status" name="status" required>
                    <option value="pending">Pending</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
                <div class="help-text">Initial status of the course</div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Course</button>
                <a href="admin_dashboard.php?section=courses" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html> 