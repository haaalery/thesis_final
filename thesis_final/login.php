<?php
session_start();
if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'];
    header("Location: " . $role . "/dashboard.php");
    exit();
}
require_once 'db_connect.php';
$error = '';
$email = '';
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid request. Please try again.";
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        if (empty($email) || empty($password)) {
            $error = "Please enter both email and password.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT user_id, password_hash, role, name, status FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                if ($user && password_verify($password, $user['password_hash'])) {
                    if ($user['status'] === 'inactive') {
                        $error = "Your account has been deactivated. Please contact the administrator.";
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['name'] = $user['name'];
                        $_SESSION['login_time'] = time();
                        $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address, created_at) VALUES (?, 'login', ?, NOW())");
                        $log_stmt->execute([$user['user_id'], $_SERVER['REMOTE_ADDR']]);
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
    <title>Login - Thesis Panel System</title>
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
        .login-container { 
            width: 100%; 
            max-width: 450px; 
            background: #DC143C;
            border-radius: 30px; 
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3); 
            overflow: hidden; 
        }
        .login-header { 
            background: #DC143C;
            padding: 3rem 2rem; 
            text-align: center; 
            color: #FFFFF0; 
        }
        .login-header h2 { 
            font-size: 2rem; 
            margin-bottom: 0.5rem; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 0.5rem; 
        }
        .login-header p { 
            opacity: 0.9; 
            font-size: 1rem; 
        }
        .login-body { 
            padding: 2.5rem; 
            background: #DC143C;
        }
        .alert-error { 
            background: #fee2e2; 
            color: #991b1b; 
            padding: 1rem; 
            border-radius: 12px; 
            margin-bottom: 1.5rem; 
            border-left: 4px solid #353535; 
            font-size: 0.95rem; 
        }
        .form-group { 
            margin-bottom: 1.5rem; 
        }
        .form-group label { 
            display: block; 
            margin-bottom: 0.5rem; 
            font-weight: 600; 
            color: #FFFFF0; 
            font-size: 0.95rem; 
        }
        .form-group input { 
            width: 100%; 
            padding: 0.9rem 1rem; 
            border: 2px solid #FFFFF0; 
            border-radius: 12px; 
            font-size: 1rem; 
            transition: all 0.3s; 
            background: #FFFFF0; 
            color: #353535; 
        }
        .form-group input:focus { 
            outline: none; 
            border-color: #D4AF37; 
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.3); 
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
            font-size: 1.2rem; 
            color: #353535; 
            transition: color 0.3s; 
        }
        .toggle-password:hover { 
            color: #DC143C; 
        }
        .btn-login { 
            width: 100%; 
            padding: 1rem; 
            background: #353535; 
            color: #FFFFF0; 
            border: none; 
            border-radius: 12px; 
            font-size: 1.1rem; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s; 
            margin-top: 1rem; 
        }
        .btn-login:hover { 
            background: #D4AF37; 
            transform: translateY(-2px); 
            box-shadow: 0 10px 25px rgba(212, 175, 55, 0.3); 
        }
        .btn-login:disabled { 
            opacity: 0.6; 
            cursor: not-allowed; 
        }
        .form-footer { 
            text-align: center; 
            margin-top: 1.5rem; 
            color: #FFFFF0; 
            font-size: 0.95rem; 
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
        @media (max-width: 480px) {
            .login-container { 
                margin: 1rem; 
            }
            .login-header { 
                padding: 2rem 1.5rem; 
            }
            .login-body { 
                padding: 2rem 1.5rem; 
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h2>
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M13.8 12H3"/>
                </svg>
                Welcome Back
            </h2>
            <p>Enter your credentials to access the system</p>
        </div>

        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="email">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                            <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                            <polyline points="22,6 12,13 2,6"/>
                        </svg>
                        Email Address
                    </label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required placeholder="Enter your email">
                </div>

                <div class="form-group">
                    <label for="password">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 4px;">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        Password
                    </label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required placeholder="Enter your password">
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px;">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M13.8 12H3"/>
                    </svg>
                    Login to Dashboard
                </button>

                <div class="form-footer">
                    <p>Don't have an account? <a href="signup.php">Sign up as Student</a></p>
                    <p style="margin-top: 0.5rem;"><a href="index.php">← Back to Home</a></p>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields.');
                return false;
            }
            document.getElementById('loginBtn').disabled = true;
            document.getElementById('loginBtn').innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: middle; margin-right: 8px; animation: spin 1s linear infinite;"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>Logging in...';
        });
        function togglePassword() {
            const passwordField = document.getElementById('password');
            passwordField.type = passwordField.type === 'password' ? 'text' : 'password';
        }
    </script>
    <script src="assets/js/icons.js"></script>
</body>
</html>