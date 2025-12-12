<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'panelist') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';
$page_title = "Notifications";
$page_subtitle = "View all your notifications";
$success = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $success = "Invalid request.";
    } else {
        $action = $_POST['action'];
        try {
            if ($action === 'mark_read') {
                $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?")->execute([intval($_POST['notification_id']), $_SESSION['user_id']]);
                $success = "Marked as read.";
            } elseif ($action === 'mark_all_read') {
                $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$_SESSION['user_id']]);
                $success = "All marked as read.";
            } elseif ($action === 'delete') {
                $pdo->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?")->execute([intval($_POST['notification_id']), $_SESSION['user_id']]);
                $success = "Deleted.";
            } elseif ($action === 'delete_all_read') {
                $pdo->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1")->execute([$_SESSION['user_id']]);
                $success = "All read notifications deleted.";
            }
        } catch (PDOException $e) {
            $success = "Error occurred.";
        }
    }
}

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$type_filter = isset($_GET['type']) ? $_GET['type'] : 'all';
$where_clauses = ["user_id = ?"];
$params = [$_SESSION['user_id']];
if ($filter === 'unread') $where_clauses[] = "is_read = 0";
elseif ($filter === 'read') $where_clauses[] = "is_read = 1";
if ($type_filter !== 'all') { $where_clauses[] = "type = ?"; $params[] = $type_filter; }
$where_sql = implode(' AND ', $where_clauses);

try {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE $where_sql ORDER BY created_at DESC");
    $stmt->execute($params);
    $notifications = $stmt->fetchAll();
    $count_stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread, SUM(CASE WHEN is_read = 1 THEN 1 ELSE 0 END) as read FROM notifications WHERE user_id = ?");
    $count_stmt->execute([$_SESSION['user_id']]);
    $counts = $count_stmt->fetch();
} catch (PDOException $e) {
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
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include 'includes/topbar.php'; ?>
            <div class="dashboard-content">
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-icon primary">📬</div><div class="stat-info"><h3><?php echo $counts['total']; ?></h3><p>Total</p></div></div>
                    <div class="stat-card"><div class="stat-icon warning">🔔</div><div class="stat-info"><h3><?php echo $counts['unread']; ?></h3><p>Unread</p></div></div>
                    <div class="stat-card"><div class="stat-icon success">✅</div><div class="stat-info"><h3><?php echo $counts['read']; ?></h3><p>Read</p></div></div>
                </div>

                <div class="content-card">
                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                            <div class="filter-group">
                                <label>Filter:</label>
                                <select onchange="window.location.href='notifications.php?filter=' + this.value + '&type=<?php echo $type_filter; ?>'">
                                    <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All</option>
                                    <option value="unread" <?php echo $filter === 'unread' ? 'selected' : ''; ?>>Unread</option>
                                    <option value="read" <?php echo $filter === 'read' ? 'selected' : ''; ?>>Read</option>
                                </select>
                                <label style="margin-left: 1rem;">Type:</label>
                                <select onchange="window.location.href='notifications.php?filter=<?php echo $filter; ?>&type=' + this.value">
                                    <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                                    <?php foreach ($notification_types as $type): ?>
                                        <option value="<?php echo $type; ?>" <?php echo $type_filter === $type ? 'selected' : ''; ?>><?php echo ucfirst($type); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div style="display: flex; gap: 0.5rem;">
                                <?php if ($counts['unread'] > 0): ?>
                                    <form method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="mark_all_read"><button type="submit" class="btn btn-secondary">Mark All Read</button></form>
                                <?php endif; ?>
                                <?php if ($counts['read'] > 0): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete all read?');"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="delete_all_read"><button type="submit" class="btn btn-secondary">Delete All Read</button></form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <div class="card-header"><h3>🔔 Notifications</h3></div>
                    <div class="card-body">
                        <?php if (empty($notifications)): ?>
                            <div class="empty-state"><div class="icon">📭</div><h3>No Notifications</h3><p>All caught up!</p></div>
                        <?php else: ?>
                            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                                <?php foreach ($notifications as $notif): ?>
                                    <div style="padding: 1rem; background: <?php echo $notif['is_read'] ? 'var(--bg-color)' : '#e0f2fe'; ?>; border-radius: 8px; border-left: 4px solid var(--primary-color); display: flex; justify-content: space-between; gap: 1rem;">
                                        <div style="flex: 1;">
                                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                                <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                                                <span class="badge badge-secondary" style="font-size: 0.75rem;"><?php echo ucfirst($notif['type']); ?></span>
                                                <?php if (!$notif['is_read']): ?><span style="background: var(--error-color); color: white; padding: 2px 6px; border-radius: 10px; font-size: 0.7rem; font-weight: 600;">NEW</span><?php endif; ?>
                                            </div>
                                            <p style="margin: 0 0 0.5rem 0;"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></p>
                                            <span style="font-size: 0.8rem; color: var(--text-secondary);"><?php $t = time() - strtotime($notif['created_at']); echo $t < 60 ? "Just now" : ($t < 3600 ? floor($t/60) . " min ago" : ($t < 86400 ? floor($t/3600) . " hr ago" : date('M d, Y h:i A', strtotime($notif['created_at'])))); ?></span>
                                        </div>
                                        <div style="display: flex; gap: 0.25rem; flex-shrink: 0;">
                                            <?php if (!$notif['is_read']): ?>
                                                <form method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="mark_read"><input type="hidden" name="notification_id" value="<?php echo $notif['notification_id']; ?>"><button type="submit" class="btn-icon" title="Mark Read">✅</button></form>
                                            <?php endif; ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete?');"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="notification_id" value="<?php echo $notif['notification_id']; ?>"><button type="submit" class="btn-icon btn-delete">🗑️</button></form>
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
</body>
</html>