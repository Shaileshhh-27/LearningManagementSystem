<?php
require_once 'auth/Auth.php';

session_start();
$auth = new Auth();

// If user is already logged in, redirect to appropriate dashboard
if ($auth->isLoggedIn()) {
    $role = $auth->getUserRole();
    if ($role === 'admin') {
        header('Location: admin_dashboard.php');
    } else if ($role === 'teacher') {
        header('Location: teacher_dashboard.php');
    } else {
        header('Location: student_dashboard.php');
    }
    exit();
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';
    
    // Validate input
    if (empty($username)) {
        $errors[] = "Username is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($role) || !in_array($role, ['student', 'teacher'])) {
        $errors[] = "Invalid role selected";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            $result = $auth->register($username, $password, $email, $role);
            $success = true;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LMS - Register</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #0061ff, #60efff);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            z-index: 0;
        }

        @keyframes gradient {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            position: relative;
            margin-top: 50px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            z-index: 1;
        }

        .back-button {
            position: absolute;
            top: -45px;
            left: 0;
            text-decoration: none;
            color: #1a73e8;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            background: white;
            padding: 8px 15px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            background: #f8f9fa;
        }

        .back-button i {
            font-size: 16px;
        }

        h1 {
            color: #1a73e8;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #1a73e8;
        }

        .validation-message {
            font-size: 0.8rem;
            margin-top: 0.25rem;
            color: #666;
        }

        .password-strength {
            margin-top: 0.5rem;
        }

        .password-strength-meter {
            height: 4px;
            background: #eee;
            border-radius: 2px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .password-strength-meter-fill {
            height: 100%;
            width: 0;
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .password-requirements {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.5rem;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.25rem;
        }

        .requirement i {
            font-size: 0.8rem;
        }

        .requirement.valid {
            color: #0f9d58;
        }

        .requirement.invalid {
            color: #d93025;
        }

        button {
            background: #1a73e8;
            color: white;
            padding: 0.75rem;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s ease;
        }

        button:hover {
            background: #1557b0;
        }

        button:disabled {
            background: #ccc;
            cursor: not-allowed;
        }

        .error {
            color: #d93025;
            background: #fce8e6;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .success {
            color: #0f9d58;
            background: #e6f4ea;
            padding: 0.75rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }

        .login-link {
            text-align: center;
            margin-top: 1rem;
        }

        .login-link a {
            color: #1a73e8;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="home.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to Homepage
        </a>
        <h1>Register</h1>

        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success">
                Registration successful! Your account is pending verification.
                <br>
                <a href="index.php">Back to Login</a>
            </div>
        <?php else: ?>
            <form method="POST" action="" id="registerForm" novalidate>
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required 
                           pattern="^(?=.*[a-zA-Z])[a-zA-Z0-9]+$"
                           title="Username must contain at least one letter and can include numbers">
                    <div class="validation-message" id="usernameMessage"></div>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                    <div class="validation-message" id="emailMessage"></div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                    <div class="password-strength">
                        <div class="password-strength-meter">
                            <div class="password-strength-meter-fill" id="strengthMeter"></div>
                        </div>
                        <div class="password-requirements">
                            <div class="requirement" id="lengthReq">
                                <i class="fas fa-times"></i> At least 8 characters
                            </div>
                            <div class="requirement" id="upperReq">
                                <i class="fas fa-times"></i> One uppercase letter
                            </div>
                            <div class="requirement" id="lowerReq">
                                <i class="fas fa-times"></i> One lowercase letter
                            </div>
                            <div class="requirement" id="numberReq">
                                <i class="fas fa-times"></i> One number
                            </div>
                            <div class="requirement" id="specialReq">
                                <i class="fas fa-times"></i> One special character
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <div class="validation-message" id="confirmMessage"></div>
                </div>

                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" required>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                    </select>
                </div>

                <button type="submit" id="submitBtn" disabled>Register</button>
            </form>

            <div class="login-link">
                Already have an account? <a href="index.php">Login here</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const form = document.getElementById('registerForm');
        const username = document.getElementById('username');
        const email = document.getElementById('email');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submitBtn');
        const strengthMeter = document.getElementById('strengthMeter');

        // Password requirement elements
        const lengthReq = document.getElementById('lengthReq');
        const upperReq = document.getElementById('upperReq');
        const lowerReq = document.getElementById('lowerReq');
        const numberReq = document.getElementById('numberReq');
        const specialReq = document.getElementById('specialReq');

        let isUsernameValid = false;
        let isEmailValid = false;
        let isPasswordValid = false;
        let isConfirmPasswordValid = false;

        // Username validation
        username.addEventListener('input', validateUsername);

        function validateUsername() {
            const value = username.value;
            const usernameMessage = document.getElementById('usernameMessage');
            const pattern = /^(?=.*[a-zA-Z])[a-zA-Z0-9]+$/;

            if (!value) {
                usernameMessage.textContent = 'Username is required';
                usernameMessage.style.color = '#d93025';
                isUsernameValid = false;
            } else if (!pattern.test(value)) {
                usernameMessage.textContent = 'Username must contain at least one letter and can include numbers';
                usernameMessage.style.color = '#d93025';
                isUsernameValid = false;
            } else {
                // Check if username is unique using AJAX
                fetch(`check_username.php?username=${encodeURIComponent(value)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.exists) {
                            usernameMessage.textContent = 'Username already taken';
                            usernameMessage.style.color = '#d93025';
                            isUsernameValid = false;
                        } else {
                            usernameMessage.textContent = 'Username available';
                            usernameMessage.style.color = '#0f9d58';
                            isUsernameValid = true;
                        }
                        updateSubmitButton();
                    });
            }
            updateSubmitButton();
        }

        // Email validation
        email.addEventListener('input', validateEmail);

        function validateEmail() {
            const value = email.value;
            const emailMessage = document.getElementById('emailMessage');
            const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (!value) {
                emailMessage.textContent = 'Email is required';
                emailMessage.style.color = '#d93025';
                isEmailValid = false;
            } else if (!pattern.test(value)) {
                emailMessage.textContent = 'Please enter a valid email address';
                emailMessage.style.color = '#d93025';
                isEmailValid = false;
            } else {
                // Check if email is unique using AJAX
                fetch(`check_email.php?email=${encodeURIComponent(value)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.exists) {
                            emailMessage.textContent = 'Email already registered';
                            emailMessage.style.color = '#d93025';
                            isEmailValid = false;
                        } else {
                            emailMessage.textContent = 'Email available';
                            emailMessage.style.color = '#0f9d58';
                            isEmailValid = true;
                        }
                        updateSubmitButton();
                    });
            }
            updateSubmitButton();
        }

        // Password validation
        password.addEventListener('input', validatePassword);

        function validatePassword() {
            const value = password.value;
            
            // Check requirements
            const hasLength = value.length >= 8;
            const hasUpper = /[A-Z]/.test(value);
            const hasLower = /[a-z]/.test(value);
            const hasNumber = /[0-9]/.test(value);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(value);

            // Update requirement indicators
            updateRequirement(lengthReq, hasLength);
            updateRequirement(upperReq, hasUpper);
            updateRequirement(lowerReq, hasLower);
            updateRequirement(numberReq, hasNumber);
            updateRequirement(specialReq, hasSpecial);

            // Calculate strength percentage
            let strength = 0;
            if (hasLength) strength += 20;
            if (hasUpper) strength += 20;
            if (hasLower) strength += 20;
            if (hasNumber) strength += 20;
            if (hasSpecial) strength += 20;

            // Update strength meter
            strengthMeter.style.width = `${strength}%`;
            if (strength <= 40) {
                strengthMeter.style.backgroundColor = '#d93025';
            } else if (strength <= 80) {
                strengthMeter.style.backgroundColor = '#ffa000';
            } else {
                strengthMeter.style.backgroundColor = '#0f9d58';
            }

            isPasswordValid = strength >= 80;
            validateConfirmPassword();
            updateSubmitButton();
        }

        function updateRequirement(element, isValid) {
            const icon = element.querySelector('i');
            if (isValid) {
                element.classList.remove('invalid');
                element.classList.add('valid');
                icon.className = 'fas fa-check';
            } else {
                element.classList.remove('valid');
                element.classList.add('invalid');
                icon.className = 'fas fa-times';
            }
        }

        // Confirm password validation
        confirmPassword.addEventListener('input', validateConfirmPassword);

        function validateConfirmPassword() {
            const confirmMessage = document.getElementById('confirmMessage');
            if (confirmPassword.value === password.value && password.value !== '') {
                confirmMessage.textContent = 'Passwords match';
                confirmMessage.style.color = '#0f9d58';
                isConfirmPasswordValid = true;
            } else {
                confirmMessage.textContent = 'Passwords do not match';
                confirmMessage.style.color = '#d93025';
                isConfirmPasswordValid = false;
            }
            updateSubmitButton();
        }

        function updateSubmitButton() {
            submitBtn.disabled = !(isUsernameValid && isEmailValid && isPasswordValid && isConfirmPasswordValid);
        }
    </script>
</body>
</html>