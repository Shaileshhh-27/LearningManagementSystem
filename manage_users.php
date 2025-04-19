<?php
require_once 'auth/Auth.php';
require_once 'config/database.php';

// Only start session if one hasn't been started already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();

// If not logged in or not an admin, redirect to login page
if (!$auth->isLoggedIn() || $auth->getUserRole() !== 'admin') {
    header('Location: index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

$error_message = '';
$success_message = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add':
                    $username = $_POST['username'];
                    $email = $_POST['email'];
                    $password = $_POST['password'];
                    $role = $_POST['role'];

                    // Validate role (only allow teacher and student)
                    if ($role === 'admin') {
                        throw new Exception('Cannot create additional admin accounts');
                    }

                    if (!in_array($role, ['teacher', 'student'])) {
                        throw new Exception('Invalid role selected');
                    }

                    // Check for duplicate username
                    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM users WHERE username = :username');
                    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
                    $result = $stmt->execute();
                    if ($result->fetchArray(SQLITE3_ASSOC)['count'] > 0) {
                        throw new Exception('Username already exists');
                    }

                    // Check for duplicate email
                    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM users WHERE email = :email');
                    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
                    $result = $stmt->execute();
                    if ($result->fetchArray(SQLITE3_ASSOC)['count'] > 0) {
                        throw new Exception('Email already exists');
                    }

                    // Add the new user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare('INSERT INTO users (username, email, password, role, verified) VALUES (:username, :email, :password, :role, 1)');
                    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
                    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
                    $stmt->bindValue(':password', $hashed_password, SQLITE3_TEXT);
                    $stmt->bindValue(':role', $role, SQLITE3_TEXT);
                    $stmt->execute();
                    $success_message = ucfirst($role) . ' added successfully!';
                    break;
                    
                case 'update':
                    $id = $_POST['user_id'];
                    $username = $_POST['username'];
                    $email = $_POST['email'];
                    $role = $_POST['role'];

                    // Don't allow changing user to admin
                    if ($role === 'admin') {
                        throw new Exception('Cannot change user role to admin');
                    }

                    // Check if user exists and get current details
                    $stmt = $conn->prepare('SELECT role FROM users WHERE id = :id');
                    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $user = $result->fetchArray(SQLITE3_ASSOC);
                    
                    if (!$user) {
                        throw new Exception('User not found');
                    }

                    // Check for duplicate username (excluding current user)
                    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM users WHERE username = :username AND id != :id');
                    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
                    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    if ($result->fetchArray(SQLITE3_ASSOC)['count'] > 0) {
                        throw new Exception('Username already exists');
                    }

                    // Check for duplicate email (excluding current user)
                    $stmt = $conn->prepare('SELECT COUNT(*) as count FROM users WHERE email = :email AND id != :id');
                    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
                    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    if ($result->fetchArray(SQLITE3_ASSOC)['count'] > 0) {
                        throw new Exception('Email already exists');
                    }

                    $stmt = $conn->prepare('UPDATE users SET username = :username, email = :email, role = :role WHERE id = :id');
                    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
                    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
                    $stmt->bindValue(':email', $email, SQLITE3_TEXT);
                    $stmt->bindValue(':role', $role, SQLITE3_TEXT);
                    $stmt->execute();
                    $success_message = 'User updated successfully!';
                    break;
                    
                case 'delete':
                    $id = $_POST['user_id'];
                    
                    // Check if user is admin
                    $stmt = $conn->prepare('SELECT role FROM users WHERE id = :id');
                    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $user = $result->fetchArray(SQLITE3_ASSOC);
                    
                    if ($user && $user['role'] === 'admin') {
                        throw new Exception('Cannot delete admin account');
                    }

                    $stmt = $conn->prepare('DELETE FROM users WHERE id = :id');
                    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
                    $stmt->execute();
                    $success_message = 'User deleted successfully!';
                    break;

                case 'change_password':
                    $userId = $_POST['user_id'];
                    $newPassword = $_POST['new_password'];
                    
                    if (empty($newPassword)) {
                        throw new Exception('Password cannot be empty');
                    }

                    // Check if user exists and is not admin
                    $stmt = $conn->prepare('SELECT role FROM users WHERE id = :id');
                    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
                    $result = $stmt->execute();
                    $user = $result->fetchArray(SQLITE3_ASSOC);
                    
                    if (!$user) {
                        throw new Exception('User not found');
                    }

                    // Hash the new password
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    
                    // Update the password
                    $stmt = $conn->prepare('UPDATE users SET password = :password WHERE id = :id');
                    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
                    $stmt->bindValue(':password', $hashedPassword, SQLITE3_TEXT);
                    $stmt->execute();
                    
                    $success_message = 'Password changed successfully!';
                    break;
            }
        } catch (Exception $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Fetch users with role filter if specified
$role = isset($_GET['role']) ? $_GET['role'] : null;
$query = 'SELECT * FROM users';
if ($role) {
    $query .= ' WHERE role = :role';
}
$query .= ' ORDER BY role, username';

$stmt = $conn->prepare($query);
if ($role) {
    $stmt->bindValue(':role', $role, SQLITE3_TEXT);
}
$result = $stmt->execute();
$users = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $users[] = $row;
}

$page_title = $role ? ucfirst($role) . ' Management' : 'Manage Users';
require_once 'includes/admin_header.php';
?>

    <div class="container">
        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><?php echo $page_title; ?></h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus"></i> Add New <?php echo $role ? ucfirst($role) : 'User'; ?>
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <?php if (!$role): ?>
                                <th>Role</th>
                                <?php endif; ?>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <?php if (!$role): ?>
                                <td><?php echo htmlspecialchars($user['role']); ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php if ($user['role'] !== 'admin'): ?>
                                    <button class="btn btn-sm btn-primary edit-user" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editUserModal"
                                            data-id="<?php echo $user['id']; ?>"
                                            data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                            data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                            data-role="<?php echo htmlspecialchars($user['role']); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning change-password"
                                            data-bs-toggle="modal"
                                            data-bs-target="#changePasswordModal"
                                            data-id="<?php echo $user['id']; ?>"
                                            data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                        <i class="fas fa-key"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger delete-user"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteUserModal"
                                            data-id="<?php echo $user['id']; ?>"
                                            data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New <?php echo $role ? ucfirst($role) : 'User'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <?php if (!$role): ?>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" required>
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                            </select>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="role" value="<?php echo $role; ?>">
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add <?php echo $role ? ucfirst($role) : 'User'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit <?php echo $role ? ucfirst($role) : 'User'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" id="edit_username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <?php if (!$role): ?>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" id="edit_role" required>
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                            </select>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="role" value="<?php echo $role; ?>">
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update <?php echo $role ? ucfirst($role) : 'User'; ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete <?php echo $role ? ucfirst($role) : 'User'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <?php echo $role ? strtolower($role) : 'user'; ?> <span id="delete_username"></span>?</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="user_id" id="change_password_user_id">
                        <p>Change password for user: <span id="change_password_username"></span></p>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" class="form-control" name="new_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    </div> <!-- End of container -->

    <!-- Add Back Button -->
    <a href="admin_dashboard.php" class="back-button" id="backButton">
        <i class="fas fa-arrow-left"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle edit user modal
        document.querySelectorAll('.edit-user').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const username = this.dataset.username;
                const email = this.dataset.email;
                const role = this.dataset.role;

                document.getElementById('edit_user_id').value = id;
                document.getElementById('edit_username').value = username;
                document.getElementById('edit_email').value = email;
                document.getElementById('edit_role').value = role;
            });
        });

        // Handle delete user modal
        document.querySelectorAll('.delete-user').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const username = this.dataset.username;

                document.getElementById('delete_user_id').value = id;
                document.getElementById('delete_username').textContent = username;
            });
        });

        // Handle change password modal
        document.querySelectorAll('.change-password').forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const username = this.dataset.username;

                document.getElementById('change_password_user_id').value = id;
                document.getElementById('change_password_username').textContent = username;
            });
        });

        // Back button visibility
        window.addEventListener('scroll', function() {
            const backButton = document.getElementById('backButton');
            if (window.scrollY > 300) {
                backButton.classList.add('visible');
            } else {
                backButton.classList.remove('visible');
            }
        });
    </script>
</body>
</html> 