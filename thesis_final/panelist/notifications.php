<?php
/**
 * Panelist Notifications Page - FULLY SYNCED WITH TOPBAR
 * File: thesis_final/panelist/notifications.php
 * FEATURES: Mark as read, delete, filter by type, sync with bell icon
 */

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'panelist') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

$page_title = "Notifications";
$page_subtitle = "View all your notifications";

$errors = [];
$success = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --------------------
// HANDLE ACTIONS
// --------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $success = "Invalid request.";
    } else {
        try {
            if ($_POST['action'] === 'mark_read') {
                $stmt = $pdo->prepare("
                    UPDATE notifications 
                    SET is_read = 1 
                    WHERE notification_id = ? 
                    AND user_id = ?
                ");
                $stmt->execute([
                    intval($_POST['notification_id']),
                    $_SESSION['user_id']
                ]);
                $success = "Notification marked as read.";

            } elseif ($_POST['action'] === 'mark_all_read') {
                $stmt = $pdo->prepare("
                    UPDATE notifications 
                    SET is_read = 1 
                    WHERE user_id = ? 
                    AND is_read = 0
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $success = "All notifications marked as read.";

            } elseif ($_POST['action'] === 'delete') {
                $stmt = $pdo->prepare("
                    DELETE FROM notifications 
                    WHERE notification_id = ? 
                    AND user_id = ?
                ");
                $stmt->execute([
                    intval($_POST['notification_id']),
                    $_SESSION['user_id']
                ]);
                $success = "Notification deleted.";

            } elseif ($_POST['action'] === 'delete_all_read') {
                $stmt = $pdo->prepare("
                    DELETE FROM notifications 
                    WHERE user_id = ? 
                    AND is_read = 1
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $success = "All read notifications deleted.";
            }
        } catch (PDOException $e) {
            error_log("Notification Action Error: " . $e->getMessage());
            $success = "An error occurred.";
        }
    }
}

// --------------------
// FILTERS
// --------------------
$filter = $_GET['filter'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';

$where_clauses = ["user_id = ?"];
$params = [$_SESSION['user_id']];

if ($filter === 'unread') {
    $where_clauses[] = "is_read = 0";
} elseif ($filter === 'read') {
    $where_clauses[] = "is_read = 1";
}

if ($type_filter !== 'all') {
    $where_clauses[] = "type = ?";
    $params[] = $type_filter;
}

$where_sql = implode(' AND ', $where_clauses);

// --------------------
// FETCH NOTIFICATIONS
// --------------------
try {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE $where_sql 
        ORDER BY created_at DESC
    ");
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();

    $count_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,
            SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as `read`
        FROM notifications 
        WHERE user_id = ?
    ");
    $count_stmt->execute([$_SESSION['user_id']]);
    $counts = $count_stmt->fetch();

} catch (PDOException $e) {
    error_log("Notifications Fetch Error: " . $e->getMessage());
    $notifications = [];
    $counts = ['total' => 0, 'unread' => 0, 'read' => 0];
}

$notification_types = ['general', 'schedule', 'evaluation', 'assignment', 'document'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Thesis Panel System</title>
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

            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <!-- STATS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">📬</div>
                    <div class="stat-info">
                        <h3><?= $counts['total'] ?></h3>
                        <p>Total Notifications</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon warning">🔔</div>
                    <div class="stat-info">
                        <h3><?= $counts['unread'] ?></h3>
                        <p>Unread</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">✅</div>
                    <div class="stat-info">
                        <h3><?= $counts['read'] ?></h3>
                        <p>Read</p>
                    </div>
                </div>
            </div>

            <!-- FILTERS & ACTIONS -->
            <div class="content-card">
                <div class="card-body">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div class="filter-group">
                            <label>Filter:</label>
                            <select onchange="window.location.href='notifications.php?filter=' + this.value + '&type=<?= $type_filter ?>'">
                                <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Notifications</option>
                                <option value="unread" <?= $filter === 'unread' ? 'selected' : '' ?>>Unread Only</option>
                                <option value="read" <?= $filter === 'read' ? 'selected' : '' ?>>Read Only</option>
                            </select>

                            <label style="margin-left: 1rem;">Type:</label>
                            <select onchange="window.location.href='notifications.php?filter=<?= $filter ?>&type=' + this.value">
                                <option value="all" <?= $type_filter === 'all' ? 'selected' : '' ?>>All Types</option>
                                <?php foreach ($notification_types as $type): ?>
                                    <option value="<?= $type ?>" <?= $type_filter === $type ? 'selected' : '' ?>>
                                        <?= ucfirst($type) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div style="display: flex; gap: 0.5rem;">
                            <?php if ($counts['unread'] > 0): ?>
                                <form method="POST" action="notifications.php" style="display: inline;">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="mark_all_read">
                                    <button type="submit" class="btn btn-secondary">Mark All as Read</button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($counts['read'] > 0): ?>
                                <form method="POST" action="notifications.php" style="display: inline;" onsubmit="return confirm('Delete all read notifications?');">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="delete_all_read">
                                    <button type="submit" class="btn btn-secondary">Delete All Read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- NOTIFICATIONS LIST -->
            <div class="content-card">
                <div class="card-header">
                    <h3>🔔 Notifications</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <div class="icon">📭</div>
                            <h3>No Notifications</h3>
                            <p><?= $filter === 'unread' ? 'You have no unread notifications.' : 'No notifications found.' ?></p>
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <?php foreach ($notifications as $notif): ?>
                                <div style="padding: 1rem; background: <?= $notif['is_read'] ? 'var(--bg-color)' : '#e0f2fe' ?>; border-radius: 8px; border-left: 4px solid <?php 
                                    echo $notif['type'] === 'schedule' ? '#3b82f6' : 
                                         ($notif['type'] === 'evaluation' ? '#10b981' : 
                                         ($notif['type'] === 'document' ? '#f59e0b' : '#6366f1')); 
                                ?>; display: flex; justify-content: space-between; align-items: start; gap: 1rem;">
                                    <div style="flex: 1;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                            <strong style="color: var(--text-primary);"><?= htmlspecialchars($notif['title']) ?></strong>
                                            <span class="badge badge-<?php 
                                                echo $notif['type'] === 'schedule' ? 'primary' : 
                                                     ($notif['type'] === 'evaluation' ? 'success' : 
                                                     ($notif['type'] === 'document' ? 'warning' : 'secondary')); 
                                            ?>" style="font-size: 0.75rem;">
                                                <?= ucfirst($notif['type']) ?>
                                            </span>
                                            <?php if (!$notif['is_read']): ?>
                                                <span style="background: var(--error-color); color: white; padding: 2px 6px; border-radius: 10px; font-size: 0.7rem; font-weight: 600;">NEW</span>
                                            <?php endif; ?>
                                        </div>
                                        <p style="margin: 0 0 0.5rem 0; color: var(--text-primary);">
                                            <?= nl2br(htmlspecialchars($notif['message'])) ?>
                                        </p>
                                        <span style="font-size: 0.8rem; color: var(--text-secondary);">
                                            <?php 
                                            $time_diff = time() - strtotime($notif['created_at']);
                                            if ($time_diff < 60) {
                                                echo "Just now";
                                            } elseif ($time_diff < 3600) {
                                                echo floor($time_diff / 60) . " minutes ago";
                                            } elseif ($time_diff < 86400) {
                                                echo floor($time_diff / 3600) . " hours ago";
                                            } else {
                                                echo date('M d, Y h:i A', strtotime($notif['created_at']));
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <div style="display: flex; gap: 0.25rem; flex-shrink: 0;">
                                        <?php if (!$notif['is_read']): ?>
                                            <form method="POST" action="notifications.php" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="action" value="mark_read">
                                                <input type="hidden" name="notification_id" value="<?= $notif['notification_id'] ?>">
                                                <button type="submit" class="btn-icon" title="Mark as Read" style="font-size: 1rem;">✅</button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" action="notifications.php" style="display: inline;" onsubmit="return confirm('Delete this notification?');">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="notification_id" value="<?= $notif['notification_id'] ?>">
                                            <button type="submit" class="btn-icon btn-delete" title="Delete">🗑️</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="../assets/js/main.js"></script>
<script src="../assets/js/icons.js"></script>
</body>
</html>