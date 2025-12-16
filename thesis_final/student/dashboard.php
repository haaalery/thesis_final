<?php
/**
 * Student Dashboard
 * Main overview page for student users
 */

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

// Page title and subtitle
$page_title = "Dashboard";
$page_subtitle = "Overview of your thesis progress";

// Get student details
try {
    $user_stmt = $pdo->prepare("
        SELECT u.*, sd.course, sd.year, sd.student_id 
        FROM users u 
        LEFT JOIN student_details sd ON u.user_id = sd.user_id 
        WHERE u.user_id = ?
    ");
    $user_stmt->execute([$_SESSION['user_id']]);
    $student = $user_stmt->fetch();

    // Get or create student details if not exists
    if (!$student['course']) {
        $create_details = $pdo->prepare("
            INSERT INTO student_details (user_id, course, year) 
            VALUES (?, 'Not Set', 'Not Set')
        ");
        $create_details->execute([$_SESSION['user_id']]);
        $student['course'] = 'Not Set';
        $student['year'] = 'Not Set';
    }
} catch (PDOException $e) {
    error_log("Student Details Error: " . $e->getMessage());
    die("Error loading student information.");
}

// Get thesis group information
try {
    $group_stmt = $pdo->prepare("
        SELECT tg.*, 
               COUNT(DISTINCT gm.member_id) as member_count,
               (SELECT COUNT(*) FROM thesis_documents WHERE group_id = tg.group_id) as document_count
        FROM thesis_groups tg
        INNER JOIN group_members gm ON tg.group_id = gm.group_id
        WHERE gm.user_id = ?
        GROUP BY tg.group_id
    ");
    $group_stmt->execute([$_SESSION['user_id']]);
    $thesis_group = $group_stmt->fetch();
} catch (PDOException $e) {
    error_log("Thesis Group Error: " . $e->getMessage());
    $thesis_group = null;
}

// Get defense schedule if exists
$defense_schedule = null;
if ($thesis_group) {
    try {
        $schedule_stmt = $pdo->prepare("
            SELECT ds.*, v.venue_name, v.building, v.room_number,
                   COUNT(DISTINCT pa.panelist_id) as panelist_count
            FROM defense_schedules ds
            LEFT JOIN venues v ON ds.venue_id = v.venue_id
            LEFT JOIN panel_assignments pa ON ds.schedule_id = pa.schedule_id
            WHERE ds.group_id = ? AND ds.status IN ('approved', 'completed')
            GROUP BY ds.schedule_id
            ORDER BY ds.defense_date DESC, ds.defense_time DESC
            LIMIT 1
        ");
        $schedule_stmt->execute([$thesis_group['group_id']]);
        $defense_schedule = $schedule_stmt->fetch();
    } catch (PDOException $e) {
        error_log("Schedule Error: " . $e->getMessage());
    }
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

// Get evaluation status if defense completed
$evaluation_summary = null;
if ($defense_schedule && $defense_schedule['status'] === 'completed') {
    try {
        $eval_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_evaluations,
                AVG(score) as average_score,
                SUM(CASE WHEN verdict = 'passed' THEN 1 ELSE 0 END) as passed_count,
                SUM(CASE WHEN verdict = 'revisions_required' THEN 1 ELSE 0 END) as revision_count,
                SUM(CASE WHEN verdict = 'failed' THEN 1 ELSE 0 END) as failed_count
            FROM evaluations
            WHERE schedule_id = ? AND group_id = ?
        ");
        $eval_stmt->execute([$defense_schedule['schedule_id'], $thesis_group['group_id']]);
        $evaluation_summary = $eval_stmt->fetch();
    } catch (PDOException $e) {
        error_log("Evaluation Summary Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Thesis Panel System</title>
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
                            👥
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $thesis_group ? 'Group Created' : 'No Group'; ?></h3>
                            <p><?php echo $thesis_group ? htmlspecialchars($thesis_group['group_name']) : 'Create your thesis group'; ?></p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon <?php echo $thesis_group && $thesis_group['status'] === 'approved' ? 'success' : 'warning'; ?>">
                            <?php echo $thesis_group && $thesis_group['status'] === 'approved' ? '✅' : '⏳'; ?>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $thesis_group ? ucfirst(str_replace('_', ' ', $thesis_group['status'])) : 'No Status'; ?></h3>
                            <p>Thesis Status</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon success">
                            📄
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $thesis_group ? $thesis_group['document_count'] : '0'; ?></h3>
                            <p>Documents Uploaded</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon <?php echo $defense_schedule ? 'primary' : 'secondary'; ?>">
                            📅
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $defense_schedule ? 'Scheduled' : 'Not Scheduled'; ?></h3>
                            <p>Defense Status</p>
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
                            <?php if (!$thesis_group): ?>
                                <a href="thesis-group.php" class="btn btn-primary" style="text-align: center;">
                                    👥 Create Thesis Group
                                </a>
                            <?php else: ?>
                                <a href="thesis-group.php" class="btn btn-secondary" style="text-align: center;">
                                    👥 View Thesis Group
                                </a>
                                <a href="documents.php" class="btn btn-primary" style="text-align: center;">
                                    📄 Upload Documents
                                </a>
                                <?php if ($thesis_group['status'] === 'approved' && !$defense_schedule): ?>
                                    <a href="schedule.php" class="btn btn-primary" style="text-align: center;">
                                        📅 Request Schedule
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                            <a href="profile.php" class="btn btn-secondary" style="text-align: center;">
                                ⚙️ Edit Profile
                            </a>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
                    <!-- Thesis Information -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3>📚 Thesis Information</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($thesis_group): ?>
                                <div style="margin-bottom: 1rem;">
                                    <strong>Group Name:</strong>
                                    <p><?php echo htmlspecialchars($thesis_group['group_name']); ?></p>
                                </div>
                                <div style="margin-bottom: 1rem;">
                                    <strong>Title:</strong>
                                    <p><?php echo $thesis_group['thesis_title'] ? htmlspecialchars($thesis_group['thesis_title']) : '<em>Not set</em>'; ?></p>
                                </div>
                                <div style="margin-bottom: 1rem;">
                                    <strong>Course:</strong>
                                    <p><?php echo htmlspecialchars($thesis_group['course']); ?></p>
                                </div>
                                <div style="margin-bottom: 1rem;">
                                    <strong>Members:</strong>
                                    <p><?php echo $thesis_group['member_count']; ?> member(s)</p>
                                </div>
                                <div>
                                    <span class="badge badge-<?php 
                                        echo $thesis_group['status'] === 'approved' ? 'success' : 
                                             ($thesis_group['status'] === 'rejected' ? 'danger' : 'warning'); 
                                    ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $thesis_group['status'])); ?>
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="empty-state" style="padding: 2rem 1rem;">
                                    <div class="icon">👥</div>
                                    <h3>No Thesis Group Yet</h3>
                                    <p>Create your thesis group to get started</p>
                                    <a href="thesis-group.php" class="btn btn-primary">Create Group</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Defense Schedule -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3>📅 Defense Schedule</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($defense_schedule): ?>
                                <div style="margin-bottom: 1rem;">
                                    <strong>Date:</strong>
                                    <p><?php echo date('F d, Y', strtotime($defense_schedule['defense_date'])); ?></p>
                                </div>
                                <div style="margin-bottom: 1rem;">
                                    <strong>Time:</strong>
                                    <p><?php echo date('h:i A', strtotime($defense_schedule['defense_time'])); ?></p>
                                </div>
                                <div style="margin-bottom: 1rem;">
                                    <strong>Venue:</strong>
                                    <p><?php echo htmlspecialchars($defense_schedule['venue_name']); ?><br>
                                    <small><?php echo htmlspecialchars($defense_schedule['building'] . ' - ' . $defense_schedule['room_number']); ?></small></p>
                                </div>
                                <div style="margin-bottom: 1rem;">
                                    <strong>Panelists:</strong>
                                    <p><?php echo $defense_schedule['panelist_count']; ?> assigned</p>
                                </div>
                                <div>
                                    <span class="badge badge-<?php echo $defense_schedule['status'] === 'completed' ? 'success' : 'primary'; ?>">
                                        <?php echo ucfirst($defense_schedule['status']); ?>
                                    </span>
                                </div>
                                <?php if ($evaluation_summary && $evaluation_summary['total_evaluations'] > 0): ?>
                                    <hr style="margin: 1rem 0;">
                                    <div>
                                        <strong>Evaluation Summary:</strong>
                                        <p>Average Score: <strong><?php echo number_format($evaluation_summary['average_score'], 2); ?></strong></p>
                                        <p style="font-size: 0.9rem;">
                                            ✅ Passed: <?php echo $evaluation_summary['passed_count']; ?> | 
                                            ⚠️ Revisions: <?php echo $evaluation_summary['revision_count']; ?> | 
                                            ❌ Failed: <?php echo $evaluation_summary['failed_count']; ?>
                                        </p>
                                        <a href="evaluation.php" class="btn btn-secondary" style="margin-top: 0.5rem; font-size: 0.9rem;">View Details</a>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="empty-state" style="padding: 2rem 1rem;">
                                    <div class="icon">📅</div>
                                    <h3>No Schedule Yet</h3>
                                    <p><?php echo $thesis_group && $thesis_group['status'] === 'approved' ? 'Request a defense schedule' : 'Thesis must be approved first'; ?></p>
                                    <?php if ($thesis_group && $thesis_group['status'] === 'approved'): ?>
                                        <a href="schedule.php" class="btn btn-primary">Request Schedule</a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Notifications -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>🔔 Recent Notifications</h3>
                        <a href="notifications.php" class="btn btn-secondary" style="font-size: 0.9rem;">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($notifications)): ?>
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <?php foreach ($notifications as $notif): ?>
                                    <div style="padding: 1rem; background: <?php echo $notif['is_read'] ? 'var(--bg-color)' : '#e0f2fe'; ?>; border-radius: 8px; border-left: 4px solid var(--primary-color);">
                                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                            <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($notif['title']); ?></strong>
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
                        <?php else: ?>
                            <div class="empty-state" style="padding: 2rem 1rem;">
                                <div class="icon">🔔</div>
                                <h3>No Notifications</h3>
                                <p>You're all caught up!</p>
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