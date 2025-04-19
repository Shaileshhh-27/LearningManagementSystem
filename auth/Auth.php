<?php
require_once dirname(__DIR__) . '/config/database.php';

class Auth {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->startSession();
        $this->db->busyTimeout(10000); // Wait for 10 seconds
        
        // Check if verified column exists and add it if not
        $this->ensureVerifiedColumnExists();
    }
    
    private function ensureVerifiedColumnExists() {
        // Check if the verified column exists
        $result = $this->db->query("PRAGMA table_info(users)");
        $hasVerifiedColumn = false;
        while ($column = $result->fetchArray(SQLITE3_ASSOC)) {
            if ($column['name'] === 'verified') {
                $hasVerifiedColumn = true;
                break;
            }
        }
        
        // Add the verified column if it doesn't exist
        if (!$hasVerifiedColumn) {
            $this->db->exec('ALTER TABLE users ADD COLUMN verified INTEGER DEFAULT 0');
            // Make any existing admin users verified
            $this->db->exec('UPDATE users SET verified = 1 WHERE role = "admin"');
        }
    }

    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function isUsernameExists($username) {
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM users WHERE username = :username');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC)['count'] > 0;
    }

    private function isEmailExists($email) {
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM users WHERE email = :email');
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC)['count'] > 0;
    }

    public function register($username, $password, $email, $role) {
        // Check if trying to register as admin and if an admin already exists
        if ($role === 'admin') {
            $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM users WHERE role = :role');
            $stmt->bindValue(':role', 'admin', SQLITE3_TEXT);
            $result = $stmt->execute();
            $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
            
            if ($count > 0) {
                throw new Exception('Admin account already exists');
            }
        }

        // Check for existing username
        if ($this->isUsernameExists($username)) {
            throw new Exception('Username already exists');
        }

        // Check for existing email
        if ($this->isEmailExists($email)) {
            throw new Exception('Email already exists');
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Set up the appropriate SQL based on database structure
        $stmt = $this->db->prepare('INSERT INTO users (username, password, email, role, verified) VALUES (:username, :password, :email, :role, :verified)');
        
        if ($stmt === false) {
            throw new Exception('Failed to prepare registration statement');
        }

        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT);
        $stmt->bindValue(':email', $email, SQLITE3_TEXT);
        $stmt->bindValue(':role', $role, SQLITE3_TEXT);
        $stmt->bindValue(':verified', ($role === 'admin') ? 1 : 0, SQLITE3_INTEGER);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to register user');
        }
        return true;
    }

    public function login($username, $password) {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE username = :username');
        $stmt->bindValue(':username', $username, SQLITE3_TEXT);
        $result = $stmt->execute();
        $user = $result->fetchArray(SQLITE3_ASSOC);

        if ($user) {
            // Check if verified column exists
            $hasVerified = isset($user['verified']);
            
            // Only allow login if user is verified or is admin or verified column doesn't exist
            if (password_verify($password, $user['password']) && 
                (!$hasVerified || $user['verified'] == 1 || $user['role'] === 'admin')) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                return true;
            }
        }
        return false;
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function logout() {
        session_destroy();
        session_write_close();
        
        // Clear session variables
        $_SESSION = array();
        
        // Delete the session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
    }

    public function getUserRole() {
        return $_SESSION['role'] ?? null;
    }
    
    public function getCurrentUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function getUsername() {
        return $_SESSION['username'] ?? null;
    }
    
    public function checkAdminExists() {
        $stmt = $this->db->prepare('SELECT COUNT(*) as count FROM users WHERE role = :role');
        $stmt->bindValue(':role', 'admin', SQLITE3_TEXT);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC)['count'] > 0;
    }
}
?>