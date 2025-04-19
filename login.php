<?php
session_start();
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION["user"] = ["email" => $user["email"], "role" => $user["role"]];
        echo json_encode(["status" => "success", "role" => $user["role"]]);
    } else {
        echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
    }
}
?>
<html>
<script>
document.getElementById("show-register").addEventListener("click", function (e) {
    e.preventDefault();
    document.getElementById("login-page").classList.add("hidden");
    document.getElementById("register-page").classList.remove("hidden");
});

document.getElementById("show-login").addEventListener("click", function (e) {
    e.preventDefault();
    document.getElementById("register-page").classList.add("hidden");
    document.getElementById("login-page").classList.remove("hidden");
});

document.getElementById("loginForm").addEventListener("submit", function (e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch("login.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === "success") {
            alert(`Welcome, ${data.role}!`);
            document.getElementById("login-page").classList.add("hidden");
            
            if (data.role === "admin") {
                document.getElementById("admin-dashboard").classList.remove("hidden");
            } else if (data.role === "teacher") {
                document.getElementById("teacher-dashboard").classList.remove("hidden");
            } else if (data.role === "student") {
                document.getElementById("student-dashboard").classList.remove("hidden");
            }
        } else {
            alert(data.message);
        }
    });
});

document.getElementById("registerForm").addEventListener("submit", function (e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch("register.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        alert(data.message);
        if (data.status === "success") {
            document.getElementById("registerForm").reset();
            document.getElementById("register-page").classList.add("hidden");
            document.getElementById("login-page").classList.remove("hidden");
        }
    });
});
</script>

<div class="container" id="login-page">
    <h1 class="title">Learning Management System</h1>
    
    <form id="loginForm" class="form">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" placeholder="Enter your email" required>

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" placeholder="Enter your password" required>

        <button type="submit" class="btn">Login</button>
    </form>

    <p>Don't have an account? <a href="#" id="show-register">Register here</a></p>
</div>
</html>