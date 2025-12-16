<?php
/**
 * Defense Schedule Page
 * View accepted defense schedules
 */

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'panelist') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

// Page title and subtitle
$page_title = "Defense Schedule";
$page_subtitle = "View your accepted thesis defense schedule";

// Get accepted schedules
try {
    $schedules_stmt = $pdo->prepare("
        SELECT ds.*, v.venue_name, v.building, v.room_number, v.capacity,
               pa.role, pa.status as assignment_status,
               tg.group_name, tg.thesis_title, tg.course, tg.specialization,
               COUNT(DISTINCT gm.user_id) as member_count,
               (SELECT COUNT(*) FROM evaluations WHERE schedule_id = ds.schedule_id AND panelist_id = ?) as has_evaluated
        FROM defense_schedules ds
        INNER JOIN panel_assignments pa ON ds.schedule_id = pa.schedule_id
        LEFT JOIN venues v ON ds.venue_id = v.venue_id
        LEFT JOIN thesis_groups tg ON ds.group_id = tg.group_id
        LEFT JOIN group_members gm ON tg.group_id = gm.group_id
        WHERE pa.panelist_id = ? AND pa.status = 'accepted'
        GROUP BY ds.schedule_id
        ORDER BY ds.defense_date ASC, ds.defense_time ASC
    ");
    $schedules_stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $schedules = $schedules_stmt->fetchAll();

    // Separate upcoming and past schedules
    $upcoming_schedules = [];
    $past_schedules = [];
    $today = date('Y-m-d');

    foreach ($schedules as $schedule) {
        if ($schedule['defense_date'] >= $today) {
            $upcoming_schedules[] = $schedule;
        } else {
            $past_schedules[] = $schedule;
        }
    }
} catch (PDOException $e) {
    error_log("Schedules Fetch Error: " . $e->getMessage());
    $upcoming_schedules = [];
    $past_schedules = [];
}

// Get other panelists for each schedule
function getPanelists($pdo, $schedule_id, $current_user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.name, pd.title, pd.specialization, pa.role
            FROM panel_assignments pa
            INNER JOIN users u ON pa.panelist_id = u.user_id
            LEFT JOIN panelist_details pd ON u.user_id = pd.user_id
            WHERE pa.schedule_id = ? AND pa.panelist_id != ? AND pa.status = 'accepted'
            ORDER BY pa.role ASC
        ");
        $stmt->execute([$schedule_id, $current_user_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Defense Schedule - Thesis Panel System</title>
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
                <!-- Stats -->
                <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            📅
                        </div>
                        <div class="stat-info">
                            <h3><?php echo count($upcoming_schedules); ?></h3>
                            <p>Upcoming Defenses</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon success">
                            ✅
                        </div>
                        <div class="stat-info">
                            <h3><?php echo count($past_schedules); ?></h3>
                            <p>Completed Defenses</p>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Schedules -->
                <?php if (!empty($upcoming_schedules)): ?>
                <div class="content-card">
                    <div class="card-header">
                        <h3>📅 Upcoming Defenses</h3>
                    </div>
                    <div class="card-body">
                        <div style="display: grid; gap: 1.5rem;">
                            <?php foreach ($upcoming_schedules as $schedule): ?>
                                <?php $other_panelists = getPanelists($pdo, $schedule['schedule_id'], $_SESSION['user_id']); ?>
                                <div style="border: 2px solid var(--primary-color); padding: 1.5rem; border-radius: 12px; background: white;">
                                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                        <div>
                                            <h3 style="margin-bottom: 0.5rem; color: var(--primary-color);">
                                                <?php echo htmlspecialchars($schedule['group_name']); ?>
                                            </h3>
                                            <p style="margin: 0; color: var(--text-secondary);">
                                                <?php echo htmlspecialchars($schedule['thesis_title']); ?>
                                            </p>
                                        </div>
                                        <div style="text-align: right;">
                                            <span class="badge badge-<?php echo $schedule['role'] === 'chair' ? 'primary' : 'secondary'; ?>">
                                                <?php echo ucfirst($schedule['role']); ?>
                                            </span>
                                            <br>
                                            <span class="badge badge-<?php echo $schedule['status'] === 'completed' ? 'success' : 'primary'; ?>" style="margin-top: 0.5rem;">
                                                <?php echo ucfirst($schedule['status']); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; padding: 1rem; background: var(--bg-color); border-radius: 8px;">
                                        <div>
                                            <strong>📆 Date:</strong>
                                            <p style="font-size: 1.1rem; color: var(--primary-color); font-weight: 600; margin: 0.25rem 0 0 0;">
                                                <?php echo date('l, F d, Y', strtotime($schedule['defense_date'])); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <strong>🕒 Time:</strong>
                                            <p style="font-size: 1.1rem; color: var(--primary-color); font-weight: 600; margin: 0.25rem 0 0 0;">
                                                <?php echo date('h:i A', strtotime($schedule['defense_time'])); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <strong>⏱️ Duration:</strong>
                                            <p style="margin: 0.25rem 0 0 0;">
                                                <?php echo $schedule['duration_minutes']; ?> minutes
                                            </p>
                                        </div>
                                    </div>

                                    <div style="padding: 1rem; background: #e0f2fe; border-radius: 8px; margin-bottom: 1rem;">
                                        <strong>📍 Venue:</strong>
                                        <p style="margin: 0.5rem 0 0 0;">
                                            <strong><?php echo htmlspecialchars($schedule['venue_name']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($schedule['building'] . ' - Room ' . $schedule['room_number']); ?></small><br>
                                            <small>Capacity: <?php echo $schedule['capacity']; ?> people</small>
                                        </p>
                                    </div>

                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                                        <div>
                                            <strong>Course:</strong>
                                            <p style="margin: 0.25rem 0 0 0;"><?php echo htmlspecialchars($schedule['course']); ?></p>
                                        </div>
                                        <div>
                                            <strong>Specialization:</strong>
                                            <p style="margin: 0.25rem 0 0 0;"><?php echo htmlspecialchars($schedule['specialization']); ?></p>
                                        </div>
                                        <div>
                                            <strong>Group Members:</strong>
                                            <p style="margin: 0.25rem 0 0 0;"><?php echo $schedule['member_count']; ?> member(s)</p>
                                        </div>
                                    </div>

                                    <?php if (!empty($other_panelists)): ?>
                                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 2px solid var(--border-color);">
                                            <strong>👨‍🏫 Other Panel Members:</strong>
                                            <div style="margin-top: 0.5rem;">
                                                <?php foreach ($other_panelists as $panelist): ?>
                                                    <div style="padding: 0.5rem; background: white; border: 1px solid var(--border-color); border-radius: 6px; margin-bottom: 0.5rem;">
                                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($panelist['name']); ?></strong>
                                                                <?php if ($panelist['title']): ?>
                                                                    <small style="color: var(--text-secondary);"> - <?php echo htmlspecialchars($panelist['title']); ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                            <span class="badge badge-<?php echo $panelist['role'] === 'chair' ? 'primary' : 'secondary'; ?>">
                                                                <?php echo ucfirst($panelist['role']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                                        <a href="documents.php?schedule_id=<?php echo $schedule['schedule_id']; ?>" class="btn btn-secondary" style="flex: 1;">
                                            📄 View Documents
                                        </a>
                                        <?php if ($schedule['status'] === 'completed' && $schedule['has_evaluated'] == 0): ?>
                                            <a href="evaluations.php?schedule_id=<?php echo $schedule['schedule_id']; ?>" class="btn btn-primary" style="flex: 1;">
                                                📝 Submit Evaluation
                                            </a>
                                        <?php elseif ($schedule['has_evaluated'] > 0): ?>
                                            <a href="evaluations.php?schedule_id=<?php echo $schedule['schedule_id']; ?>" class="btn btn-success" style="flex: 1;">
                                                ✅ View Evaluation
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="content-card">
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="icon">📅</div>
                            <h3>No Upcoming Defenses</h3>
                            <p>You don't have any scheduled defenses at the moment.</p>
                            <a href="assignments.php" class="btn btn-secondary">View Assignments</a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Past Schedules -->
                <?php if (!empty($past_schedules)): ?>
                <div class="content-card" style="margin-top: 2rem;">
                    <div class="card-header">
                        <h3>✅ Past Defenses</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Group</th>
                                        <th>Thesis Title</th>
                                        <th>Date</th>
                                        <th>Venue</th>
                                        <th>Role</th>
                                        <th>Evaluated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($past_schedules as $schedule): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($schedule['group_name']); ?></td>
                                            <td><?php echo htmlspecialchars($schedule['thesis_title']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($schedule['defense_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($schedule['venue_name']); ?></td>
                                            <td><span class="badge badge-<?php echo $schedule['role'] === 'chair' ? 'primary' : 'secondary'; ?>"><?php echo ucfirst($schedule['role']); ?></span></td>
                                            <td>
                                                <?php if ($schedule['has_evaluated'] > 0): ?>
                                                    <span class="badge badge-success">✅ Yes</span>
                                                <?php else: ?>
                                                    <span class="badge badge-warning">⏳ No</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="evaluations.php?schedule_id=<?php echo $schedule['schedule_id']; ?>" class="btn-icon btn-view" title="View/Submit Evaluation">📝</a>
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
    <script src="../assets/js/icons.js"></script>
</body>
</html>