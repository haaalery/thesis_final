<?php
/**
 * Manage Users Page - Add, Edit, Delete Users
 * File: thesis_final/admin/manage-users.php
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

$page_title = "Manage Users";
$page_subtitle = "Add, edit, and manage system users";

$errors = [];
$success = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid request.";
    } else {
        $action = $_POST['action'];

        // ADD USER
        if ($action === 'add_user') {
            $name = trim($_POST['name']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $role = $_POST['role'];
            $course = trim($_POST['course'] ?? '');
            $year = trim($_POST['year'] ?? '');
            $specialization = trim($_POST['specialization'] ?? '');
            $title = trim($_POST['title'] ?? '');

            if (empty($name) || strlen($name) < 3) $errors[] = "Name must be at least 3 characters.";
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email.";
            if (empty($password) || strlen($password) < 8) $errors[] = "Password must be at least 8 characters.";
            if (!in_array($role, ['student', 'panelist', 'admin'])) $errors[] = "Invalid role.";

            if (empty($errors)) {
                try {
                    // Check if email exists
                    $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
                    $check->execute([$email]);
                    if ($check->fetchColumn() > 0) {
                        $errors[] = "Email already exists.";
                    } else {
                        $pdo->beginTransaction();
                        
                        $password_hash = password_hash($password, PASSWORD_DEFAULT);
                        $insert = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())");
                        $insert->execute([$name, $email, $password_hash, $role]);
                        $user_id = $pdo->lastInsertId();

                        // Create role-specific details
                        if ($role === 'student') {
                            $pdo->prepare("INSERT INTO student_details (user_id, course, year) VALUES (?, ?, ?)")->execute([$user_id, $course, $year]);
                        } elseif ($role === 'panelist') {
                            $pdo->prepare("INSERT INTO panelist_details (user_id, specialization, title) VALUES (?, ?, ?)")->execute([$user_id, $specialization, $title]);
                        }

                        // Notification
                        $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, 'Account Created', 'Your account has been created. Login email: $email', 'general', NOW())")->execute([$user_id]);

                        // Log
                        $pdo->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, created_at) VALUES (?, 'create_user', 'users', ?, ?, NOW())")->execute([$_SESSION['user_id'], $user_id, $_SERVER['REMOTE_ADDR']]);

                        $pdo->commit();
                        $success = "User added successfully!";
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Add User Error: " . $e->getMessage());
                    $errors[] = "Error adding user.";
                }
            }
        }

        // UPDATE STATUS
        elseif ($action === 'update_status') {
            $user_id = intval($_POST['user_id']);
            $status = $_POST['status'];
            
            if (in_array($status, ['active', 'inactive'])) {
                try {
                    $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE user_id = ? AND user_id != ?")->execute([$status, $user_id, $_SESSION['user_id']]);
                    $success = "User status updated.";
                } catch (PDOException $e) {
                    $errors[] = "Error updating status.";
                }
            }
        }

        // DELETE USER
        elseif ($action === 'delete_user') {
            $user_id = intval($_POST['user_id']);
            if ($user_id !== $_SESSION['user_id']) {
                try {
                    $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$user_id]);
                    $success = "User deleted.";
                } catch (PDOException $e) {
                    $errors[] = "Error deleting user.";
                }
            }
        }
    }
}

// Get users
$filter_role = $_GET['role'] ?? 'all';
$search = $_GET['search'] ?? '';

$where = ["1=1"];
$params = [];

if ($filter_role !== 'all') {
    $where[] = "role = ?";
    $params[] = $filter_role;
}
if ($search) {
    $where[] = "(name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC");
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    $counts = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $users = [];
    $counts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="../assets/css/icons.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include 'includes/topbar.php'; ?>
            <div class="dashboard-content">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-icon primary">👨‍🎓</div><div class="stat-info"><h3><?php echo $counts['student'] ?? 0; ?></h3><p>Students</p></div></div>
                    <div class="stat-card"><div class="stat-icon success">👨‍🏫</div><div class="stat-info"><h3><?php echo $counts['panelist'] ?? 0; ?></h3><p>Panelists</p></div></div>
                    <div class="stat-card"><div class="stat-icon warning">🛠️</div><div class="stat-info"><h3><?php echo $counts['admin'] ?? 0; ?></h3><p>Admins</p></div></div>
                </div>

                <div class="content-card">
                    <div class="card-header">
                        <h3>➕ Add New User</h3>
                        <button onclick="toggleAddForm()" class="btn btn-primary">Add User</button>
                    </div>
                    <div class="card-body" id="addUserForm" style="display: none;">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="add_user">
                            <div class="form-row">
                                <div class="form-group"><label>Name *</label><input type="text" name="name" required></div>
                                <div class="form-group"><label>Email *</label><input type="email" name="email" required></div>
                            </div>
                            <div class="form-row">
                                <div class="form-group"><label>Password *</label><input type="password" name="password" required minlength="8"></div>
                                <div class="form-group"><label>Role *</label><select name="role" id="roleSelect" required onchange="toggleRoleFields()"><option value="student">Student</option><option value="panelist">Panelist</option><option value="admin">Admin</option></select></div>
                            </div>
                            <div id="studentFields" class="form-row">
                                <div class="form-group"><label>Course</label><input type="text" name="course"></div>
                                <div class="form-group"><label>Year</label><input type="text" name="year"></div>
                            </div>
                            <div id="panelistFields" class="form-row" style="display: none;">
                                <div class="form-group"><label>Specialization</label><input type="text" name="specialization"></div>
                                <div class="form-group"><label>Title</label><input type="text" name="title"></div>
                            </div>
                            <div class="form-actions"><button type="button" onclick="toggleAddForm()" class="btn btn-secondary">Cancel</button><button type="submit" class="btn btn-primary">Add User</button></div>
                        </form>
                    </div>
                </div>

                <div class="content-card">
                    <div class="card-header"><h3>👥 All Users</h3></div>
                    <div class="card-body">
                        <div style="display: flex; gap: 1rem; margin-bottom: 1rem; flex-wrap: wrap;">
                            <div class="filter-group">
                                <label>Role:</label>
                                <select onchange="window.location.href='manage-users.php?role=' + this.value + '&search=<?php echo urlencode($search); ?>'">
                                    <option value="all" <?php echo $filter_role === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="student" <?php echo $filter_role === 'student' ? 'selected' : ''; ?>>Students</option>
                                    <option value="panelist" <?php echo $filter_role === 'panelist' ? 'selected' : ''; ?>>Panelists</option>
                                    <option value="admin" <?php echo $filter_role === 'admin' ? 'selected' : ''; ?>>Admins</option>
                                </select>
                            </div>
                            <form method="GET" class="search-form" style="flex: 1;">
                                <input type="hidden" name="role" value="<?php echo htmlspecialchars($filter_role); ?>">
                                <input type="text" name="search" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit">Search</button>
                            </form>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><span class="badge badge-secondary"><?php echo ucfirst($user['role']); ?></span></td>
                                            <td><span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                            <td class="action-buttons">
                                                <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <input type="hidden" name="status" value="<?php echo $user['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                        <button type="submit" class="btn-icon" title="Toggle Status"><?php echo $user['status'] === 'active' ? '🔒' : '🔓'; ?></button>
                                                    </form>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this user?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="action" value="delete_user">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                        <button type="submit" class="btn-icon btn-delete">🗑️</button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/main.js"></script>
    <script>
        function toggleAddForm() {
            const form = document.getElementById('addUserForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
        function toggleRoleFields() {
            const role = document.getElementById('roleSelect').value;
            document.getElementById('studentFields').style.display = role === 'student' ? 'flex' : 'none';
            document.getElementById('panelistFields').style.display = role === 'panelist' ? 'flex' : 'none';
        }
    </script>
    <script src="../assets/js/icons.js"></script>
</body>
</html>