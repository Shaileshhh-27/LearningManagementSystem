<?php
require_once 'dddd/auth/Auth.php';
require_once 'dddd/config/database.php';

$db = new Database();
$conn = $db->getConnection();

// New admin credentials
$newUsername = 'admin';
$newPassword = 'admin123';
$newEmail = 'admin11@gmail.com';

// Hash the new password
$hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

try {
    // First, check if admin exists
    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM users WHERE role = "admin"');
    $result = $stmt->execute();
    $adminExists = $result->fetchArray(SQLITE3_ASSOC)['count'] > 0;

    if ($adminExists) {
        // Update existing admin
        $stmt = $conn->prepare('
            UPDATE users 
            SET username = :username,
                password = :password,
                email = :email,
                verified = 1
            WHERE role = "admin"
        ');
    } else {
        // Create new admin
        $stmt = $conn->prepare('
            INSERT INTO users (username, password, email, role, verified)
            VALUES (:username, :password, :email, "admin", 1)
        ');
    }

    $stmt->bindValue(':username', $newUsername, SQLITE3_TEXT);
    $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT);
    $stmt->bindValue(':email', $newEmail, SQLITE3_TEXT);
    
    if ($stmt->execute()) {
        echo "<div style='text-align: center; padding: 20px; font-family: Arial, sans-serif;'>";
        echo "<h2 style='color: #4CAF50;'>Admin Account " . ($adminExists ? "Updated" : "Created") . " Successfully!</h2>";
        echo "<p style='margin: 20px 0;'>";
        echo "Username: <strong>$newUsername</strong><br>";
        echo "Password: <strong>$newPassword</strong><br>";
        echo "Email: <strong>$newEmail</strong>";
        echo "</p>";
        echo "<p><a href='dddd/index.php' style='color: #1a73e8; text-decoration: none;'>Go to Login Page</a></p>";
        echo "</div>";
    } else {
        throw new Exception("Failed to " . ($adminExists ? "update" : "create") . " admin account");
    }

} catch (Exception $e) {
    echo "<div style='text-align: center; padding: 20px; font-family: Arial, sans-serif;'>";
    echo "<h2 style='color: #d93025;'>Error</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='dddd/index.php' style='color: #1a73e8; text-decoration: none;'>Go Back</a></p>";
    echo "</div>";
}
?> 