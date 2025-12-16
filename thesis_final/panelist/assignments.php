<?php
/**
 * Panel Assignments Page
 * View and respond to panel assignments
 */

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'panelist') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

// Page title and subtitle
$page_title = "Panel Assignments";
$page_subtitle = "View and manage your thesis panel assignments";

// Initialize variables
$errors = [];
$success = '';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle assignment response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid request.";
    } else {
        $action = $_POST['action'];
        $assignment_id = intval($_POST['assignment_id']);

        if ($action === 'accept' || $action === 'decline') {
            try {
                $status = $action === 'accept' ? 'accepted' : 'declined';
                
                $update_stmt = $pdo->prepare("
                    UPDATE panel_assignments 
                    SET status = ?, responded_at = NOW() 
                    WHERE assignment_id = ? AND panelist_id = ?
                ");
                $update_stmt->execute([$status, $assignment_id, $_SESSION['user_id']]);

                // Log activity
                $log_stmt = $pdo->prepare("
                    INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, created_at) 
                    VALUES (?, ?, 'panel_assignments', ?, ?, NOW())
                ");
                $log_stmt->execute([$_SESSION['user_id'], $action . '_assignment', $assignment_id, $_SERVER['REMOTE_ADDR']]);

                $success = "Assignment " . ($action === 'accept' ? 'accepted' : 'declined') . " successfully!";
            } catch (PDOException $e) {
                error_log("Assignment Response Error: " . $e->getMessage());
                $errors[] = "An error occurred. Please try again.";
            }
        }
    }
}

// Get filter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query
$where_clauses = ["pa.panelist_id = ?"];
$params = [$_SESSION['user_id']];

if ($filter === 'pending') {
    $where_clauses[] = "pa.status = 'pending'";
} elseif ($filter === 'accepted') {
    $where_clauses[] = "pa.status = 'accepted'";
} elseif ($filter === 'declined') {
    $where_clauses[] = "pa.status = 'declined'";
}

$where_sql = implode(' AND ', $where_clauses);

// Get assignments
try {
    $stmt = $pdo->prepare("
        SELECT pa.*, ds.defense_date, ds.defense_time, ds.status as schedule_status,
               v.venue_name, v.building, v.room_number,
               tg.group_name, tg.thesis_title, tg.abstract,
               COUNT(DISTINCT gm.user_id) as member_count
        FROM panel_assignments pa
        INNER JOIN defense_schedules ds ON pa.schedule_id = ds.schedule_id
        LEFT JOIN venues v ON ds.venue_id = v.venue_id
        LEFT JOIN thesis_groups tg ON ds.group_id = tg.group_id
        LEFT JOIN group_members gm ON tg.group_id = gm.group_id
        WHERE $where_sql
        GROUP BY pa.assignment_id
        ORDER BY ds.defense_date ASC, ds.defense_time ASC
    ");
    $stmt->execute($params);
    $assignments = $stmt->fetchAll();

    // Get counts
    $count_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
            SUM(CASE WHEN status = 'declined' THEN 1 ELSE 0 END) as declined
        FROM panel_assignments 
        WHERE panelist_id = ?
    ");
    $count_stmt->execute([$_SESSION['user_id']]);
    $counts = $count_stmt->fetch();
} catch (PDOException $e) {
    error_log("Assignments Fetch Error: " . $e->getMessage());
    $assignments = [];
    $counts = ['total' => 0, 'pending' => 0, 'accepted' => 0, 'declined' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Assignments - Thesis Panel System</title>
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
                    <div class="alert alert-error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            📋
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $counts['total']; ?></h3>
                            <p>Total Assignments</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon warning">
                            ⏳
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $counts['pending']; ?></h3>
                            <p>Pending</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon success">
                            ✅
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $counts['accepted']; ?></h3>
                            <p>Accepted</p>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon danger">
                            ❌
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $counts['declined']; ?></h3>
                            <p>Declined</p>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="content-card">
                    <div class="card-body">
                        <div class="filter-group">
                            <label>Filter:</label>
                            <select onchange="window.location.href='assignments.php?filter=' + this.value">
                                <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Assignments</option>
                                <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending Only</option>
                                <option value="accepted" <?php echo $filter === 'accepted' ? 'selected' : ''; ?>>Accepted Only</option>
                                <option value="declined" <?php echo $filter === 'declined' ? 'selected' : ''; ?>>Declined Only</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Assignments List -->
                <div class="content-card">
                    <div class="card-header">
                        <h3>📋 Panel Assignments</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($assignments)): ?>
                            <div class="empty-state">
                                <div class="icon">📋</div>
                                <h3>No Assignments</h3>
                                <p><?php echo $filter === 'pending' ? 'You have no pending assignments.' : 'No assignments found.'; ?></p>
                            </div>
                        <?php else: ?>
                            <div style="display: grid; gap: 1.5rem;">
                                <?php foreach ($assignments as $assignment): ?>
                                    <div class="content-card" style="margin: 0; border: 2px solid var(--border-color);">
                                        <div class="card-header">
                                            <div>
                                                <h3 style="margin-bottom: 0.25rem;">
                                                    <?php echo htmlspecialchars($assignment['group_name']); ?>
                                                </h3>
                                                <p style="margin: 0; font-size: 0.9rem; color: var(--text-secondary); font-weight: normal;">
                                                    <?php echo htmlspecialchars($assignment['thesis_title']); ?>
                                                </p>
                                            </div>
                                            <div style="text-align: right;">
                                                <span class="badge badge-<?php echo $assignment['role'] === 'chair' ? 'primary' : 'secondary'; ?>">
                                                    <?php echo ucfirst($assignment['role']); ?>
                                                </span>
                                                <br>
                                                <span class="badge badge-<?php 
                                                    echo $assignment['status'] === 'accepted' ? 'success' : 
                                                         ($assignment['status'] === 'declined' ? 'danger' : 'warning'); 
                                                ?>" style="margin-top: 0.5rem;">
                                                    <?php echo ucfirst($assignment['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                                                <div>
                                                    <strong>📆 Defense Date:</strong>
                                                    <p><?php echo date('F d, Y', strtotime($assignment['defense_date'])); ?></p>
                                                </div>
                                                <div>
                                                    <strong>🕒 Time:</strong>
                                                    <p><?php echo date('h:i A', strtotime($assignment['defense_time'])); ?></p>
                                                </div>
                                                <div>
                                                    <strong>📍 Venue:</strong>
                                                    <p><?php echo htmlspecialchars($assignment['venue_name']); ?><br>
                                                    <small><?php echo htmlspecialchars($assignment['building'] . ' - ' . $assignment['room_number']); ?></small></p>
                                                </div>
                                                <div>
                                                    <strong>👥 Group Size:</strong>
                                                    <p><?php echo $assignment['member_count']; ?> member(s)</p>
                                                </div>
                                            </div>

                                            <?php if ($assignment['abstract']): ?>
                                                <div style="margin-top: 1rem; padding-top: 1rem; border-top: 2px solid var(--border-color);">
                                                    <strong>Abstract:</strong>
                                                    <p style="margin-top: 0.5rem; color: var(--text-secondary);">
                                                        <?php echo nl2br(htmlspecialchars($assignment['abstract'])); ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($assignment['responded_at']): ?>
                                                <div style="margin-top: 1rem; padding: 0.75rem; background: var(--bg-color); border-radius: 8px;">
                                                    <small style="color: var(--text-secondary);">
                                                        <strong>Responded:</strong> <?php echo date('F d, Y h:i A', strtotime($assignment['responded_at'])); ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($assignment['status'] === 'pending'): ?>
                                                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 2px solid var(--border-color); display: flex; gap: 1rem;">
                                                    <form method="POST" action="assignments.php" style="flex: 1;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="action" value="accept">
                                                        <input type="hidden" name="assignment_id" value="<?php echo $assignment['assignment_id']; ?>">
                                                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                                                            ✅ Accept Assignment
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="assignments.php" style="flex: 1;" onsubmit="return confirm('Are you sure you want to decline this assignment?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                        <input type="hidden" name="action" value="decline">
                                                        <input type="hidden" name="assignment_id" value="<?php echo $assignment['assignment_id']; ?>">
                                                        <button type="submit" class="btn btn-secondary" style="width: 100%;">
                                                            ❌ Decline Assignment
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php elseif ($assignment['status'] === 'accepted'): ?>
                                                <div style="margin-top: 1rem;">
                                                    <a href="documents.php?group_id=<?php echo $assignment['schedule_id']; ?>" class="btn btn-secondary">
                                                        📄 View Thesis Documents
                                                    </a>
                                                </div>
                                            <?php endif; ?>
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