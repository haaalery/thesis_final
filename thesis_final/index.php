<?php
session_start();
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin': header("Location: admin/dashboard.php"); exit();
        case 'panelist': header("Location: panelist/dashboard.php"); exit();
        case 'student': header("Location: student/dashboard.php"); exit();
        default: session_destroy(); break;
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
    <link rel="stylesheet" href="assets/css/icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #FFFFF0; overflow-x: hidden; }
        
        /* Header */
        .modern-header { background: linear-gradient(135deg, #4e596a 0%, #b01030 100%); padding: 1rem 5%; display: flex; justify-content: space-between; align-items: center; position: fixed; width: 100%; top: 0; z-index: 1000; box-shadow: 0 4px 20px rgba(220, 20, 60, 0.2); }
        .logo { display: flex; align-items: center; gap: 0.8rem; color: #FFFFF0; font-size: 1.5rem; font-weight: 700; text-decoration: none; }
        .logo-icon { width: 40px; height: 40px; background: #D4AF37; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .nav-menu { display: flex; gap: 2rem; align-items: center; }
        .nav-link { color: #FFFFF0; text-decoration: none; font-weight: 500; transition: color 0.3s; }
        .nav-link:hover { color: #D4AF37; }
        .btn-signup { background: #D4AF37; color: #353535; padding: 0.6rem 1.5rem; border-radius: 25px; font-weight: 600; border: none; cursor: pointer; transition: all 0.3s; text-decoration: none; }
        .btn-signup:hover { background: #FFFFF0; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3); }
        
        /* Hero Section */
        .hero-section { margin-top: 80px; padding: 5rem 5% 3rem; display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; align-items: center; min-height: calc(100vh - 80px); background: linear-gradient(135deg, #FFFFF0 0%, #BBAFAF 100%); }
        .hero-content h1 { font-size: 3rem; color: #353535; margin-bottom: 1rem; line-height: 1.2; }
        .hero-content h1 span { color: #DC143C; }
        .hero-content p { font-size: 1.2rem; color: #353535; margin-bottom: 2rem; line-height: 1.6; opacity: 0.8; }
        .cta-buttons { display: flex; gap: 1rem; }
        .btn-primary { background: #DC143C; color: #FFFFF0; padding: 1rem 2rem; border-radius: 30px; font-weight: 600; border: none; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-primary:hover { background: #353535; transform: translateY(-3px); box-shadow: 0 8px 20px rgba(220, 20, 60, 0.3); }
        .btn-secondary { background: transparent; color: #DC143C; padding: 1rem 2rem; border-radius: 30px; font-weight: 600; border: 2px solid #DC143C; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn-secondary:hover { background: #DC143C; color: #FFFFF0; }
        
        /* Hero Image */
        .hero-image { position: relative; height: 500px; background: linear-gradient(135deg, rgba(220, 20, 60, 0.1), rgba(212, 175, 55, 0.1)); border-radius: 30px; display: flex; align-items: center; justify-content: center; overflow: hidden; box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15); }
        .hero-image::before { content: ''; position: absolute; width: 400px; height: 400px; background: radial-gradient(circle, rgba(212, 175, 55, 0.3), transparent); border-radius: 50%; animation: pulse 3s infinite; }
        @keyframes pulse { 0%, 100% { transform: scale(1); opacity: 0.5; } 50% { transform: scale(1.1); opacity: 0.8; } }
        .image-placeholder { font-size: 8rem; color: #D4AF37; opacity: 0.3; }
        .hero-image img { max-width: 100%; max-height: 100%; object-fit: contain; position: relative; z-index: 1; }
        
        /* Features Section */
        .features-section { padding: 5rem 5%; background: #FFFFF0; }
        .section-title { text-align: center; font-size: 2.5rem; color: #353535; margin-bottom: 3rem; }
        .section-title span { color: #DC143C; }
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2.5rem; }
        .feature-card { background: white; padding: 0; border-radius: 20px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08); transition: all 0.3s; border: 2px solid transparent; overflow: hidden; display: flex; flex-direction: column; }
        .feature-card:hover { transform: translateY(-10px); box-shadow: 0 20px 50px rgba(220, 20, 60, 0.15); border-color: #D4AF37; }
        
        /* Feature Image Container */
        .feature-image-container { width: 100%; height: 250px; overflow: hidden; background: linear-gradient(135deg, #f5f5f5 0%, #e0e0e0 100%); display: flex; align-items: center; justify-content: center; position: relative; }
        .feature-image-container img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s ease; }
        .feature-card:hover .feature-image-container img { transform: scale(1.05); }
        
        /* Fallback icon if no image */
        .feature-image-container .fallback-icon { font-size: 6rem; color: #D4AF37; opacity: 0.3; }
        
        /* Feature Content */
        .feature-content { padding: 2rem; }
        .feature-card h3 { font-size: 1.5rem; color: #353535; margin-bottom: 1rem; }
        .feature-card p { color: #353535; opacity: 0.7; line-height: 1.6; }
        
        /* Enhanced Footer */
        .modern-footer { background: linear-gradient(135deg, #353535 0%, #1a1a1a 100%); color: #FFFFF0; padding: 3rem 5%; }
        .footer-content { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 2rem; margin-bottom: 2rem; }
        .footer-section h4 { color: #D4AF37; margin-bottom: 1rem; font-size: 1.2rem; }
        .footer-section ul { list-style: none; }
        .footer-section ul li { margin-bottom: 0.5rem; }
        .footer-section ul li a { color: #FFFFF0; text-decoration: none; opacity: 0.8; transition: all 0.3s; }
        .footer-section ul li a:hover { opacity: 1; color: #D4AF37; padding-left: 5px; }
        .footer-bottom { border-top: 1px solid rgba(255, 255, 240, 0.2); padding-top: 2rem; text-align: center; display: flex; flex-direction: column; gap: 1rem; }
        .footer-bottom p { opacity: 0.8; }
        .social-links { display: flex; gap: 1rem; justify-content: center; }
        .social-links a { color: #FFFFF0; font-size: 1.5rem; transition: all 0.3s; }
        .social-links a:hover { color: #D4AF37; transform: translateY(-3px); }
        
        /* Responsive */
        @media (max-width: 968px) {
            .hero-section { grid-template-columns: 1fr; text-align: center; }
            .hero-content h1 { font-size: 2.5rem; }
            .cta-buttons { justify-content: center; flex-wrap: wrap; }
            .nav-menu { display: none; }
            .features-grid { grid-template-columns: 1fr; }
            .footer-content { grid-template-columns: 1fr; text-align: center; }
        }
        @media (max-width: 768px) {
            .hero-content h1 { font-size: 2rem; }
            .hero-image { height: 350px; }
            .feature-image-container { height: 200px; }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="modern-header">
        <a href="index.php" class="logo">
            <span>PanDeFend</span>
        </a>
        <nav class="nav-menu">
            <a href="#features" class="nav-link">Features</a>
            <a href="login.php" class="nav-link">Login</a>
            <a href="signup.php" class="btn-signup">Sign Up</a>
        </nav>
    </header>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1><span>Finding a time</span> shouldn't be harder than the research.</h1>
            <p>"Your research is hard enough; your schedule shouldn't be. PanDeFend simplifies panel coordination—because finding a time shouldn't be harder than the research."</p>
            <div class="cta-buttons">
                <a href="signup.php" class="btn-primary">Get Started</a>
                <a href="#features" class="btn-secondary">Learn More</a>
            </div>
        </div>
        <div class="hero-image">
            <div class="image-placeholder">📊</div>
            <img src="hand-drawn.png" alt="Hero Image"> 
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="features">
        <h2 class="section-title">Compre<span>hensive</span></h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-image-container">
                    <!-- Replace 'student-feature.png' with your actual image path -->
                    <img src="students.jpg" alt="For Students" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="fallback-icon" style="display:none;">👨‍🎓</div>
                </div>
                <div class="feature-content">
                    <h3>For Students</h3>
                    <p>Submit thesis proposals, request defense schedules, track your progress, and receive panel feedback all in one convenient dashboard.</p>
                </div>
            </div>
            
            <div class="feature-card">
                <div class="feature-image-container">
                    <!-- Replace 'panelist-feature.png' with your actual image path -->
                    <img src="panelists.jpg" alt="For Panelists" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="fallback-icon" style="display:none;">👨‍🏫</div>
                </div>
                <div class="feature-content">
                    <h3>For Panelists</h3>
                    <p>Review assigned defenses, submit comprehensive evaluations, manage your schedule, and provide valuable feedback to students.</p>
                </div>
            </div>
            
            <div class="feature-card">
                <div class="feature-image-container">
                    <!-- Replace 'admin-feature.png' with your actual image path -->
                    <img src="admins.avif" alt="For Administrators" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="fallback-icon" style="display:none;">🛠️</div>
                </div>
                <div class="feature-content">
                    <h3>For Administrators</h3>
                    <p>Manage users efficiently, create schedules, assign panelists intelligently, and generate detailed reports with ease.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Enhanced Footer -->
    <footer class="modern-footer">
        <div class="footer-content">
            <div class="footer-section">
                <h4>PanDeFend</h4>
                <p>"Your research is hard enough; your schedule shouldn't be. PanDeFend simplifies panel coordination—because finding a time shouldn't be harder than the research."</p>
            </div>
            
            <div class="footer-section">
                <h4>Quick Links</h4>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="#features">Features</a></li>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="signup.php">Sign Up</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h4>Resources</h4>
                <ul>
                    <li><a href="#">User Guide</a></li>
                    <li><a href="#">FAQ</a></li>
                    <li><a href="#">Support</a></li>
                    <li><a href="#">Contact Us</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h4>Legal</h4>
                <ul>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Cookie Policy</a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="social-links">
            </div>
            <p>&copy; <?php echo date('Y'); ?> PanDeFend - Thesis Panel Scheduling System. All rights reserved.</p>
        </div>
    </footer>

    <script src="assets/js/icons.js"></script>
</body>
</html>