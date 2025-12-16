<?php
/**
 * UPDATED: Thesis Group Management with Member Selection
 * File: thesis_final/student/thesis-group.php
 * FEATURES: Select 2 groupmates with checkboxes (3 members total)
 */

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

$page_title = "Thesis Group";
$page_subtitle = "Manage your thesis group and proposal";
$errors = [];
$success = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check existing group
try {
    $group_check = $pdo->prepare("
        SELECT tg.*, gm.role as member_role
        FROM thesis_groups tg
        INNER JOIN group_members gm ON tg.group_id = gm.group_id
        WHERE gm.user_id = ?
    ");
    $group_check->execute([$_SESSION['user_id']]);
    $existing_group = $group_check->fetch();
} catch (PDOException $e) {
    error_log("Group Check Error: " . $e->getMessage());
    $existing_group = null;
}

// Get group members if exists
$group_members = [];
if ($existing_group) {
    try {
        $members_stmt = $pdo->prepare("
            SELECT u.user_id, u.name, u.email, gm.role, sd.course, sd.year, sd.student_id
            FROM group_members gm
            INNER JOIN users u ON gm.user_id = u.user_id
            LEFT JOIN student_details sd ON u.user_id = sd.user_id
            WHERE gm.group_id = ?
            ORDER BY gm.role DESC, u.name ASC
        ");
        $members_stmt->execute([$existing_group['group_id']]);
        $group_members = $members_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Members Fetch Error: " . $e->getMessage());
    }
}

// Get available students for selection (not in any group)
$available_students = [];
if (!$existing_group) {
    try {
        $available_stmt = $pdo->prepare("
            SELECT u.user_id, u.name, u.email, sd.course, sd.year, sd.student_id
            FROM users u
            LEFT JOIN student_details sd ON u.user_id = sd.user_id
            LEFT JOIN group_members gm ON u.user_id = gm.user_id
            WHERE u.role = 'student' 
            AND u.user_id != ?
            AND gm.user_id IS NULL
            AND u.status = 'active'
            ORDER BY u.name ASC
        ");
        $available_stmt->execute([$_SESSION['user_id']]);
        $available_students = $available_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Available Students Error: " . $e->getMessage());
    }
}

$courses = ['Computer Science', 'Information Technology', 'Computer Engineering', 'Software Engineering', 'Data Science'];
$specializations = [
    'Web Development', 'Mobile Development', 'Data Science', 'Artificial Intelligence', 
    'Machine Learning', 'Cybersecurity', 'Game Development', 'Software Engineering',
    'Database Management', 'Network Administration', 'Cloud Computing', 'IoT'
];

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid request. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';

        // CREATE GROUP WITH MEMBER SELECTION
        if ($action === 'create_group' && !$existing_group) {
            $group_name = trim($_POST['group_name']);
            $thesis_title = trim($_POST['thesis_title']);
            $abstract = trim($_POST['abstract']);
            $course = trim($_POST['course']);
            $specialization = trim($_POST['specialization']);
            $selected_members = $_POST['members'] ?? [];

            // Validation
            if (empty($group_name) || strlen($group_name) < 3) {
                $errors[] = "Group name must be at least 3 characters long.";
            }

            if (empty($thesis_title) || strlen($thesis_title) < 10) {
                $errors[] = "Thesis title must be at least 10 characters long.";
            }

            if (empty($abstract) || strlen($abstract) < 50) {
                $errors[] = "Abstract must be at least 50 characters long.";
            }

            if (empty($course) || !in_array($course, $courses)) {
                $errors[] = "Please select a valid course.";
            }

            if (empty($specialization) || !in_array($specialization, $specializations)) {
                $errors[] = "Please select a valid specialization.";
            }

            // CRITICAL: Validate exactly 2 members selected
            if (count($selected_members) != 2) {
                $errors[] = "You must select exactly 2 groupmates (3 members total including you).";
            }

            // Validate selected members exist and available
            if (!empty($selected_members)) {
                foreach ($selected_members as $member_id) {
                    $verify_stmt = $pdo->prepare("
                        SELECT u.user_id 
                        FROM users u
                        LEFT JOIN group_members gm ON u.user_id = gm.user_id
                        WHERE u.user_id = ? AND u.role = 'student' AND gm.user_id IS NULL
                    ");
                    $verify_stmt->execute([$member_id]);
                    if (!$verify_stmt->fetch()) {
                        $errors[] = "One or more selected members is no longer available.";
                        break;
                    }
                }
            }

            if (empty($errors)) {
                try {
                    $pdo->beginTransaction();

                    // Insert thesis group
                    $insert_group = $pdo->prepare("
                        INSERT INTO thesis_groups (group_name, thesis_title, abstract, course, specialization, status, created_by, created_at) 
                        VALUES (?, ?, ?, ?, ?, 'pending_approval', ?, NOW())
                    ");
                    $insert_group->execute([$group_name, $thesis_title, $abstract, $course, $specialization, $_SESSION['user_id']]);
                    $group_id = $pdo->lastInsertId();

                    // Add creator as leader
                    $insert_leader = $pdo->prepare("
                        INSERT INTO group_members (group_id, user_id, role, joined_at) 
                        VALUES (?, ?, 'leader', NOW())
                    ");
                    $insert_leader->execute([$group_id, $_SESSION['user_id']]);

                    // Add selected members
                    $insert_member = $pdo->prepare("
                        INSERT INTO group_members (group_id, user_id, role, joined_at) 
                        VALUES (?, ?, 'member', NOW())
                    ");
                    
                    foreach ($selected_members as $member_id) {
                        $insert_member->execute([$group_id, $member_id]);
                        
                        // Notify member
                        $notif = $pdo->prepare("
                            INSERT INTO notifications (user_id, title, message, type, created_at) 
                            VALUES (?, 'Added to Thesis Group', ?, 'general', NOW())
                        ");
                        $message = "You have been added to thesis group: " . $group_name;
                        $notif->execute([$member_id, $message]);
                    }

                    // Notify creator
                    $notif_stmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type, created_at) 
                        VALUES (?, 'Thesis Group Created', 'Your thesis group has been created and submitted for approval.', 'general', NOW())
                    ");
                    $notif_stmt->execute([$_SESSION['user_id']]);

                    // Log activity
                    $log_stmt = $pdo->prepare("
                        INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, created_at) 
                        VALUES (?, 'create_thesis_group', 'thesis_groups', ?, ?, NOW())
                    ");
                    $log_stmt->execute([$_SESSION['user_id'], $group_id, $_SERVER['REMOTE_ADDR']]);

                    $pdo->commit();

                    $success = "Thesis group created successfully with 3 members! Submitted for admin approval.";
                    header("Refresh: 2; url=thesis-group.php");

                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Group Creation Error: " . $e->getMessage());
                    $errors[] = "An error occurred while creating your group. Please try again.";
                }
            }
        }

        // UPDATE GROUP
        elseif ($action === 'update_group' && $existing_group && $existing_group['member_role'] === 'leader' && in_array($existing_group['status'], ['pending', 'pending_approval', 'rejected'])) {
            $thesis_title = trim($_POST['thesis_title']);
            $abstract = trim($_POST['abstract']);
            $specialization = trim($_POST['specialization']);

            if (empty($thesis_title) || strlen($thesis_title) < 10) {
                $errors[] = "Thesis title must be at least 10 characters long.";
            }

            if (empty($abstract) || strlen($abstract) < 50) {
                $errors[] = "Abstract must be at least 50 characters long.";
            }

            if (empty($specialization) || !in_array($specialization, $specializations)) {
                $errors[] = "Please select a valid specialization.";
            }

            if (empty($errors)) {
                try {
                    $update_stmt = $pdo->prepare("
                        UPDATE thesis_groups 
                        SET thesis_title = ?, abstract = ?, specialization = ?, 
                            status = 'pending_approval', updated_at = NOW() 
                        WHERE group_id = ?
                    ");
                    $update_stmt->execute([$thesis_title, $abstract, $specialization, $existing_group['group_id']]);

                    $log_stmt = $pdo->prepare("
                        INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, created_at) 
                        VALUES (?, 'update_thesis_group', 'thesis_groups', ?, ?, NOW())
                    ");
                    $log_stmt->execute([$_SESSION['user_id'], $existing_group['group_id'], $_SERVER['REMOTE_ADDR']]);

                    $success = "Thesis information updated successfully!";
                    
                    $group_check->execute([$_SESSION['user_id']]);
                    $existing_group = $group_check->fetch();

                } catch (PDOException $e) {
                    error_log("Group Update Error: " . $e->getMessage());
                    $errors[] = "An error occurred while updating your group. Please try again.";
                }
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
    <title>Thesis Group - Thesis Panel System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="../assets/css/icons.css">
    <style>
        .member-selection-grid {
            display: grid;
            gap: 1rem;
            margin: 1.5rem 0;
        }
        .member-card {
            border: 2px solid var(--border-color);
            padding: 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .member-card:hover {
            border-color: var(--primary-color);
            background: var(--bg-color);
        }
        .member-card.selected {
            border-color: var(--primary-color);
            background: #e0f2fe;
        }
        .member-checkbox {
            width: 24px;
            height: 24px;
            cursor: pointer;
        }
        .member-info {
            flex: 1;
        }
        .member-count {
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            text-align: center;
            margin-bottom: 1rem;
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
                        <ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if (!$existing_group): ?>
                    <!-- CREATE GROUP WITH MEMBER SELECTION -->
                    <div class="form-card">
                        <div class="form-card-header">
                            <h3>👥 Create Thesis Group (3 Members Required)</h3>
                            <p>Select exactly 2 groupmates to join you in this thesis group</p>
                        </div>
                        
                        <form method="POST" action="thesis-group.php" id="createGroupForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="create_group">
                            
                            <div class="form-group">
                                <label for="group_name">Group Name <span class="required">*</span></label>
                                <input type="text" id="group_name" name="group_name" required minlength="3" placeholder="e.g., Team Alpha, The Innovators">
                                <small class="form-help">Choose a unique name for your group</small>
                            </div>

                            <div class="form-group">
                                <label for="thesis_title">Thesis Title <span class="required">*</span></label>
                                <input type="text" id="thesis_title" name="thesis_title" required minlength="10" placeholder="Enter your thesis title">
                            </div>

                            <div class="form-group">
                                <label for="abstract">Abstract <span class="required">*</span></label>
                                <textarea id="abstract" name="abstract" required minlength="50" rows="6" placeholder="Provide a brief description of your thesis (minimum 50 characters)"></textarea>
                                <small class="form-help">Describe your research objectives, methodology, and expected outcomes</small>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="course">Course <span class="required">*</span></label>
                                    <select id="course" name="course" required>
                                        <option value="">Select Course</option>
                                        <?php foreach ($courses as $c): ?>
                                            <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="specialization">Specialization <span class="required">*</span></label>
                                    <select id="specialization" name="specialization" required>
                                        <option value="">Select Specialization</option>
                                        <?php foreach ($specializations as $s): ?>
                                            <option value="<?php echo $s; ?>"><?php echo $s; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <hr style="margin: 2rem 0; border: none; border-top: 2px solid var(--border-color);">

                            <div class="form-group">
                                <label style="font-size: 1.1rem; margin-bottom: 0.5rem;">👨‍🎓 Select Your 2 Groupmates <span class="required">*</span></label>
                                <div id="memberCount" class="member-count">0 / 2 Members Selected</div>
                                
                                <?php if (empty($available_students)): ?>
                                    <div class="alert alert-warning">No available students found. All students may already be in groups.</div>
                                <?php else: ?>
                                    <div class="member-selection-grid">
                                        <?php foreach ($available_students as $student): ?>
                                            <div class="member-card" onclick="toggleMember(this, <?php echo $student['user_id']; ?>)">
                                                <input type="checkbox" name="members[]" value="<?php echo $student['user_id']; ?>" class="member-checkbox" id="member_<?php echo $student['user_id']; ?>">
                                                <div class="member-info">
                                                    <strong><?php echo htmlspecialchars($student['name']); ?></strong><br>
                                                    <small style="color: var(--text-secondary);">
                                                        <?php echo htmlspecialchars($student['email']); ?><br>
                                                        <?php echo htmlspecialchars($student['course'] . ' - ' . $student['year']); ?>
                                                        <?php if ($student['student_id']): ?>
                                                            | ID: <?php echo htmlspecialchars($student['student_id']); ?>
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="form-actions">
                                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary" id="createBtn" <?php echo empty($available_students) ? 'disabled' : ''; ?>>
                                    Create Group with 3 Members
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <!-- VIEW/EDIT GROUP -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3>👥 <?php echo htmlspecialchars($existing_group['group_name']); ?></h3>
                            <span class="badge badge-<?php echo $existing_group['status'] === 'approved' ? 'success' : ($existing_group['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $existing_group['status'])); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <?php if ($existing_group['status'] === 'rejected' && $existing_group['rejection_reason']): ?>
                                <div class="alert alert-error">
                                    <strong>Rejection Reason:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($existing_group['rejection_reason'])); ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="thesis-group.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="update_group">
                                
                                <div class="form-group">
                                    <label>Thesis Title <span class="required">*</span></label>
                                    <input type="text" name="thesis_title" value="<?php echo htmlspecialchars($existing_group['thesis_title']); ?>" required minlength="10" <?php echo $existing_group['member_role'] !== 'leader' || $existing_group['status'] === 'approved' ? 'disabled' : ''; ?>>
                                </div>

                                <div class="form-group">
                                    <label>Abstract <span class="required">*</span></label>
                                    <textarea name="abstract" required minlength="50" rows="6" <?php echo $existing_group['member_role'] !== 'leader' || $existing_group['status'] === 'approved' ? 'disabled' : ''; ?>><?php echo htmlspecialchars($existing_group['abstract']); ?></textarea>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Course</label>
                                        <input type="text" value="<?php echo htmlspecialchars($existing_group['course']); ?>" disabled>
                                    </div>

                                    <div class="form-group">
                                        <label>Specialization <span class="required">*</span></label>
                                        <select name="specialization" required <?php echo $existing_group['member_role'] !== 'leader' || $existing_group['status'] === 'approved' ? 'disabled' : ''; ?>>
                                            <?php foreach ($specializations as $s): ?>
                                                <option value="<?php echo $s; ?>" <?php echo $existing_group['specialization'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <?php if ($existing_group['member_role'] === 'leader' && in_array($existing_group['status'], ['pending', 'pending_approval', 'rejected'])): ?>
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-primary">Update Thesis Information</button>
                                    </div>
                                <?php endif; ?>
                            </form>

                            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid var(--border-color);">
                                <strong>Created:</strong> <?php echo date('F d, Y', strtotime($existing_group['created_at'])); ?>
                                <?php if ($existing_group['updated_at']): ?><br><strong>Last Updated:</strong> <?php echo date('F d, Y', strtotime($existing_group['updated_at'])); ?><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- GROUP MEMBERS -->
                    <div class="content-card" style="margin-top: 1.5rem;">
                        <div class="card-header">
                            <h3>👨‍🎓 Group Members (<?php echo count($group_members); ?>/3)</h3>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Student ID</th>
                                            <th>Course & Year</th>
                                            <th>Role</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($group_members as $member): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($member['name']); ?></td>
                                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                                <td><?php echo htmlspecialchars($member['student_id'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($member['course'] . ' - ' . $member['year']); ?></td>
                                                <td>
                                                    <span class="badge badge-<?php echo $member['role'] === 'leader' ? 'primary' : 'secondary'; ?>">
                                                        <?php echo ucfirst($member['role']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        let selectedCount = 0;
        const maxMembers = 2;

        function toggleMember(card, memberId) {
            const checkbox = document.getElementById('member_' + memberId);
            
            if (checkbox.checked) {
                checkbox.checked = false;
                card.classList.remove('selected');
                selectedCount--;
            } else {
                if (selectedCount >= maxMembers) {
                    alert('You can only select ' + maxMembers + ' groupmates (3 members total including you).');
                    return;
                }
                checkbox.checked = true;
                card.classList.add('selected');
                selectedCount++;
            }
            
            updateMemberCount();
        }

        function updateMemberCount() {
            const countDisplay = document.getElementById('memberCount');
            countDisplay.textContent = selectedCount + ' / ' + maxMembers + ' Members Selected';
            countDisplay.style.background = selectedCount === maxMembers ? 'var(--success-color)' : 'var(--primary-color)';
        }

        document.getElementById('createGroupForm')?.addEventListener('submit', function(e) {
            if (selectedCount !== maxMembers) {
                e.preventDefault();
                alert('Please select exactly ' + maxMembers + ' groupmates to proceed.');
                return false;
            }
            
            const submitBtn = document.getElementById('createBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Group...';
        });
    </script>
    <script src="../assets/js/icons.js"></script>
</body>
</html>