<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Our Learning Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            background: #f4f4f4;
        }

        .navbar {
            background: #fff;
            padding: 1rem 5%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #1a73e8;
            text-decoration: none;
        }

        .nav-buttons {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.5rem 1.5rem;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-login {
            background: #1a73e8;
            color: white;
        }

        .btn-register {
            border: 2px solid #1a73e8;
            color: #1a73e8;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .hero {
            background: linear-gradient(135deg, #1a73e8, #0d47a1);
            color: white;
            padding: 8rem 5% 4rem;
            text-align: center;
        }

        .hero h1 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        .features {
            padding: 4rem 5%;
            background: white;
        }

        .features h2 {
            text-align: center;
            margin-bottom: 3rem;
            color: #333;
        }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature-card {
            padding: 2rem;
            border-radius: 8px;
            background: #f8f9fa;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
        }

        .feature-card i {
            font-size: 2.5rem;
            color: #1a73e8;
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            color: #333;
            margin-bottom: 1rem;
        }

        .feature-card p {
            color: #666;
        }

        .about {
            padding: 4rem 5%;
            background: #f8f9fa;
        }

        .about-content {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }

        .about h2 {
            color: #333;
            margin-bottom: 2rem;
        }

        .about p {
            color: #666;
            margin-bottom: 1rem;
        }

        footer {
            background: #333;
            color: white;
            text-align: center;
            padding: 2rem;
        }

        footer p {
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2rem;
            }

            .feature-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="home.php" class="logo">LMS</a>
        <div class="nav-buttons">
            <a href="index.php" class="btn btn-login">Login</a>
            <a href="register.php" class="btn btn-register">Register</a>
        </div>
    </nav>

    <section class="hero">
        <h1>Welcome to Our Learning Management System</h1>
        <p>Empower your learning journey with our comprehensive educational platform</p>
    </section>

    <section class="features">
        <h2>Why Choose Our LMS?</h2>
        <div class="feature-grid">
            <div class="feature-card">
                <i class="fas fa-graduation-cap"></i>
                <h3>Quality Education</h3>
                <p>Access high-quality courses and learning materials designed by expert educators.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-clock"></i>
                <h3>Learn at Your Pace</h3>
                <p>Flexible learning schedule that adapts to your lifestyle and commitments.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-users"></i>
                <h3>Interactive Learning</h3>
                <p>Engage with teachers and fellow students in a collaborative learning environment.</p>
            </div>
            <div class="feature-card">
                <i class="fas fa-chart-line"></i>
                <h3>Track Progress</h3>
                <p>Monitor your learning progress with detailed analytics and assessments.</p>
            </div>
        </div>
    </section>

    <section class="about">
        <div class="about-content">
            <h2>About Our Platform</h2>
            <p>Our Learning Management System is designed to provide a seamless educational experience for both students and teachers. We offer a comprehensive suite of tools for course management, assignment submission, and progress tracking.</p>
            <p>Whether you're a student looking to expand your knowledge or a teacher wanting to create engaging learning experiences, our platform has everything you need to succeed.</p>
        </div>
    </section>

    <footer>
        <p>&copy; 2024 Learning Management System. All rights reserved.</p>
    </footer>
</body>
</html> 