<?php
// ============================================
// FILE: thesis_final/admin/reports.php
// ============================================
?>
<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../db_connect.php';
$page_title = "Reports";
$page_subtitle = "System reports and analytics";

try {
    $stats = [
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn(),
        'total_groups' => $pdo->query("SELECT COUNT(*) FROM thesis_groups")->fetchColumn(),
        'approved_groups' => $pdo->query("SELECT COUNT(*) FROM thesis_groups WHERE status = 'approved'")->fetchColumn(),
        'total_schedules' => $pdo->query("SELECT COUNT(*) FROM defense_schedules")->fetchColumn(),
        'completed_defenses' => $pdo->query("SELECT COUNT(*) FROM defense_schedules WHERE status = 'completed'")->fetchColumn(),
        'total_evaluations' => $pdo->query("SELECT COUNT(*) FROM evaluations")->fetchColumn(),
    ];

    $recent_defenses = $pdo->query("SELECT ds.*, tg.group_name, v.venue_name, COUNT(DISTINCT pa.panelist_id) as panelist_count FROM defense_schedules ds LEFT JOIN thesis_groups tg ON ds.group_id = tg.group_id LEFT JOIN venues v ON ds.venue_id = v.venue_id LEFT JOIN panel_assignments pa ON ds.schedule_id = pa.schedule_id WHERE ds.status = 'completed' GROUP BY ds.schedule_id ORDER BY ds.defense_date DESC LIMIT 10")->fetchAll();

    $course_distribution = $pdo->query("SELECT course, COUNT(*) as count FROM thesis_groups WHERE status = 'approved' GROUP BY course")->fetchAll();
} catch (PDOException $e) {
    $stats = [];
    $recent_defenses = [];
    $course_distribution = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin</title>
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
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-icon primary">👥</div><div class="stat-info"><h3><?php echo $stats['total_users']; ?></h3><p>Active Users</p></div></div>
                    <div class="stat-card"><div class="stat-icon success">📚</div><div class="stat-info"><h3><?php echo $stats['approved_groups']; ?></h3><p>Approved Groups</p></div></div>
                    <div class="stat-card"><div class="stat-icon warning">📅</div><div class="stat-info"><h3><?php echo $stats['total_schedules']; ?></h3><p>Total Schedules</p></div></div>
                    <div class="stat-card"><div class="stat-icon success">✅</div><div class="stat-info"><h3><?php echo $stats['completed_defenses']; ?></h3><p>Completed Defenses</p></div></div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
                    <div class="content-card">
                        <div class="card-header"><h3>📊 Course Distribution</h3></div>
                        <div class="card-body">
                            <?php foreach ($course_distribution as $cd): ?>
                                <div style="margin-bottom: 1rem;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                                        <span><?php echo htmlspecialchars($cd['course']); ?></span>
                                        <strong><?php echo $cd['count']; ?></strong>
                                    </div>
                                    <div style="background: var(--bg-color); height: 8px; border-radius: 4px; overflow: hidden;">
                                        <div style="background: var(--primary-color); height: 100%; width: <?php echo ($cd['count'] / max(1, $stats['approved_groups'])) * 100; ?>%;"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="content-card">
                        <div class="card-header"><h3>📈 System Overview</h3></div>
                        <div class="card-body">
                            <div style="padding: 1rem; background: var(--bg-color); border-radius: 8px; margin-bottom: 1rem;">
                                <strong>Total Groups:</strong> <?php echo $stats['total_groups']; ?><br>
                                <strong>Total Evaluations:</strong> <?php echo $stats['total_evaluations']; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="content-card">
                    <div class="card-header"><h3>📅 Recent Completed Defenses</h3></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead><tr><th>Group</th><th>Date</th><th>Venue</th><th>Panelists</th></tr></thead>
                                <tbody>
                                    <?php foreach ($recent_defenses as $rd): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($rd['group_name']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($rd['defense_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($rd['venue_name']); ?></td>
                                            <td><?php echo $rd['panelist_count']; ?></td>
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
    <script src="../assets/js/icons.js"></script>
</body>
</html>