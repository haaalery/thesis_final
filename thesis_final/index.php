<?php
/**
 * Landing Page and Entry Point
 * Redirects logged-in users to appropriate dashboard
 */

session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    // Redirect based on role
    switch ($_SESSION['role']) {
        case 'admin':
            header("Location: admin/dashboard.php");
            exit();
        case 'panelist':
            header("Location: panelist/dashboard.php");
            exit();
        case 'student':
            header("Location: student/dashboard.php");
            exit();
        default:
            // Invalid role, destroy session
            session_destroy();
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thesis Panel Scheduling System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Enhanced Landing Page Specific Styles */
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 4rem 2rem;
            text-align: center;
            clip-path: polygon(0 0, 100% 0, 100% 85%, 0 100%);
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero-title {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .hero-subtitle {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }
        
        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .hero-buttons .btn {
            padding: 1rem 2.5rem;
            font-size: 1.1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .hero-buttons .btn-primary {
            background-color: white;
            color: #667eea;
        }
        
        .hero-buttons .btn-primary:hover {
            background-color: #f8fafc;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
        
        .hero-buttons .btn-secondary {
            background-color: transparent;
            color: white;
            border: 2px solid white;
        }
        
        .hero-buttons .btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .features-section {
            padding: 4rem 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .section-header h2 {
            font-size: 2.5rem;
            color: var(--text-primary);
            margin-bottom: 1rem;
        }
        
        .section-header p {
            font-size: 1.2rem;
            color: var(--text-secondary);
            max-width: 700px;
            margin: 0 auto;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }
        
        .feature-card {
            background: white;
            padding: 2.5rem 2rem;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15);
            border-color: var(--primary-color);
        }
        
        .feature-icon {
            font-size: 3.5rem;
            display: block;
            margin-bottom: 1.5rem;
            filter: drop-shadow(2px 4px 6px rgba(0, 0, 0, 0.1));
        }
        
        .feature-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--text-primary);
        }
        
        .feature-card p {
            color: var(--text-secondary);
            font-size: 1rem;
            line-height: 1.7;
        }
        
        .how-it-works {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            padding: 4rem 2rem;
        }
        
        .steps-container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .step-item {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 3rem;
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .step-number {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .step-content h3 {
            font-size: 1.4rem;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .step-content p {
            color: var(--text-secondary);
            font-size: 1rem;
        }
        
        .cta-section {
            text-align: center;
            padding: 4rem 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .cta-section h2 {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .cta-section p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }
        
        .landing-footer {
            background: #1e293b;
            color: rgba(255, 255, 255, 0.8);
            text-align: center;
            padding: 2rem;
        }
        
        .landing-footer p {
            margin: 0;
        }
        
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }
            
            .hero-subtitle {
                font-size: 1.1rem;
            }
            
            .section-header h2 {
                font-size: 1.8rem;
            }
            
            .step-item {
                flex-direction: column;
                text-align: center;
            }
            
            .hero-buttons {
                flex-direction: column;
            }
            
            .hero-buttons .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-content">
                <h1 class="hero-title">🎓 Thesis Panel Scheduling System</h1>
                <p class="hero-subtitle">Streamline your thesis defense scheduling, panel assignments, and evaluation process all in one place</p>
                <div class="hero-buttons">
                    <a href="login.php" class="btn btn-primary">Get Started</a>
                    <a href="#features" class="btn btn-secondary">Learn More</a>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features-section" id="features">
            <div class="section-header">
                <h2>Everything You Need</h2>
                <p>A comprehensive platform designed for students, panelists, and administrators</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <span class="feature-icon">👨‍🎓</span>
                    <h3>For Students</h3>
                    <p>Submit thesis proposals, request defense schedules, track your progress, and receive panel feedback all in one convenient dashboard.</p>
                </div>
                
                <div class="feature-card">
                    <span class="feature-icon">👨‍🏫</span>
                    <h3>For Panelists</h3>
                    <p>Review assigned defenses, submit comprehensive evaluations, manage your schedule, and provide valuable feedback to students.</p>
                </div>
                
                <div class="feature-card">
                    <span class="feature-icon">🛠️</span>
                    <h3>For Administrators</h3>
                    <p>Manage users efficiently, create schedules, assign panelists intelligently, and generate detailed reports with ease.</p>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section class="how-it-works">
            <div class="steps-container">
                <div class="section-header">
                    <h2>How It Works</h2>
                    <p>Getting started is simple and straightforward</p>
                </div>
                
                <div class="step-item">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Create Your Account</h3>
                        <p>Students can sign up instantly. Panelists and admins are added by administrators to maintain security.</p>
                    </div>
                </div>
                
                <div class="step-item">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Submit Your Thesis</h3>
                        <p>Students create groups, submit thesis proposals, and upload supporting documents for review.</p>
                    </div>
                </div>
                
                <div class="step-item">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Schedule Defense</h3>
                        <p>Administrators review proposals, create schedules, and assign qualified panelists based on specialization.</p>
                    </div>
                </div>
                
                <div class="step-item">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3>Get Evaluated</h3>
                        <p>Panelists conduct defenses, provide detailed feedback, and submit evaluations through the platform.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="cta-section">
            <h2>Ready to Get Started?</h2>
            <p>Join thousands of students and faculty using our platform</p>
            <div class="hero-buttons">
                <a href="signup.php" class="btn btn-primary">Sign Up as Student</a>
                <a href="login.php" class="btn btn-secondary">Login to Your Account</a>
            </div>
        </section>

        <!-- Footer -->
        <footer class="landing-footer">
            <p>&copy; <?php echo date('Y'); ?> College Thesis Panel Scheduling System. All rights reserved.</p>
        </footer>
    </div>

    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>