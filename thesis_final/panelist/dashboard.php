<?php
/**
 * Panelist Dashboard
 * Main overview page for panelist users
 */

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'panelist') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

// Page title and subtitle
$page_title = "Dashboard";
$page_subtitle = "Overview of your panel assignments";

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
} catch (PDOException $e) {
    error_log("Panelist Details Error: " . $e->getMessage());
    die("Error loading panelist information.");
}

// Get statistics
try {
    // Total assignments
    $total_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM panel_assignments WHERE panelist_id = ?");
    $total_stmt->execute([$_SESSION['user_id']]);
    $total_assignments = $total_stmt->fetch()['total'];

    // Pending assignments
    $pending_stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM panel_assignments WHERE panelist_id = ? AND status = 'pending'");
    $pending_stmt->execute([$_SESSION['user_id']]);
    $pending_assignments = $pending_stmt->fetch()['pending'];

    // Completed evaluations
    $eval_stmt = $pdo->prepare("SELECT COUNT(*) as completed FROM evaluations WHERE panelist_id = ?");
    $eval_stmt->execute([$_SESSION['user_id']]);
    $completed_evaluations = $eval_stmt->fetch()['completed'];

    // Upcoming defenses
    $upcoming_stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT ds.schedule_id) as upcoming
        FROM defense_schedules ds
        INNER JOIN panel_assignments pa ON ds.schedule_id = pa.schedule_id
        WHERE pa.panelist_id = ? 
        AND ds.status IN ('approved')
        AND ds.defense_date >= CURDATE()
    ");
    $upcoming_stmt->execute([$_SESSION['user_id']]);
    $upcoming_defenses = $upcoming_stmt->fetch()['upcoming'];
} catch (PDOException $e) {
    error_log("Stats Error: " . $e->getMessage());
    $total_assignments = $pending_assignments = $completed_evaluations = $upcoming_defenses = 0;
}

// Get upcoming defense schedules
try {
    $schedules_stmt = $pdo->prepare("
        SELECT ds.*, v.venue_name, v.building, v.room_number, pa.role, pa.status as assignment_status,
               tg.group_name, tg.thesis_title
        FROM defense_schedules ds
        INNER JOIN panel_assignments pa ON ds.schedule_id = pa.schedule_id
        LEFT JOIN venues v ON ds.venue_id = v.venue_id
        LEFT JOIN thesis_groups tg ON ds.group_id = tg.group_id
        WHERE pa.panelist_id = ? 
        AND ds.status IN ('approved')
        AND ds.defense_date >= CURDATE()
        ORDER BY ds.defense_date ASC, ds.defense_time ASC
        LIMIT 5
    ");
    $schedules_stmt->execute([$_SESSION['user_id']]);
    $upcoming_schedules = $schedules_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Schedules Error: " . $e->getMessage());
    $upcoming_schedules = [];
}

// Get recent notifications
try {
    $notif_stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $notif_stmt->execute([$_SESSION['user_id']]);
    $notifications = $notif_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Notifications Error: " . $e->getMessage());
    $notifications = [];
}

// Get pending assignments
try {
    $pending_list_stmt = $pdo->prepare("
        SELECT pa.*, ds.defense_date, ds.defense_time, v.venue_name,
               tg.group_name, tg.thesis_title
        FROM panel_assignments pa
        INNER JOIN defense_schedules ds ON pa.schedule_id = ds.schedule_id
        LEFT JOIN venues v ON ds.venue_id = v.venue_id
        LEFT JOIN thesis_groups tg ON ds.group_id = tg.group_id
        WHERE pa.panelist_id = ? AND pa.status = 'pending'
        ORDER BY ds.defense_date ASC
        LIMIT 5
    ");
    $pending_list_stmt->execute([$_SESSION['user_id']]);
    $pending_list = $pending_list_stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Pending List Error: " . $e->getMessage());
    $pending_list = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panelist Dashboard - Thesis Panel System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/icons.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/topbar.php'; ?>

            <div class="dashboard-content">
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            📋
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_assignments; ?></h3>
                            <p>Total Assignments</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon warning">
                            ⏳
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $pending_assignments; ?></h3>
                            <p>Pending Response</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon success">
                            ✅
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $completed_evaluations; ?></h3>
                            <p>Completed Evaluations</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon primary">
                            📅
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $upcoming_defenses; ?></h3>
                            <p>Upcoming Defenses</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>🚀 Quick Actions</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <a href="assignments.php" class="btn btn-primary" style="text-align: center;">
                                📋 View Assignments
                            </a>
                            <a href="schedule.php" class="btn btn-secondary" style="text-align: center;">
                                📅 My Schedule
                            </a>
                            <a href="evaluations.php" class="btn btn-secondary" style="text-align: center;">
                                📝 Submit Evaluation
                            </a>
                            <a href="profile.php" class="btn btn-secondary" style="text-align: center;">
                                ⚙️ Edit Profile
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (!empty($pending_list)): ?>
                <!-- Pending Assignments -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>⏳ Pending Assignments</h3>
                        <a href="assignments.php" class="btn btn-secondary" style="font-size: 0.9rem;">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Group</th>
                                        <th>Thesis Title</th>
                                        <th>Defense Date</th>
                                        <th>Venue</th>
                                        <th>Role</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_list as $assignment): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($assignment['group_name']); ?></td>
                                            <td><?php echo htmlspecialchars($assignment['thesis_title']); ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($assignment['defense_date'] . ' ' . $assignment['defense_time'])); ?></td>
                                            <td><?php echo htmlspecialchars($assignment['venue_name']); ?></td>
                                            <td><span class="badge badge-<?php echo $assignment['role'] === 'chair' ? 'primary' : 'secondary'; ?>"><?php echo ucfirst($assignment['role']); ?></span></td>
                                            <td>
                                                <a href="assignments.php?id=<?php echo $assignment['assignment_id']; ?>" class="btn-icon btn-view" title="View Details">👁️</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
                    <!-- Upcoming Defenses -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3>📅 Upcoming Defenses</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($upcoming_schedules)): ?>
                                <div class="empty-state">
                                    <div class="icon">📅</div>
                                    <h3>No Upcoming Defenses</h3>
                                    <p>You don't have any scheduled defenses at the moment.</p>
                                </div>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; gap: 1rem;">
                                    <?php foreach ($upcoming_schedules as $schedule): ?>
                                        <div style="padding: 1rem; background: var(--bg-color); border-radius: 8px; border-left: 4px solid var(--primary-color);">
                                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                                <strong><?php echo htmlspecialchars($schedule['group_name']); ?></strong>
                                                <span class="badge badge-<?php echo $schedule['role'] === 'chair' ? 'primary' : 'secondary'; ?>">
                                                    <?php echo ucfirst($schedule['role']); ?>
                                                </span>
                                            </div>
                                            <p style="margin: 0.5rem 0; font-size: 0.9rem;">
                                                <?php echo htmlspecialchars($schedule['thesis_title']); ?>
                                            </p>
                                            <div style="font-size: 0.85rem; color: var(--text-secondary);">
                                                📆 <?php echo date('F d, Y', strtotime($schedule['defense_date'])); ?><br>
                                                🕒 <?php echo date('h:i A', strtotime($schedule['defense_time'])); ?><br>
                                                📍 <?php echo htmlspecialchars($schedule['venue_name']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <a href="schedule.php" class="btn btn-secondary" style="margin-top: 1rem; width: 100%;">View Full Schedule</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Notifications -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3>🔔 Recent Notifications</h3>
                            <a href="notifications.php" class="btn btn-secondary" style="font-size: 0.9rem;">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($notifications)): ?>
                                <div class="empty-state">
                                    <div class="icon">🔔</div>
                                    <h3>No Notifications</h3>
                                    <p>You're all caught up!</p>
                                </div>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; gap: 1rem;">
                                    <?php foreach ($notifications as $notif): ?>
                                        <div style="padding: 1rem; background: <?php echo $notif['is_read'] ? 'var(--bg-color)' : '#e0f2fe'; ?>; border-radius: 8px; border-left: 4px solid var(--primary-color);">
                                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                                <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                                                <span style="font-size: 0.8rem; color: var(--text-secondary);">
                                                    <?php echo date('M d, Y', strtotime($notif['created_at'])); ?>
                                                </span>
                                            </div>
                                            <p style="margin: 0; color: var(--text-secondary); font-size: 0.95rem;">
                                                <?php echo htmlspecialchars($notif['message']); ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/icons.js"></script>
</body>
</html>