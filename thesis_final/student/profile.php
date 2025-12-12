<?php
/**
 * Student Profile Page
 * Allows students to view and edit their personal information
 */

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

// Page title and subtitle
$page_title = "My Profile";
$page_subtitle = "Manage your personal information";

// Initialize variables
$errors = [];
$success = '';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get student details
try {
    $user_stmt = $pdo->prepare("
        SELECT u.*, sd.course, sd.year, sd.student_id 
        FROM users u 
        LEFT JOIN student_details sd ON u.user_id = sd.user_id 
        WHERE u.user_id = ?
    ");
    $user_stmt->execute([$_SESSION['user_id']]);
    $student = $user_stmt->fetch();

    // Create student_details record if not exists
    if (!$student['course']) {
        $create_stmt = $pdo->prepare("
            INSERT INTO student_details (user_id, course, year, student_id) 
            VALUES (?, 'Not Set', 'Not Set', NULL)
        ");
        $create_stmt->execute([$_SESSION['user_id']]);
        
        // Re-fetch data
        $user_stmt->execute([$_SESSION['user_id']]);
        $student = $user_stmt->fetch();
    }
} catch (PDOException $e) {
    error_log("Profile Load Error: " . $e->getMessage());
    die("Error loading profile information.");
}

// Course and year options
$courses = ['Computer Science', 'Information Technology', 'Computer Engineering', 'Software Engineering', 'Data Science'];
$years = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $course = trim($_POST['course']);
        $year = trim($_POST['year']);
        $student_id = trim($_POST['student_id']);
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validation
        if (empty($name) || strlen($name) < 3) {
            $errors[] = "Name must be at least 3 characters long.";
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        }

        if (empty($course) || !in_array($course, $courses)) {
            $errors[] = "Please select a valid course.";
        }

        if (empty($year) || !in_array($year, $years)) {
            $errors[] = "Please select a valid year.";
        }

        // Check if email is already taken by another user
        if (empty($errors)) {
            try {
                $email_check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND user_id != ?");
                $email_check->execute([$email, $_SESSION['user_id']]);
                if ($email_check->fetchColumn() > 0) {
                    $errors[] = "This email address is already in use by another account.";
                }
            } catch (PDOException $e) {
                error_log("Email Check Error: " . $e->getMessage());
                $errors[] = "An error occurred. Please try again.";
            }
        }

        // Password change validation (if provided)
        if (!empty($new_password)) {
            if (empty($current_password)) {
                $errors[] = "Please enter your current password to change it.";
            } elseif (!password_verify($current_password, $student['password_hash'])) {
                $errors[] = "Current password is incorrect.";
            } elseif (strlen($new_password) < 8) {
                $errors[] = "New password must be at least 8 characters long.";
            } elseif ($new_password !== $confirm_password) {
                $errors[] = "New passwords do not match.";
            } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
                $errors[] = "New password must contain uppercase, lowercase, and numbers.";
            }
        }

        // Update profile if no errors
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Update users table
                if (!empty($new_password)) {
                    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_user = $pdo->prepare("
                        UPDATE users 
                        SET name = ?, email = ?, password_hash = ?, updated_at = NOW() 
                        WHERE user_id = ?
                    ");
                    $update_user->execute([$name, $email, $password_hash, $_SESSION['user_id']]);
                } else {
                    $update_user = $pdo->prepare("
                        UPDATE users 
                        SET name = ?, email = ?, updated_at = NOW() 
                        WHERE user_id = ?
                    ");
                    $update_user->execute([$name, $email, $_SESSION['user_id']]);
                }

                // Update student_details table
                $update_details = $pdo->prepare("
                    UPDATE student_details 
                    SET course = ?, year = ?, student_id = ? 
                    WHERE user_id = ?
                ");
                $update_details->execute([$course, $year, $student_id ?: NULL, $_SESSION['user_id']]);

                // Log activity
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, created_at) 
                    VALUES (?, 'update_profile', 'users', ?, ?, NOW())
                ");
                $log_stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);

                $pdo->commit();

                // Update session name
                $_SESSION['name'] = $name;

                $success = "Profile updated successfully!";
                
                // Refresh student data
                $user_stmt->execute([$_SESSION['user_id']]);
                $student = $user_stmt->fetch();

            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Profile Update Error: " . $e->getMessage());
                $errors[] = "An error occurred while updating your profile. Please try again.";
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
    <title>My Profile - Thesis Panel System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/topbar.php'; ?>

            <div class="dashboard-content">
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

                <!-- Profile Information Form -->
                <div class="form-card">
                    <div class="form-card-header">
                        <h3>👤 Personal Information</h3>
                        <p>Update your account details and contact information</p>
                    </div>
                    
                    <form method="POST" action="profile.php" id="profileForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="form-group">
                            <label for="name">Full Name <span class="required">*</span></label>
                            <input 
                                type="text" 
                                id="name" 
                                name="name" 
                                value="<?php echo htmlspecialchars($student['name']); ?>"
                                required 
                                minlength="3"
                            >
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address <span class="required">*</span></label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                value="<?php echo htmlspecialchars($student['email']); ?>"
                                required
                            >
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="student_id">Student ID</label>
                                <input 
                                    type="text" 
                                    id="student_id" 
                                    name="student_id" 
                                    value="<?php echo htmlspecialchars($student['student_id'] ?? ''); ?>"
                                    placeholder="e.g., 2021-00001"
                                >
                                <small class="form-help">Optional - Your official student ID number</small>
                            </div>

                            <div class="form-group">
                                <label for="course">Course <span class="required">*</span></label>
                                <select id="course" name="course" required>
                                    <?php foreach ($courses as $c): ?>
                                        <option value="<?php echo $c; ?>" <?php echo $student['course'] === $c ? 'selected' : ''; ?>>
                                            <?php echo $c; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="year">Year Level <span class="required">*</span></label>
                            <select id="year" name="year" required>
                                <?php foreach ($years as $y): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $student['year'] === $y ? 'selected' : ''; ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <hr style="margin: 2rem 0; border: none; border-top: 2px solid var(--border-color);">

                        <div class="form-card-header" style="margin-top: 2rem;">
                            <h3>🔒 Change Password</h3>
                            <p>Leave blank if you don't want to change your password</p>
                        </div>

                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <div class="password-input-wrapper">
                                <input 
                                    type="password" 
                                    id="current_password" 
                                    name="current_password"
                                    placeholder="Enter current password"
                                >
                                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('current_password')">
                                    👁️
                                </button>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <div class="password-input-wrapper">
                                    <input 
                                        type="password" 
                                        id="new_password" 
                                        name="new_password"
                                        placeholder="Minimum 8 characters"
                                        minlength="8"
                                    >
                                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility('new_password')">
                                        👁️
                                    </button>
                                </div>
                                <small class="form-help">Must contain uppercase, lowercase, and number</small>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <div class="password-input-wrapper">
                                    <input 
                                        type="password" 
                                        id="confirm_password" 
                                        name="confirm_password"
                                        placeholder="Re-enter new password"
                                        minlength="8"
                                    >
                                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password')">
                                        👁️
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary" id="updateBtn">
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Account Information -->
                <div class="content-card" style="margin-top: 1.5rem;">
                    <div class="card-header">
                        <h3>📋 Account Information</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                            <div>
                                <strong>Account Status:</strong>
                                <p>
                                    <span class="badge badge-<?php echo $student['status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </p>
                            </div>
                            <div>
                                <strong>Role:</strong>
                                <p><?php echo ucfirst($student['role']); ?></p>
                            </div>
                            <div>
                                <strong>Account Created:</strong>
                                <p><?php echo date('F d, Y', strtotime($student['created_at'])); ?></p>
                            </div>
                            <div>
                                <strong>Last Updated:</strong>
                                <p><?php echo $student['updated_at'] ? date('F d, Y', strtotime($student['updated_at'])) : 'Never'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Password match validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0) {
                if (newPassword === confirmPassword) {
                    this.style.borderColor = 'var(--success-color)';
                } else {
                    this.style.borderColor = 'var(--error-color)';
                }
            } else {
                this.style.borderColor = '';
            }
        });

        // Form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const currentPassword = document.getElementById('current_password').value;

            // If attempting to change password
            if (newPassword || confirmPassword || currentPassword) {
                if (!currentPassword) {
                    e.preventDefault();
                    alert('Please enter your current password to change it.');
                    return false;
                }
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('New passwords do not match!');
                    return false;
                }
            }

            // Disable submit button
            document.getElementById('updateBtn').disabled = true;
            document.getElementById('updateBtn').textContent = 'Updating...';
        });
    </script>
</body>
</html>