<?php
/**
 * User Authentication Page
 * Handles login and session establishment
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
$error = '';
$email = '';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request. Please try again.";
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        // Validate input
        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            try {
                // Prepare and execute query
                $stmt = $pdo->prepare("
                    SELECT user_id, password_hash, role, name, status 
                    FROM users 
                    WHERE email = ? 
                    LIMIT 1
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                // Verify user exists and password is correct
                if ($user && password_verify($password, $user['password_hash'])) {
                    // Check if account is active
                    if ($user['status'] === 'inactive') {
                        $error = "Your account has been deactivated. Please contact the administrator.";
                    } else {
                        // Regenerate session ID to prevent fixation
                        session_regenerate_id(true);

                        // Set session variables
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['name'] = $user['name'];
                        $_SESSION['login_time'] = time();

                        // Log login activity
                        $log_stmt = $pdo->prepare("
                            INSERT INTO activity_logs (user_id, action, ip_address, created_at) 
                            VALUES (?, 'login', ?, NOW())
                        ");
                        $log_stmt->execute([$user['user_id'], $_SERVER['REMOTE_ADDR']]);

                        // Redirect based on role
                        header("Location: " . $user['role'] . "/dashboard.php");
                        exit();
                    }
                } else {
                    $error = "Invalid email or password.";
                }
            } catch (PDOException $e) {
                error_log("Login Error: " . $e->getMessage());
                $error = "An error occurred. Please try again later.";
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
    <title>Login - Thesis Panel Scheduling System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/forms.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="auth-header">
                <h2>🔐 Login</h2>
                <p>Enter your credentials to access the system</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php" class="auth-form" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?php echo htmlspecialchars($email); ?>"
                        required 
                        autocomplete="email"
                        placeholder="Enter your email"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            autocomplete="current-password"
                            placeholder="Enter your password"
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            👁️
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <button type="submit" class="btn btn-primary btn-block" id="loginBtn">
                        Login
                    </button>
                </div>

                <div class="form-footer">
                    <p>Don't have an account? <a href="signup.php">Sign up as Student</a></p>
                    <p><a href="index.php">← Back to Home</a></p>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
    <script>
        // Client-side validation
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;

            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields.');
                return false;
            }

            // Disable submit button to prevent double submission
            document.getElementById('loginBtn').disabled = true;
            document.getElementById('loginBtn').textContent = 'Logging in...';
        });

        function togglePassword() {
            const passwordField = document.getElementById('password');
            const type = passwordField.type === 'password' ? 'text' : 'password';
            passwordField.type = type;
        }
    </script>
</body>
</html>