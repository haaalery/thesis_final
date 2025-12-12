<?php
/**
 * Student Registration Page
 * Only students can self-register
 * FIXED: Proper student_details table insertion
 */

session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    header("Location: " . $role . "/dashboard.php");
    exit();
}

require_once 'db_connect.php';

// Initialize variables
$errors = [];
$success = '';
$form_data = [
    'name' => '',
    'email' => '',
    'course' => '',
    'year' => ''
];

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Course options
$courses = ['Computer Science', 'Information Technology', 'Computer Engineering', 'Software Engineering', 'Data Science'];
$years = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

// Process signup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        // Get and sanitize input
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $course = trim($_POST['course']);
        $year = trim($_POST['year']);

        // Store form data for repopulation
        $form_data = compact('name', 'email', 'course', 'year');

        // Validation
        if (empty($name) || strlen($name) < 3) $errors[] = "Name must be at least 3 characters.";
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email.";
        if (empty($password) || strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";
        if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

        // Password strength check
        if (!preg_match('/[A-Z]/', $password)) $errors[] = "Password must contain at least one uppercase letter.";
        if (!preg_match('/[a-z]/', $password)) $errors[] = "Password must contain at least one lowercase letter.";
        if (!preg_match('/[0-9]/', $password)) $errors[] = "Password must contain at least one number.";

        if (empty($course) || !in_array($course, $courses)) $errors[] = "Please select a valid course.";
        if (empty($year) || !in_array($year, $years)) $errors[] = "Please select a valid year.";

        // Check if email already exists
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

        // FIXED: Insert new user with proper student_details handling
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Hash password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // Insert into users table (WITHOUT course and year)
                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password_hash, role, status, created_at) 
                    VALUES (?, ?, ?, 'student', 'active', NOW())
                ");
                $stmt->execute([$name, $email, $password_hash]);
                
                $new_user_id = $pdo->lastInsertId();

                // Insert into student_details table (WITH course and year)
                $details_stmt = $pdo->prepare("
                    INSERT INTO student_details (user_id, course, year) 
                    VALUES (?, ?, ?)
                ");
                $details_stmt->execute([$new_user_id, $course, $year]);

                // Create welcome notification
                $notif_stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type, created_at) 
                    VALUES (?, 'Welcome to Thesis Panel System', 'Your account has been created successfully. You can now create your thesis group and submit your proposal.', 'general', NOW())
                ");
                $notif_stmt->execute([$new_user_id]);

                $pdo->commit();

                // Success message
                $success = "Account created successfully! Redirecting to login...";
                
                // Clear form data
                $form_data = ['name' => '', 'email' => '', 'course' => '', 'year' => ''];
                
                // Redirect after 2 seconds
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
    <title>Sign Up - Thesis Panel Scheduling System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/forms.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box auth-box-large">
            <div class="auth-header">
                <h2>📝 Student Registration</h2>
                <p>Create your account to get started</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="signup.php" class="auth-form" id="signupForm">
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
                    <div class="password-input-wrapper">
                        <input type="password" id="password" name="password" required minlength="8" placeholder="Minimum 8 characters">
                        <button type="button" class="toggle-password" onclick="togglePassword('password')">👁️</button>
                    </div>
                    <small class="form-help">Must contain uppercase, lowercase, and number</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <div class="password-input-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8" placeholder="Re-enter your password">
                        <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">👁️</button>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block" id="signupBtn">Create Account</button>
                </div>

                <div class="form-footer">
                    <p>Already have an account? <a href="login.php">Login here</a></p>
                    <p><a href="index.php">← Back to Home</a></p>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
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
            document.getElementById('signupBtn').textContent = 'Creating Account...';
        });

        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const type = passwordField.type === 'password' ? 'text' : 'password';
            passwordField.type = type;
        }

        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    this.style.borderColor = 'green';
                } else {
                    this.style.borderColor = 'red';
                }
            } else {
                this.style.borderColor = '';
            }
        });
    </script>
</body>
</html>