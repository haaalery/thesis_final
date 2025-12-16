<?php
/**
 * Admin Dashboard
 * Main overview page for admin users
 */

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

// Page title and subtitle
$page_title = "Dashboard";
$page_subtitle = "System overview and statistics";

// Get statistics
try {
    // Total users by role
    $users_stmt = $pdo->query("
        SELECT role, COUNT(*) as count 
        FROM users 
        WHERE status = 'active' 
        GROUP BY role
    ");
    $user_counts = $users_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $total_students = $user_counts['student'] ?? 0;
    $total_panelists = $user_counts['panelist'] ?? 0;
    $total_admins = $user_counts['admin'] ?? 0;

    // Thesis groups statistics
    $groups_stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM thesis_groups 
        GROUP BY status
    ");
    $group_counts = $groups_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $pending_groups = ($group_counts['pending'] ?? 0) + ($group_counts['pending_approval'] ?? 0);
    $approved_groups = $group_counts['approved'] ?? 0;
    $rejected_groups = $group_counts['rejected'] ?? 0;

    // Schedule statistics
    $schedules_stmt = $pdo->query("
        SELECT status, COUNT(*) as count 
        FROM defense_schedules 
        GROUP BY status
    ");
    $schedule_counts = $schedules_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $available_slots = $schedule_counts['available'] ?? 0;
    $requested_slots = $schedule_counts['requested'] ?? 0;
    $approved_schedules = $schedule_counts['approved'] ?? 0;
    $completed_schedules = $schedule_counts['completed'] ?? 0;

    // Recent activity
    $activity_stmt = $pdo->prepare("
        SELECT al.*, u.name, u.role 
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $activity_stmt->execute();
    $recent_activity = $activity_stmt->fetchAll();

    // Pending schedule requests
    $requests_stmt = $pdo->prepare("
        SELECT sr.*, tg.group_name, ds.defense_date, ds.defense_time, v.venue_name
        FROM schedule_requests sr
        INNER JOIN thesis_groups tg ON sr.group_id = tg.group_id
        INNER JOIN defense_schedules ds ON sr.requested_schedule_id = ds.schedule_id
        LEFT JOIN venues v ON ds.venue_id = v.venue_id
        WHERE sr.status = 'pending'
        ORDER BY sr.requested_at DESC
        LIMIT 5
    ");
    $requests_stmt->execute();
    $pending_requests = $requests_stmt->fetchAll();

    // Upcoming defenses
    $upcoming_stmt = $pdo->prepare("
        SELECT ds.*, tg.group_name, v.venue_name,
               COUNT(DISTINCT pa.panelist_id) as panelist_count
        FROM defense_schedules ds
        LEFT JOIN thesis_groups tg ON ds.group_id = tg.group_id
        LEFT JOIN venues v ON ds.venue_id = v.venue_id
        LEFT JOIN panel_assignments pa ON ds.schedule_id = pa.schedule_id
        WHERE ds.status = 'approved' AND ds.defense_date >= CURDATE()
        GROUP BY ds.schedule_id
        ORDER BY ds.defense_date ASC, ds.defense_time ASC
        LIMIT 5
    ");
    $upcoming_stmt->execute();
    $upcoming_defenses = $upcoming_stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard Stats Error: " . $e->getMessage());
    $total_students = $total_panelists = $total_admins = 0;
    $pending_groups = $approved_groups = $rejected_groups = 0;
    $available_slots = $requested_slots = $approved_schedules = $completed_schedules = 0;
    $recent_activity = $pending_requests = $upcoming_defenses = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Thesis Panel System</title>
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
                <!-- User Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            👨‍🎓
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_students; ?></h3>
                            <p>Active Students</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon success">
                            👨‍🏫
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $total_panelists; ?></h3>
                            <p>Active Panelists</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon warning">
                            ⏳
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $pending_groups; ?></h3>
                            <p>Pending Approvals</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon danger">
                            📋
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $requested_slots; ?></h3>
                            <p>Schedule Requests</p>
                        </div>
                    </div>
                </div>

                <!-- Schedule Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            📅
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $available_slots; ?></h3>
                            <p>Available Slots</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon success">
                            ✅
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $approved_schedules; ?></h3>
                            <p>Approved Schedules</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon success">
                            ✔️
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $completed_schedules; ?></h3>
                            <p>Completed Defenses</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon primary">
                            📚
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $approved_groups; ?></h3>
                            <p>Approved Groups</p>
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
                            <a href="manage-users.php" class="btn btn-primary" style="text-align: center;">
                                👥 Manage Users
                            </a>
                            <a href="manage-thesis.php" class="btn btn-secondary" style="text-align: center;">
                                📚 Review Thesis
                            </a>
                            <a href="manage-schedules.php" class="btn btn-secondary" style="text-align: center;">
                                📅 Manage Schedules
                            </a>
                            <a href="manage-venues.php" class="btn btn-secondary" style="text-align: center;">
                                📍 Manage Venues
                            </a>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
                    <!-- Pending Schedule Requests -->
                    <?php if (!empty($pending_requests)): ?>
                    <div class="content-card">
                        <div class="card-header">
                            <h3>⏳ Pending Schedule Requests</h3>
                            <a href="schedule-requests.php" class="btn btn-secondary" style="font-size: 0.9rem;">View All</a>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <?php foreach ($pending_requests as $request): ?>
                                    <div style="padding: 1rem; background: var(--bg-color); border-radius: 8px; border-left: 4px solid var(--warning-color);">
                                        <strong><?php echo htmlspecialchars($request['group_name']); ?></strong><br>
                                        <small style="color: var(--text-secondary);">
                                            <?php echo date('M d, Y h:i A', strtotime($request['defense_date'] . ' ' . $request['defense_time'])); ?><br>
                                            <?php echo htmlspecialchars($request['venue_name']); ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Upcoming Defenses -->
                    <?php if (!empty($upcoming_defenses)): ?>
                    <div class="content-card">
                        <div class="card-header">
                            <h3>📅 Upcoming Defenses</h3>
                            <a href="manage-schedules.php" class="btn btn-secondary" style="font-size: 0.9rem;">View All</a>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <?php foreach ($upcoming_defenses as $defense): ?>
                                    <div style="padding: 1rem; background: var(--bg-color); border-radius: 8px; border-left: 4px solid var(--primary-color);">
                                        <strong><?php echo htmlspecialchars($defense['group_name']); ?></strong><br>
                                        <small style="color: var(--text-secondary);">
                                            <?php echo date('M d, Y h:i A', strtotime($defense['defense_date'] . ' ' . $defense['defense_time'])); ?><br>
                                            <?php echo htmlspecialchars($defense['venue_name']); ?> | <?php echo $defense['panelist_count']; ?> panelist(s)
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity -->
                <?php if (!empty($recent_activity)): ?>
                <div class="content-card" style="margin-top: 1.5rem;">
                    <div class="card-header">
                        <h3>📊 Recent Activity</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Table</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($activity['name'] ?? 'System'); ?>
                                                <br><small class="badge badge-secondary"><?php echo ucfirst($activity['role'] ?? 'system'); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars(str_replace('_', ' ', $activity['action'])); ?></td>
                                            <td><?php echo htmlspecialchars($activity['table_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></td>
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
    <script src="../assets/js/icons.js"></script>
</body>
</html>