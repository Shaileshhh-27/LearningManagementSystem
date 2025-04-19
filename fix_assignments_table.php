<?php
require_once 'config/database.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

$success = false;
$error = false;
$errorMessage = '';
$dbSchemaDetails = '';

try {
    // Get database info first
    $db = new Database();
    $conn = $db->getConnection();
    
    // Check if the column already exists
    $result = $conn->query("PRAGMA table_info(assignments)");
    $tableInfo = [];
    $hasColumn = false;
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $tableInfo[] = $row;
        if ($row['name'] === 'assignment_file') {
            $hasColumn = true;
        }
    }
    
    $dbSchemaDetails = "Current assignments table columns:\n";
    foreach ($tableInfo as $column) {
        $dbSchemaDetails .= "- {$column['name']} ({$column['type']})\n";
    }
    
    if ($hasColumn) {
        $success = true;
        $message = "The column 'assignment_file' already exists in the assignments table.";
    } else {
        // Add the assignment_file column if it doesn't exist
        $result = $conn->exec('ALTER TABLE assignments ADD COLUMN assignment_file TEXT');
        $success = true;
        $message = "The 'assignment_file' column has been successfully added to the assignments table.";
    }
} catch (Exception $e) {
    $error = true;
    $errorMessage = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Assignments Table</title>
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <style>
        :root {
            --primary-color: #4A90E2;
            --primary-hover: #357ABD;
            --secondary-color: #6BB9F0;
            --accent-color: #FFD700;
            --error-color: #FF6B6B;
            --success-color: #4CAF50;
            --text-color: #2C3E50;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 2rem;
        }
        
        .container {
            max-width: 800px;
            width: 100%;
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        }
        
        h1 {
            color: var(--text-color);
            text-align: center;
            margin-bottom: 1.5rem;
        }
        
        .message {
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .success-message {
            background: #E8F5E9;
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }
        
        .error-message {
            background: #FFEBEE;
            color: var(--error-color);
            border: 1px solid var(--error-color);
        }
        
        .icon {
            font-size: 2rem;
            margin-top: 0.25rem;
        }
        
        .message-content {
            flex: 1;
        }
        
        .message-content h2 {
            margin-bottom: 0.5rem;
            font-size: 1.25rem;
        }
        
        .actions {
            text-align: center;
            margin-top: 2rem;
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem 1.5rem;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn-secondary {
            background: #95a5a6;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .details {
            background: #f5f7fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            font-family: monospace;
            white-space: pre-wrap;
            overflow-x: auto;
        }
        
        .fixed-file {
            margin-top: 2rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e0e6ed;
        }
        
        .fixed-file h3 {
            margin-bottom: 1rem;
            color: var(--text-color);
        }
        
        .fixed-file p {
            margin-bottom: 1rem;
        }
        
        .code-block {
            background: #2c3e50;
            color: white;
            padding: 1rem;
            border-radius: 8px;
            font-family: monospace;
            white-space: pre-wrap;
            overflow-x: auto;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Assignments Table Update</h1>
        
        <?php if ($success): ?>
            <div class="message success-message">
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="message-content">
                    <h2>Update Successful!</h2>
                    <p><?php echo $message; ?></p>
                    <?php if (!empty($dbSchemaDetails)): ?>
                        <div class="details"><?php echo nl2br(htmlspecialchars($dbSchemaDetails)); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="fixed-file">
                <h3>Modified create_assignment.php</h3>
                <p>We've also updated your <code>create_assignment.php</code> file to work without the column for now:</p>
                <div class="code-block">
INSERT INTO assignments (
    course_id, title, description, due_date
) VALUES (
    :course_id, :title, :description, :due_date
)
                </div>
                <p>You should still be able to create assignments (without file uploads) while we fix the database structure.</p>
            </div>
        <?php elseif ($error): ?>
            <div class="message error-message">
                <div class="icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="message-content">
                    <h2>Update Failed</h2>
                    <p>There was an error updating the assignments table.</p>
                    <div class="details"><?php echo htmlspecialchars($errorMessage); ?></div>
                    <?php if (!empty($dbSchemaDetails)): ?>
                        <h3>Database Schema</h3>
                        <div class="details"><?php echo nl2br(htmlspecialchars($dbSchemaDetails)); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="actions">
            <a href="dashboard.php" class="btn">
                <i class="fas fa-home"></i> Back to Dashboard
            </a>
            <a href="create_assignment.php" class="btn btn-secondary">
                <i class="fas fa-plus-circle"></i> Create Assignment
            </a>
        </div>
    </div>
</body>
</html> 