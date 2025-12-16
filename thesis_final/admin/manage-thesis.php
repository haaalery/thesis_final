<?php
/**
 * Manage Thesis Groups - Approve/Reject thesis proposals
 * File: thesis_final/admin/manage-thesis.php
 * WITH EMAIL INTEGRATION
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

$page_title = "Manage Thesis";
$page_subtitle = "Review and approve thesis proposals";

$errors = [];
$success = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid request.";
    } else {
        $action = $_POST['action'];
        $group_id = intval($_POST['group_id']);

        // APPROVE THESIS
        if ($action === 'approve') {
            try {
                $pdo->beginTransaction();
                $pdo->prepare("UPDATE thesis_groups SET status = 'approved', approved_by = ?, approved_at = NOW(), updated_at = NOW() WHERE group_id = ?")->execute([$_SESSION['user_id'], $group_id]);

                // Notify members
                $members = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ?");
                $members->execute([$group_id]);
                foreach ($members->fetchAll() as $member) {
                    $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, 'Thesis Approved', 'Your thesis proposal has been approved! You can now request a defense schedule.', 'general', NOW())")->execute([$member['user_id']]);
                }

                $pdo->commit();
                $success = "Thesis approved successfully!";
                
                // 📧 SEND EMAIL NOTIFICATIONS
                require_once '../includes/notification_helper.php';
                $notif_helper = new NotificationHelper($pdo);
                $email_sent = $notif_helper->emailGroupStatusChange($group_id, 'approved');
                
                if ($email_sent > 0) {
                    error_log("✅ Sent approval emails to {$email_sent} members");
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Approval Error: " . $e->getMessage());
                $errors[] = "Error approving thesis.";
            }
        }

        // REJECT THESIS
        elseif ($action === 'reject') {
            $reason = trim($_POST['rejection_reason']);
            if (empty($reason)) {
                $errors[] = "Please provide rejection reason.";
            } else {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare("UPDATE thesis_groups SET status = 'rejected', rejection_reason = ?, updated_at = NOW() WHERE group_id = ?")->execute([$reason, $group_id]);

                    // Notify members
                    $members = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ?");
                    $members->execute([$group_id]);
                    foreach ($members->fetchAll() as $member) {
                        $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, 'Thesis Rejected', 'Your thesis proposal needs revision. Please check the feedback and resubmit.', 'general', NOW())")->execute([$member['user_id']]);
                    }

                    $pdo->commit();
                    $success = "Thesis rejected with feedback.";
                    
                    // 📧 SEND EMAIL NOTIFICATIONS
                    require_once '../includes/notification_helper.php';
                    $notif_helper = new NotificationHelper($pdo);
                    $email_sent = $notif_helper->emailGroupStatusChange($group_id, 'rejected', $reason);
                    
                    if ($email_sent > 0) {
                        error_log("✅ Sent rejection emails to {$email_sent} members");
                    }
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Rejection Error: " . $e->getMessage());
                    $errors[] = "Error rejecting thesis.";
                }
            }
        }
    }
}

// Get thesis groups
$filter = $_GET['status'] ?? 'pending_approval';
$where = $filter === 'all' ? "1=1" : "tg.status = '$filter'";

try {
    $stmt = $pdo->prepare("
        SELECT tg.*, u.name as created_by_name, 
               COUNT(DISTINCT gm.user_id) as member_count,
               COUNT(DISTINCT td.document_id) as document_count
        FROM thesis_groups tg
        LEFT JOIN users u ON tg.created_by = u.user_id
        LEFT JOIN group_members gm ON tg.group_id = gm.group_id
        LEFT JOIN thesis_documents td ON tg.group_id = td.group_id
        WHERE $where
        GROUP BY tg.group_id
        ORDER BY tg.created_at DESC
    ");
    $stmt->execute();
    $thesis_groups = $stmt->fetchAll();

    $counts = $pdo->query("SELECT status, COUNT(*) as count FROM thesis_groups GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $thesis_groups = [];
    $counts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Thesis - Admin</title>
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
                    <div class="stat-card"><div class="stat-icon warning">⏳</div><div class="stat-info"><h3><?php echo $counts['pending_approval'] ?? 0; ?></h3><p>Pending Approval</p></div></div>
                    <div class="stat-card"><div class="stat-icon success">✅</div><div class="stat-info"><h3><?php echo $counts['approved'] ?? 0; ?></h3><p>Approved</p></div></div>
                    <div class="stat-card"><div class="stat-icon danger">❌</div><div class="stat-info"><h3><?php echo $counts['rejected'] ?? 0; ?></h3><p>Rejected</p></div></div>
                </div>

                <div class="content-card">
                    <div class="card-body">
                        <div class="filter-group">
                            <label>Status:</label>
                            <select onchange="window.location.href='manage-thesis.php?status=' + this.value">
                                <option value="pending_approval" <?php echo $filter === 'pending_approval' ? 'selected' : ''; ?>>Pending Approval</option>
                                <option value="approved" <?php echo $filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo $filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All</option>
                            </select>
                        </div>
                    </div>
                </div>

                <?php if (empty($thesis_groups)): ?>
                    <div class="content-card"><div class="card-body"><div class="empty-state"><div class="icon">📚</div><h3>No Thesis Groups</h3><p>No thesis groups found with this status.</p></div></div></div>
                <?php else: ?>
                    <?php foreach ($thesis_groups as $group): ?>
                        <div class="content-card">
                            <div class="card-header">
                                <div>
                                    <h3><?php echo htmlspecialchars($group['group_name']); ?></h3>
                                    <p style="margin: 0; font-weight: normal; color: var(--text-secondary);"><?php echo htmlspecialchars($group['thesis_title']); ?></p>
                                </div>
                                <span class="badge badge-<?php echo $group['status'] === 'approved' ? 'success' : ($group['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $group['status'])); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                                    <div><strong>Course:</strong><p><?php echo htmlspecialchars($group['course']); ?></p></div>
                                    <div><strong>Specialization:</strong><p><?php echo htmlspecialchars($group['specialization']); ?></p></div>
                                    <div><strong>Members:</strong><p><?php echo $group['member_count']; ?> member(s)</p></div>
                                    <div><strong>Documents:</strong><p><?php echo $group['document_count']; ?> uploaded</p></div>
                                </div>
                                
                                <div style="padding: 1rem; background: var(--bg-color); border-radius: 8px;">
                                    <strong>Abstract:</strong>
                                    <p style="margin-top: 0.5rem;"><?php echo nl2br(htmlspecialchars($group['abstract'])); ?></p>
                                </div>

                                <?php if ($group['rejection_reason']): ?>
                                    <div style="margin-top: 1rem; padding: 1rem; background: #fee2e2; border-radius: 8px; border-left: 4px solid var(--error-color);">
                                        <strong>Rejection Reason:</strong>
                                        <p style="margin-top: 0.5rem;"><?php echo nl2br(htmlspecialchars($group['rejection_reason'])); ?></p>
                                    </div>
                                <?php endif; ?>

                                <div style="margin-top: 1rem; font-size: 0.9rem; color: var(--text-secondary);">
                                    <strong>Created by:</strong> <?php echo htmlspecialchars($group['created_by_name']); ?> on <?php echo date('F d, Y', strtotime($group['created_at'])); ?>
                                </div>

                                <?php if ($group['status'] === 'pending_approval'): ?>
                                    <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 2px solid var(--border-color); display: flex; gap: 1rem;">
                                        <form method="POST" style="flex: 1;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="group_id" value="<?php echo $group['group_id']; ?>">
                                            <button type="submit" class="btn btn-primary" style="width: 100%;">✅ Approve Thesis</button>
                                        </form>
                                        <button onclick="openRejectModal(<?php echo $group['group_id']; ?>, '<?php echo htmlspecialchars($group['group_name'], ENT_QUOTES); ?>')" class="btn btn-secondary" style="flex: 1;">❌ Reject Thesis</button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 12px; max-width: 500px; width: 90%;">
            <h3>Reject Thesis: <span id="modalGroupName"></span></h3>
            <form method="POST" id="rejectForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="group_id" id="modalGroupId">
                <div class="form-group">
                    <label>Rejection Reason *</label>
                    <textarea name="rejection_reason" required rows="6" placeholder="Provide detailed feedback on why the thesis is being rejected..."></textarea>
                </div>
                <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                    <button type="button" onclick="closeRejectModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        function openRejectModal(groupId, groupName) {
            document.getElementById('modalGroupId').value = groupId;
            document.getElementById('modalGroupName').textContent = groupName;
            document.getElementById('rejectModal').style.display = 'flex';
        }
        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
        }
        document.getElementById('rejectModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeRejectModal();
        });
    </script>
    <script src="../assets/js/icons.js"></script>
</body>
</html>