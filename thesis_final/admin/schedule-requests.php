<?php
/**
 * UPDATED: Schedule Requests with Conflict Detection
 * File: thesis_final/admin/schedule-requests.php
 * FEATURES: Prevents overlapping schedules in same venue/date/time
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../db_connect.php';
$page_title = "Schedule Requests";
$page_subtitle = "Review and approve schedule requests";
$errors = [];
$success = '';
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid request.";
    } else {
        $action = $_POST['action'];
        $request_id = intval($_POST['request_id']);

        // APPROVE with CONFLICT DETECTION
        if ($action === 'approve') {
            try {
                $pdo->beginTransaction();
                
                // Get request details
                $req = $pdo->prepare("SELECT sr.*, ds.defense_date, ds.defense_time, ds.venue_id, ds.duration_minutes FROM schedule_requests sr INNER JOIN defense_schedules ds ON sr.requested_schedule_id = ds.schedule_id WHERE sr.request_id = ?");
                $req->execute([$request_id]);
                $request = $req->fetch();
                
                if (!$request) {
                    $errors[] = "Request not found.";
                } else {
                    // CRITICAL: CHECK FOR CONFLICTS
                    // Calculate end time
                    $start_time = new DateTime($request['defense_date'] . ' ' . $request['defense_time']);
                    $end_time = clone $start_time;
                    $end_time->modify('+' . $request['duration_minutes'] . ' minutes');
                    
                    // Check for overlapping schedules in same venue on same date
                    $conflict_check = $pdo->prepare("
                        SELECT ds.schedule_id, tg.group_name, ds.defense_time, ds.duration_minutes
                        FROM defense_schedules ds
                        LEFT JOIN thesis_groups tg ON ds.group_id = tg.group_id
                        WHERE ds.venue_id = ?
                        AND ds.defense_date = ?
                        AND ds.status IN ('approved', 'requested', 'completed')
                        AND ds.schedule_id != ?
                    ");
                    $conflict_check->execute([
                        $request['venue_id'], 
                        $request['defense_date'], 
                        $request['requested_schedule_id']
                    ]);
                    
                    $conflicts = [];
                    foreach ($conflict_check->fetchAll() as $existing) {
                        $existing_start = new DateTime($request['defense_date'] . ' ' . $existing['defense_time']);
                        $existing_end = clone $existing_start;
                        $existing_end->modify('+' . $existing['duration_minutes'] . ' minutes');
                        
                        // Check if times overlap
                        if ($start_time < $existing_end && $end_time > $existing_start) {
                            $conflicts[] = $existing['group_name'] . ' at ' . date('h:i A', strtotime($existing['defense_time']));
                        }
                    }
                    
                    if (!empty($conflicts)) {
                        $pdo->rollBack();
                        $errors[] = "⚠️ SCHEDULE CONFLICT DETECTED! This time slot overlaps with: " . implode(', ', $conflicts) . ". Please choose a different schedule.";
                    } else {
                        // No conflicts - proceed with approval
                        $pdo->prepare("UPDATE schedule_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE request_id = ?")->execute([$_SESSION['user_id'], $request_id]);
                        $pdo->prepare("UPDATE defense_schedules SET status = 'approved', updated_at = NOW() WHERE schedule_id = ?")->execute([$request['requested_schedule_id']]);

                        // Notify all group members
                        $members = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ?");
                        $members->execute([$request['group_id']]);
                        foreach ($members->fetchAll() as $m) {
                            $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, 'Schedule Approved', 'Your defense schedule has been approved! Check your schedule for details.', 'schedule', NOW())")->execute([$m['user_id']]);
                        }

                        $pdo->commit();
                        $success = "✅ Schedule approved successfully! No conflicts detected.";
                        // ADD THIS NEW CODE:
                        // Send email notifications
                        require_once '../includes/notification_helper.php';
                        $notif_helper = new NotificationHelper($pdo);
                        $notif_helper->emailScheduleStatusChange(
                            $request['group_id'],
                            'approved',
                            $request['defense_date'],
                            $request['defense_time'],
                            $venue_name ?? 'TBA'
                        );
                    }
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Approval Error: " . $e->getMessage());
                $errors[] = "Error approving request.";
            }
        } 
        // REJECT
        elseif ($action === 'reject') {
            try {
                $pdo->beginTransaction();
                
                $req = $pdo->prepare("SELECT * FROM schedule_requests WHERE request_id = ?");
                $req->execute([$request_id]);
                $request = $req->fetch();
                
                if (!$request) {
                    $errors[] = "Request not found.";
                } else {
                    $notes = trim($_POST['notes'] ?? '');
                    
                    $pdo->prepare("UPDATE schedule_requests SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW(), notes = ? WHERE request_id = ?")->execute([$_SESSION['user_id'], $notes, $request_id]);
                    $pdo->prepare("UPDATE defense_schedules SET status = 'available', group_id = NULL, updated_at = NOW() WHERE schedule_id = ?")->execute([$request['requested_schedule_id']]);

                    $members = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ?");
                    $members->execute([$request['group_id']]);
                    foreach ($members->fetchAll() as $m) {
                        $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, 'Schedule Request Rejected', 'Your schedule request was not approved. Reason: " . $notes . "', 'schedule', NOW())")->execute([$m['user_id']]);
                    }

                    $pdo->commit();
                    $success = "Request rejected. The time slot is now available for other groups.";
                    // Send email notifications
                    require_once '../includes/notification_helper.php';
                    $notif_helper = new NotificationHelper($pdo);
                    $notif_helper->emailScheduleStatusChange(
                        $request['group_id'],
                        'rejected',
                        $request['defense_date'],
                        $request['defense_time'],
                        $venue_name ?? 'TBA',
                        $notes
                    );
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Rejection Error: " . $e->getMessage());
                $errors[] = "Error rejecting request.";
            }
        }
    }
}

try {
    $requests = $pdo->query("SELECT sr.*, tg.group_name, tg.thesis_title, ds.defense_date, ds.defense_time, ds.duration_minutes, v.venue_name FROM schedule_requests sr INNER JOIN thesis_groups tg ON sr.group_id = tg.group_id INNER JOIN defense_schedules ds ON sr.requested_schedule_id = ds.schedule_id LEFT JOIN venues v ON ds.venue_id = v.venue_id WHERE sr.status = 'pending' ORDER BY sr.requested_at DESC")->fetchAll();
} catch (PDOException $e) {
    $requests = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Requests - Admin</title>
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
                <?php if (!empty($errors)): ?><div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

                <?php if (empty($requests)): ?>
                    <div class="content-card"><div class="card-body"><div class="empty-state"><div class="icon">⏳</div><h3>No Pending Requests</h3><p>All schedule requests have been processed.</p></div></div></div>
                <?php else: ?>
                    <?php foreach ($requests as $req): ?>
                        <div class="content-card">
                            <div class="card-header">
                                <div>
                                    <h3><?php echo htmlspecialchars($req['group_name']); ?></h3>
                                    <p style="margin: 0; font-weight: normal; color: var(--text-secondary);"><?php echo htmlspecialchars($req['thesis_title']); ?></p>
                                </div>
                                <span class="badge badge-warning">Pending Review</span>
                            </div>
                            <div class="card-body">
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                                    <div><strong>📆 Date:</strong><p><?php echo date('M d, Y', strtotime($req['defense_date'])); ?></p></div>
                                    <div><strong>🕒 Time:</strong><p><?php echo date('h:i A', strtotime($req['defense_time'])); ?></p></div>
                                    <div><strong>⏱️ Duration:</strong><p><?php echo $req['duration_minutes']; ?> min</p></div>
                                    <div><strong>📍 Venue:</strong><p><?php echo htmlspecialchars($req['venue_name']); ?></p></div>
                                </div>
                                <?php if ($req['notes']): ?>
                                    <div style="margin-top: 1rem; padding: 1rem; background: var(--bg-color); border-radius: 8px;"><strong>Student Notes:</strong><p style="margin: 0.5rem 0 0 0;"><?php echo nl2br(htmlspecialchars($req['notes'])); ?></p></div>
                                <?php endif; ?>
                                <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                                    <form method="POST" style="flex: 1;"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="approve"><input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>"><button type="submit" class="btn btn-primary" style="width: 100%;" onclick="return confirm('Approve this schedule? System will check for conflicts.')">✅ Approve Request</button></form>
                                    <button onclick="openRejectModal(<?php echo $req['request_id']; ?>, '<?php echo htmlspecialchars($req['group_name'], ENT_QUOTES); ?>')" class="btn btn-secondary" style="flex: 1;">❌ Reject Request</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="rejectModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 12px; max-width: 500px; width: 90%;">
            <h3>Reject Request: <span id="modalGroupName"></span></h3>
            <form method="POST"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="reject"><input type="hidden" name="request_id" id="modalRequestId"><div class="form-group"><label>Reason for Rejection *</label><textarea name="notes" rows="4" required placeholder="Explain why this request is being rejected..."></textarea></div><div style="display: flex; gap: 1rem;"><button type="button" onclick="document.getElementById('rejectModal').style.display='none'" class="btn btn-secondary">Cancel</button><button type="submit" class="btn btn-primary">Confirm Rejection</button></div></form>
        </div>
    </div>
    <script src="../assets/js/main.js"></script>
    <script>function openRejectModal(id, name){document.getElementById('modalRequestId').value=id;document.getElementById('modalGroupName').textContent=name;document.getElementById('rejectModal').style.display='flex';}</script>
    <script src="../assets/js/icons.js"></script>
</body>
</html>