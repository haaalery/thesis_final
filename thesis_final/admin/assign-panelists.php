<?php
// ============================================
// FILE: thesis_final/admin/assign-panelists.php
// COMPLETE FIXED VERSION
// ============================================
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../db_connect.php';
$page_title = "Assign Panelists";
$page_subtitle = "Assign panelists to defense schedules";
$errors = [];
$success = '';
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid request.";
    } else {
        $action = $_POST['action'];

        if ($action === 'assign_panelist') {
            $schedule_id = intval($_POST['schedule_id']);
            $panelist_id = intval($_POST['panelist_id']);
            $role = $_POST['role'];

            if (!in_array($role, ['chair', 'member', 'adviser'])) {
                $errors[] = "Invalid role.";
            } else {
                try {
                    // Check if already assigned
                    $check = $pdo->prepare("SELECT COUNT(*) FROM panel_assignments WHERE schedule_id = ? AND panelist_id = ?");
                    $check->execute([$schedule_id, $panelist_id]);
                    if ($check->fetchColumn() > 0) {
                        $errors[] = "Panelist already assigned to this schedule.";
                    } else {
                        $pdo->prepare("INSERT INTO panel_assignments (schedule_id, panelist_id, role, status, created_at) VALUES (?, ?, ?, 'pending', NOW())")->execute([$schedule_id, $panelist_id, $role]);
                        
                        // Notify panelist
                        $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, 'New Panel Assignment', 'You have been assigned as a panelist. Please review and respond.', 'assignment', NOW())")->execute([$panelist_id]);
                        
                        $success = "Panelist assigned successfully!";
                    }
                } catch (PDOException $e) {
                    $errors[] = "Error assigning panelist.";
                }
            }
        } elseif ($action === 'remove_assignment') {
            $assignment_id = intval($_POST['assignment_id']);
            try {
                $pdo->prepare("DELETE FROM panel_assignments WHERE assignment_id = ?")->execute([$assignment_id]);
                $success = "Assignment removed.";
            } catch (PDOException $e) {
                $errors[] = "Error removing assignment.";
            }
        }
    }
}

// FIXED QUERY - Only shows approved schedules with groups assigned
try {
    $schedules = $pdo->query("
        SELECT ds.*, 
               tg.group_name, 
               tg.thesis_title,
               tg.specialization, 
               v.venue_name, 
               COUNT(DISTINCT pa.panelist_id) as panelist_count 
        FROM defense_schedules ds 
        INNER JOIN thesis_groups tg ON ds.group_id = tg.group_id 
        LEFT JOIN venues v ON ds.venue_id = v.venue_id 
        LEFT JOIN panel_assignments pa ON ds.schedule_id = pa.schedule_id 
        WHERE ds.status = 'approved' 
        AND ds.group_id IS NOT NULL 
        AND ds.defense_date >= CURDATE() 
        GROUP BY ds.schedule_id 
        ORDER BY ds.defense_date ASC
    ")->fetchAll();
    
    $panelists = $pdo->query("SELECT u.user_id, u.name, pd.specialization, pd.title FROM users u INNER JOIN panelist_details pd ON u.user_id = pd.user_id WHERE u.status = 'active' ORDER BY u.name")->fetchAll();
} catch (PDOException $e) {
    $schedules = [];
    $panelists = [];
    error_log("Assign Panelists Query Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Panelists - Admin</title>
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
                <?php if (!empty($errors)): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

                <?php if (empty($schedules)): ?>
                    <div class="content-card"><div class="card-body"><div class="empty-state"><div class="icon">👨‍🏫</div><h3>No Approved Schedules</h3><p>No approved schedules found for panelist assignment. Make sure to approve schedule requests first.</p></div></div></div>
                <?php else: ?>
                    <?php foreach ($schedules as $schedule): ?>
                        <?php
                        $assigned = $pdo->prepare("SELECT pa.*, u.name, pd.title FROM panel_assignments pa INNER JOIN users u ON pa.panelist_id = u.user_id LEFT JOIN panelist_details pd ON u.user_id = pd.user_id WHERE pa.schedule_id = ?");
                        $assigned->execute([$schedule['schedule_id']]);
                        $assigned_panelists = $assigned->fetchAll();
                        ?>
                        <div class="content-card">
                            <div class="card-header">
                                <div>
                                    <h3><?php echo htmlspecialchars($schedule['group_name']); ?></h3>
                                    <p style="margin: 0; font-weight: normal; color: var(--text-secondary);">
                                        <?php echo htmlspecialchars($schedule['thesis_title']); ?><br>
                                        <?php echo date('M d, Y h:i A', strtotime($schedule['defense_date'] . ' ' . $schedule['defense_time'])); ?> - <?php echo htmlspecialchars($schedule['venue_name']); ?>
                                    </p>
                                </div>
                                <span class="badge badge-primary"><?php echo count($assigned_panelists); ?> Panelist(s)</span>
                            </div>
                            <div class="card-body">
                                <form method="POST" class="search-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="action" value="assign_panelist">
                                    <input type="hidden" name="schedule_id" value="<?php echo $schedule['schedule_id']; ?>">
                                    <select name="panelist_id" required style="flex: 2;"><option value="">Select Panelist</option><?php foreach ($panelists as $p): ?><option value="<?php echo $p['user_id']; ?>"><?php echo htmlspecialchars($p['name'] . ' - ' . $p['specialization']); ?></option><?php endforeach; ?></select>
                                    <select name="role" required><option value="chair">Chair</option><option value="member">Member</option><option value="adviser">Adviser</option></select>
                                    <button type="submit" class="btn btn-primary">Assign</button>
                                </form>

                                <?php if (!empty($assigned_panelists)): ?>
                                    <div style="margin-top: 1rem;">
                                        <strong>Assigned Panelists:</strong>
                                        <div style="margin-top: 0.5rem;">
                                            <?php foreach ($assigned_panelists as $ap): ?>
                                                <div style="padding: 0.75rem; background: var(--bg-color); border-radius: 8px; margin-bottom: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($ap['name']); ?></strong> <?php if ($ap['title']): ?>- <?php echo htmlspecialchars($ap['title']); ?><?php endif; ?>
                                                        <span class="badge badge-<?php echo $ap['role'] === 'chair' ? 'primary' : 'secondary'; ?>" style="margin-left: 0.5rem;"><?php echo ucfirst($ap['role']); ?></span>
                                                        <span class="badge badge-<?php echo $ap['status'] === 'accepted' ? 'success' : ($ap['status'] === 'declined' ? 'danger' : 'warning'); ?>" style="margin-left: 0.5rem;"><?php echo ucfirst($ap['status']); ?></span>
                                                    </div>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Remove this assignment?');"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="remove_assignment"><input type="hidden" name="assignment_id" value="<?php echo $ap['assignment_id']; ?>"><button type="submit" class="btn-icon btn-delete">❌</button></form>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/icons.js"></script>
</body>
</html>