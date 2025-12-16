<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'panelist') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';
$page_title = "Thesis Documents";
$page_subtitle = "View thesis documents from your assigned groups";

$schedule_id = isset($_GET['schedule_id']) ? intval($_GET['schedule_id']) : 0;

$documents = [];
$group_info = null;

if ($schedule_id) {
    try {
        $group_stmt = $pdo->prepare("
            SELECT tg.*, ds.defense_date, ds.defense_time
            FROM defense_schedules ds
            INNER JOIN panel_assignments pa ON ds.schedule_id = pa.schedule_id
            INNER JOIN thesis_groups tg ON ds.group_id = tg.group_id
            WHERE ds.schedule_id = ? AND pa.panelist_id = ?
        ");
        $group_stmt->execute([$schedule_id, $_SESSION['user_id']]);
        $group_info = $group_stmt->fetch();

        if ($group_info) {
            $docs_stmt = $pdo->prepare("
                SELECT td.*, u.name as uploaded_by_name
                FROM thesis_documents td
                INNER JOIN users u ON td.uploaded_by = u.user_id
                WHERE td.group_id = ?
                ORDER BY td.uploaded_at DESC
            ");
            $docs_stmt->execute([$group_info['group_id']]);
            $documents = $docs_stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Documents Fetch Error: " . $e->getMessage());
    }
}

// Get all assigned groups
try {
    $groups_stmt = $pdo->prepare("
        SELECT DISTINCT ds.schedule_id, tg.group_name, ds.defense_date
        FROM defense_schedules ds
        INNER JOIN panel_assignments pa ON ds.schedule_id = pa.schedule_id
        INNER JOIN thesis_groups tg ON ds.group_id = tg.group_id
        WHERE pa.panelist_id = ? AND pa.status = 'accepted'
        ORDER BY ds.defense_date DESC
    ");
    $groups_stmt->execute([$_SESSION['user_id']]);
    $assigned_groups = $groups_stmt->fetchAll();
} catch (PDOException $e) {
    $assigned_groups = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thesis Documents - Thesis Panel System</title>
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
                <?php if (!empty($assigned_groups)): ?>
                <div class="content-card">
                    <div class="card-header"><h3>📁 Select Group</h3></div>
                    <div class="card-body">
                        <div class="filter-group">
                            <label>Group:</label>
                            <select onchange="window.location.href='documents.php?schedule_id=' + this.value">
                                <option value="">Select a group...</option>
                                <?php foreach ($assigned_groups as $group): ?>
                                    <option value="<?php echo $group['schedule_id']; ?>" <?php echo $schedule_id == $group['schedule_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($group['group_name']) . ' - ' . date('M d, Y', strtotime($group['defense_date'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($group_info): ?>
                <div class="content-card">
                    <div class="card-header">
                        <h3>📄 Documents - <?php echo htmlspecialchars($group_info['group_name']); ?></h3>
                    </div>
                    <div class="card-body">
                        <div style="padding: 1rem; background: var(--bg-color); border-radius: 8px; margin-bottom: 1rem;">
                            <p><strong>Thesis Title:</strong> <?php echo htmlspecialchars($group_info['thesis_title']); ?></p>
                            <p><strong>Defense Date:</strong> <?php echo date('F d, Y h:i A', strtotime($group_info['defense_date'] . ' ' . $group_info['defense_time'])); ?></p>
                        </div>

                        <?php if (empty($documents)): ?>
                            <div class="empty-state">
                                <div class="icon">📄</div>
                                <h3>No Documents</h3>
                                <p>This group hasn't uploaded any documents yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Document Type</th>
                                            <th>File Name</th>
                                            <th>Size</th>
                                            <th>Uploaded By</th>
                                            <th>Upload Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($documents as $doc): ?>
                                            <tr>
                                                <td><span class="badge badge-primary"><?php echo htmlspecialchars($doc['document_type']); ?></span></td>
                                                <td><?php echo htmlspecialchars($doc['file_name']); ?></td>
                                                <td><?php echo number_format($doc['file_size'] / 1024, 2); ?> KB</td>
                                                <td><?php echo htmlspecialchars($doc['uploaded_by_name']); ?></td>
                                                <td><?php echo date('M d, Y H:i', strtotime($doc['uploaded_at'])); ?></td>
                                                <td><a href="download-document.php?id=<?php echo $doc['document_id']; ?>" class="btn-icon btn-view" title="Download">⬇️</a></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php elseif (empty($assigned_groups)): ?>
                <div class="content-card">
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="icon">📁</div>
                            <h3>No Assigned Groups</h3>
                            <p>You don't have any thesis groups assigned yet.</p>
                            <a href="assignments.php" class="btn btn-secondary">View Assignments</a>
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