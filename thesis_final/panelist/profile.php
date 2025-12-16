<?php
/**
 * Panelist Profile Page - FIXED UI with Modern Icons
 * File: thesis_final/panelist/profile.php
 */

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'panelist') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

$page_title = "My Profile";
$page_subtitle = "Manage your personal information and specialization";

$errors = [];
$success = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get panelist details
try {
    $user_stmt = $pdo->prepare("
        SELECT u.*, pd.specialization, pd.title, pd.bio
        FROM users u 
        LEFT JOIN panelist_details pd ON u.user_id = pd.user_id 
        WHERE u.user_id = ?
    ");
    $user_stmt->execute([$_SESSION['user_id']]);
    $panelist = $user_stmt->fetch();

    if (!$panelist['specialization']) {
        $create_stmt = $pdo->prepare("
            INSERT INTO panelist_details (user_id, specialization, title, bio) 
            VALUES (?, 'Not Set', NULL, NULL)
        ");
        $create_stmt->execute([$_SESSION['user_id']]);
        
        $user_stmt->execute([$_SESSION['user_id']]);
        $panelist = $user_stmt->fetch();
    }
} catch (PDOException $e) {
    error_log("Profile Load Error: " . $e->getMessage());
    die("Error loading profile information.");
}

$specializations = [
    'Software Engineering', 'Data Science', 'Artificial Intelligence', 'Machine Learning',
    'Web Development', 'Mobile Development', 'Cybersecurity', 'Game Development',
    'Database Management', 'Network Administration', 'Cloud Computing', 'IoT',
    'Computer Vision', 'Natural Language Processing', 'Blockchain', 'DevOps'
];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $specialization = trim($_POST['specialization']);
        $title = trim($_POST['title']);
        $bio = trim($_POST['bio']);
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($name) || strlen($name) < 3) {
            $errors[] = "Name must be at least 3 characters long.";
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        }

        if (empty($specialization) || !in_array($specialization, $specializations)) {
            $errors[] = "Please select a valid specialization.";
        }

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

        if (!empty($new_password)) {
            if (empty($current_password)) {
                $errors[] = "Please enter your current password to change it.";
            } elseif (!password_verify($current_password, $panelist['password_hash'])) {
                $errors[] = "Current password is incorrect.";
            } elseif (strlen($new_password) < 8) {
                $errors[] = "New password must be at least 8 characters long.";
            } elseif ($new_password !== $confirm_password) {
                $errors[] = "New passwords do not match.";
            } elseif (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
                $errors[] = "New password must contain uppercase, lowercase, and numbers.";
            }
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

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

                $update_details = $pdo->prepare("
                    UPDATE panelist_details 
                    SET specialization = ?, title = ?, bio = ? 
                    WHERE user_id = ?
                ");
                $update_details->execute([$specialization, $title ?: NULL, $bio ?: NULL, $_SESSION['user_id']]);

                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, created_at) 
                    VALUES (?, 'update_profile', 'users', ?, ?, NOW())
                ");
                $log_stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);

                $pdo->commit();

                $_SESSION['name'] = $name;

                $success = "Profile updated successfully!";
                
                $user_stmt->execute([$_SESSION['user_id']]);
                $panelist = $user_stmt->fetch();

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
    <link rel="stylesheet" href="../assets/css/icons.css">
    <style>
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
            color: #6b6b6b;
            transition: color 0.3s ease;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .toggle-password:hover {
            color: #DC143C;
        }
        
        .toggle-password svg {
            width: 20px;
            height: 20px;
        }
    </style>
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
                        <h3>
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 0.5rem;">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            Personal Information
                        </h3>
                        <p>Update your account details and professional information</p>
                    </div>
                    
                    <form method="POST" action="profile.php" id="profileForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        
                        <div class="form-group">
                            <label for="name">Full Name <span class="required">*</span></label>
                            <input 
                                type="text" 
                                id="name" 
                                name="name" 
                                value="<?php echo htmlspecialchars($panelist['name']); ?>"
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
                                value="<?php echo htmlspecialchars($panelist['email']); ?>"
                                required
                            >
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="specialization">Specialization <span class="required">*</span></label>
                                <select id="specialization" name="specialization" required>
                                    <?php foreach ($specializations as $s): ?>
                                        <option value="<?php echo $s; ?>" <?php echo $panelist['specialization'] === $s ? 'selected' : ''; ?>>
                                            <?php echo $s; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="title">Academic Title</label>
                                <input 
                                    type="text" 
                                    id="title" 
                                    name="title" 
                                    value="<?php echo htmlspecialchars($panelist['title'] ?? ''); ?>"
                                    placeholder="e.g., PhD in Computer Science, Professor"
                                >
                                <small class="form-help">Optional - Your academic title or degree</small>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="bio">Professional Bio</label>
                            <textarea 
                                id="bio" 
                                name="bio" 
                                rows="4"
                                placeholder="Brief professional background and areas of expertise"
                            ><?php echo htmlspecialchars($panelist['bio'] ?? ''); ?></textarea>
                            <small class="form-help">Optional - Brief description of your expertise</small>
                        </div>

                        <hr style="margin: 2rem 0; border: none; border-top: 2px solid var(--border-color);">

                        <div class="form-card-header" style="margin-top: 2rem;">
                            <h3>
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 0.5rem;">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                </svg>
                                Change Password
                            </h3>
                            <p>Leave blank if you don't want to change your password</p>
                        </div>

                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <div class="password-wrapper">
                                <input 
                                    type="password" 
                                    id="current_password" 
                                    name="current_password"
                                    placeholder="Enter current password"
                                >
                                <button type="button" class="toggle-password" onclick="togglePasswordVisibility('current_password')">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                        <circle cx="12" cy="12" r="3"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <div class="password-wrapper">
                                    <input 
                                        type="password" 
                                        id="new_password" 
                                        name="new_password"
                                        placeholder="Minimum 8 characters"
                                        minlength="8"
                                    >
                                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility('new_password')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
                                    </button>
                                </div>
                                <small class="form-help">Must contain uppercase, lowercase, and number</small>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <div class="password-wrapper">
                                    <input 
                                        type="password" 
                                        id="confirm_password" 
                                        name="confirm_password"
                                        placeholder="Re-enter new password"
                                        minlength="8"
                                    >
                                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility('confirm_password')">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                            <circle cx="12" cy="12" r="3"/>
                                        </svg>
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
                        <h3>
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 0.5rem;">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                            </svg>
                            Account Information
                        </h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                            <div>
                                <strong>Account Status:</strong>
                                <p>
                                    <span class="badge badge-<?php echo $panelist['status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($panelist['status']); ?>
                                    </span>
                                </p>
                            </div>
                            <div>
                                <strong>Role:</strong>
                                <p><?php echo ucfirst($panelist['role']); ?></p>
                            </div>
                            <div>
                                <strong>Account Created:</strong>
                                <p><?php echo date('F d, Y', strtotime($panelist['created_at'])); ?></p>
                            </div>
                            <div>
                                <strong>Last Updated:</strong>
                                <p><?php echo $panelist['updated_at'] ? date('F d, Y', strtotime($panelist['updated_at'])) : 'Never'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        function togglePasswordVisibility(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const button = passwordField.nextElementSibling;
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                button.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                    </svg>
                `;
            } else {
                passwordField.type = 'password';
                button.innerHTML = `
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                `;
            }
        }

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

            document.getElementById('updateBtn').disabled = true;
            document.getElementById('updateBtn').textContent = 'Updating...';
        });
    </script>
    <script src="../assets/js/icons.js"></script>
</body>
</html>