<?php
/**
 * UPDATED: Manage Schedules with Conflict Detection
 * File: thesis_final/admin/manage-schedules.php
 * FEATURES: Prevents creating overlapping schedules
 */
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../db_connect.php';
$page_title = "Manage Schedules";
$page_subtitle = "Create and manage defense schedules";
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

        if ($action === 'create_schedule') {
            $defense_date = $_POST['defense_date'];
            $defense_time = $_POST['defense_time'];
            $venue_id = intval($_POST['venue_id']);
            $duration = intval($_POST['duration_minutes']);

            if (empty($defense_date) || empty($defense_time)) {
                $errors[] = "Date and time required.";
            } elseif ($duration < 30 || $duration > 240) {
                $errors[] = "Duration must be between 30-240 minutes.";
            } else {
                try {
                    // CRITICAL: CHECK FOR CONFLICTS BEFORE CREATING
                    $start_time = new DateTime($defense_date . ' ' . $defense_time);
                    $end_time = clone $start_time;
                    $end_time->modify('+' . $duration . ' minutes');
                    
                    // Check existing schedules in same venue on same date
                    $conflict_check = $pdo->prepare("
                        SELECT ds.schedule_id, ds.defense_time, ds.duration_minutes, tg.group_name
                        FROM defense_schedules ds
                        LEFT JOIN thesis_groups tg ON ds.group_id = tg.group_id
                        WHERE ds.venue_id = ?
                        AND ds.defense_date = ?
                        AND ds.status IN ('available', 'requested', 'approved', 'completed')
                    ");
                    $conflict_check->execute([$venue_id, $defense_date]);
                    
                    $conflicts = [];
                    foreach ($conflict_check->fetchAll() as $existing) {
                        $existing_start = new DateTime($defense_date . ' ' . $existing['defense_time']);
                        $existing_end = clone $existing_start;
                        $existing_end->modify('+' . $existing['duration_minutes'] . ' minutes');
                        
                        // Check if times overlap
                        if ($start_time < $existing_end && $end_time > $existing_start) {
                            $group_info = $existing['group_name'] ?: 'Available Slot';
                            $conflicts[] = $group_info . ' at ' . date('h:i A', strtotime($existing['defense_time'])) . ' (' . $existing['duration_minutes'] . ' min)';
                        }
                    }
                    
                    if (!empty($conflicts)) {
                        $errors[] = "⚠️ SCHEDULE CONFLICT! This time slot overlaps with existing schedule(s): " . implode(', ', $conflicts) . ". Please choose a different time or venue.";
                    } else {
                        // No conflicts - create schedule
                        $pdo->prepare("INSERT INTO defense_schedules (defense_date, defense_time, venue_id, status, duration_minutes, created_at) VALUES (?, ?, ?, 'available', ?, NOW())")->execute([$defense_date, $defense_time, $venue_id, $duration]);
                        $success = "✅ Schedule created successfully! No conflicts detected.";
                    }
                } catch (PDOException $e) {
                    error_log("Create Schedule Error: " . $e->getMessage());
                    $errors[] = "Error creating schedule.";
                }
            }
        } elseif ($action === 'delete_schedule') {
            $schedule_id = intval($_POST['schedule_id']);
            try {
                $pdo->prepare("DELETE FROM defense_schedules WHERE schedule_id = ? AND status = 'available'")->execute([$schedule_id]);
                $success = "Schedule deleted.";
            } catch (PDOException $e) {
                $errors[] = "Error deleting schedule.";
            }
        }
    }
}

try {
    $schedules = $pdo->query("SELECT ds.*, v.venue_name, v.building, v.room_number, tg.group_name FROM defense_schedules ds LEFT JOIN venues v ON ds.venue_id = v.venue_id LEFT JOIN thesis_groups tg ON ds.group_id = tg.group_id ORDER BY ds.defense_date ASC, ds.defense_time ASC")->fetchAll();
    $venues = $pdo->query("SELECT * FROM venues WHERE status = 'active' ORDER BY venue_name")->fetchAll();
} catch (PDOException $e) {
    $schedules = [];
    $venues = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedules - Admin</title>
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

                <div class="form-card">
                    <div class="form-card-header"><h3>➕ Create New Schedule</h3></div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="create_schedule">
                        <div class="form-row">
                            <div class="form-group"><label>Defense Date *</label><input type="date" name="defense_date" required min="<?php echo date('Y-m-d'); ?>"></div>
                            <div class="form-group"><label>Defense Time *</label><input type="time" name="defense_time" required></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Venue *</label><select name="venue_id" required><option value="">Select Venue</option><?php foreach ($venues as $v): ?><option value="<?php echo $v['venue_id']; ?>"><?php echo htmlspecialchars($v['venue_name'] . ' - ' . $v['building']); ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label>Duration (minutes) *</label><input type="number" name="duration_minutes" value="60" min="30" max="240" required></div>
                        </div>
                        <div class="form-actions"><button type="submit" class="btn btn-primary">Create Schedule</button></div>
                    </form>
                </div>

                <div class="content-card">
                    <div class="card-header"><h3>📅 All Schedules</h3></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead><tr><th>Date & Time</th><th>Venue</th><th>Duration</th><th>Status</th><th>Group</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($schedules as $s): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y h:i A', strtotime($s['defense_date'] . ' ' . $s['defense_time'])); ?></td>
                                            <td><?php echo htmlspecialchars($s['venue_name'] . ' - ' . $s['building'] . ' ' . $s['room_number']); ?></td>
                                            <td><?php echo $s['duration_minutes']; ?> min</td>
                                            <td><span class="badge badge-<?php echo $s['status'] === 'available' ? 'success' : ($s['status'] === 'completed' ? 'primary' : 'warning'); ?>"><?php echo ucfirst($s['status']); ?></span></td>
                                            <td><?php echo $s['group_name'] ? htmlspecialchars($s['group_name']) : '-'; ?></td>
                                            <td>
                                                <?php if ($s['status'] === 'available' && !$s['group_id']): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this schedule?');"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="delete_schedule"><input type="hidden" name="schedule_id" value="<?php echo $s['schedule_id']; ?>"><button type="submit" class="btn-icon btn-delete">🗑️</button></form>
                                                <?php endif; ?>
                                            </td>
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