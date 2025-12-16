<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    header("Location: " . $role . "/dashboard.php");
    exit();
}
require_once 'db_connect.php';
require_once 'includes/notification_helper.php';

$errors = [];
$success = '';
$form_data = ['name' => '', 'email' => '', 'course' => '', 'year' => ''];

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$courses = ['Computer Science', 'Information Technology', 'Computer Engineering', 'Software Engineering', 'Data Science'];
$years = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $course = trim($_POST['course']);
        $year = trim($_POST['year']);
        $form_data = compact('name', 'email', 'course', 'year');

        if (empty($name) || strlen($name) < 3) $errors[] = "Name must be at least 3 characters.";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email.";
        if (empty($password) || strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";
        if ($password !== $confirm_password) $errors[] = "Passwords do not match.";
        if (!preg_match('/[A-Z]/', $password)) $errors[] = "Password must contain at least one uppercase letter.";
        if (!preg_match('/[a-z]/', $password)) $errors[] = "Password must contain at least one lowercase letter.";
        if (!preg_match('/[0-9]/', $password)) $errors[] = "Password must contain at least one number.";
        if (empty($course) || !in_array($course, $courses)) $errors[] = "Please select a valid course.";
        if (empty($year) || !in_array($year, $years)) $errors[] = "Please select a valid year.";

        if (empty($errors)) {
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = "This email address is already registered.";
                }
            } catch (PDOException $e) {
                error_log("Email Check Error: " . $e->getMessage());
                $errors[] = "An error occurred. Please try again.";
            }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();
                
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, status, created_at) VALUES (?, ?, ?, 'student', 'active', NOW())");
                $stmt->execute([$name, $email, $password_hash]);
                $new_user_id = $pdo->lastInsertId();

                $details_stmt = $pdo->prepare("INSERT INTO student_details (user_id, course, year) VALUES (?, ?, ?)");
                $details_stmt->execute([$new_user_id, $course, $year]);

                // FIXED: Use notification helper
                $notifier = new NotificationHelper($pdo);
                
                // Notify new user
                $notifier->notifyNewUser($new_user_id, 'student');
                
                // Notify all admins about new user
                $notifier->sendToAllAdmins(
                    'New Student Registration',
                    "New student registered: $name ($email) - $course, $year",
                    'general'
                );

                $pdo->commit();
                $success = "Account created successfully! You will be notified once approved. Redirecting to login...";
                $form_data = ['name' => '', 'email' => '', 'course' => '', 'year' => ''];
                header("Refresh: 2; url=login.php");
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Signup Error: " . $e->getMessage());
                $errors[] = "An error occurred during registration. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Thesis Panel System</title>
    <link rel="stylesheet" href="assets/css/icons.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #353535 0%, #D4AF37 50%, #353535 100%);
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 2rem; 
        }
        .signup-container { 
            width: 100%; 
            max-width: 650px; 
            background: #DC143C;
            border-radius: 30px; 
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3); 
            overflow: hidden; 
        }
        .signup-header { 
            background: #DC143C;
            padding: 2.5rem 2rem; 
            text-align: center; 
            color: #FFFFF0; 
        }
        .signup-header h2 { 
            font-size: 1.8rem; 
            margin-bottom: 0.5rem; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 0.5rem; 
        }
        .signup-header p { 
            opacity: 0.9; 
            font-size: 0.95rem; 
        }
        .signup-body { 
            padding: 2.5rem; 
            background: #DC143C;
        }
        .alert-error { 
            background: #fee2e2; 
            color: #991b1b; 
            padding: 1rem; 
            border-radius: 12px; 
            margin-bottom: 1.5rem; 
            border-left: 4px solid #DC143C; 
        }
        .alert-error ul { 
            margin-left: 1.2rem; 
            margin-top: 0.5rem; 
        }
        .alert-success { 
            background: #d1fae5; 
            color: #065f46; 
            padding: 1rem; 
            border-radius: 12px; 
            margin-bottom: 1.5rem; 
            border-left: 4px solid #10b981; 
        }
        .form-group { 
            margin-bottom: 1.3rem; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 0.4rem; 
            font-weight: 600; 
            color: #FFFFF0; 
            font-size: 0.9rem; 
        }
        .form-group input, .form-group select { 
            width: 100%; 
            padding: 0.8rem 1rem; 
            border: 2px solid #FFFFF0; 
            border-radius: 12px; 
            font-size: 0.95rem; 
            transition: all 0.3s; 
            background: #FFFFF0; 
            color: #353535; 
        }
        .form-group input:focus, .form-group select:focus { 
            outline: none; 
            border-color: #D4AF37; 
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.3); 
        }
        .form-row { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 1rem; 
        }
        .password-wrapper { 
            position: relative; 
        }
        .password-wrapper input { 
            padding-right: 3rem; 
        }
        .toggle-password { 
            position: absolute; 
            right: 1rem; 
            top: 50%; 
            transform: translateY(-50%); 
            background: none; 
            border: none; 
            cursor: pointer; 
            font-size: 1.1rem; 
            color: #353535; 
            transition: color 0.3s; 
        }
        .toggle-password:hover { 
            color: #DC143C; 
        }
        .btn-signup { 
            width: 100%; 
            padding: 1rem; 
            background: #353535; 
            color: #FFFFF0; 
            border: none; 
            border-radius: 12px; 
            font-size: 1rem; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s; 
            margin-top: 1rem; 
        }
        .btn-signup:hover { 
            background: #D4AF37; 
            transform: translateY(-2px); 
            box-shadow: 0 10px 25px rgba(212, 175, 55, 0.3); 
        }
        .btn-signup:disabled { 
            opacity: 0.6; 
            cursor: not-allowed; 
        }
        .form-footer { 
            text-align: center; 
            margin-top: 1.5rem; 
            color: #FFFFF0; 
            font-size: 0.9rem; 
        }
        .form-footer a { 
            color: #D4AF37; 
            font-weight: 600; 
            text-decoration: none; 
            transition: color 0.3s; 
        }
        .form-footer a:hover { 
            color: #FFFFF0; 
            text-decoration: underline; 
        }
        .required { 
            color: #D4AF37; 
        }
        .form-help { 
            font-size: 0.8rem; 
            color: #FFFFF0; 
            opacity: 0.8; 
            margin-top: 0.3rem; 
        }
        @media (max-width: 768px) {
            .form-row { 
                grid-template-columns: 1fr; 
            }
            .signup-container { 
                margin: 1rem; 
            }
            .signup-header { 
                padding: 2rem 1.5rem; 
            }
            .signup-body { 
                padding: 2rem 1.5rem; 
            }
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="signup-header">
            <h2>
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="8.5" cy="7" r="4"/>
                    <line x1="20" y1="8" x2="20" y2="14"/>
                    <line x1="23" y1="11" x2="17" y2="11"/>
                </svg>
                Student Registration
            </h2>
            <p>Create your account to get started</p>
        </div>

        <div class="signup-body">
            <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="signup.php" id="signupForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="name">Full Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($form_data['name']); ?>" required minlength="3" placeholder="Enter your full name">
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($form_data['email']); ?>" required placeholder="your.email@example.com">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="course">Course <span class="required">*</span></label>
                        <select id="course" name="course" required>
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?php echo $c; ?>" <?php echo $form_data['course'] === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="year">Year Level <span class="required">*</span></label>
                        <select id="year" name="year" required>
                            <option value="">Select Year</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?php echo $y; ?>" <?php echo $form_data['year'] === $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required minlength="8" placeholder="Minimum 8 characters">
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                    <small class="form-help">Must contain uppercase, lowercase, and number</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Re-enter your password">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-signup" id="signupBtn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="8.5" cy="7" r="4"/>
                        <line x1="20" y1="8" x2="20" y2="14"/>
                        <line x1="23" y1="11" x2="17" y2="11"/>
                    </svg>
                    Create Account
                </button>

                <div class="form-footer">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                    <p style="margin-top: 0.5rem;"><a href="index.php">← Back to Home</a></p>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('signupForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            document.getElementById('signupBtn').disabled = true;
            document.getElementById('signupBtn').innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px; animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>Creating Account...';
        });
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            passwordField.type = passwordField.type === 'password' ? 'text' : 'password';
        }
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    this.style.borderColor = '#10b981';
                } else {
                    this.style.borderColor = '#DC143C';
                }
            } else {
                this.style.borderColor = '#FFFFF0';
            }
        });
    </script>
    <script src="assets/js/icons.js"></script>
</body>
</html>