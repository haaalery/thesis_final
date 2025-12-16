<?php
/**
 * Defense Schedule Page
 * View available schedules and request defense schedule
 */

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

// Page title and subtitle
$page_title = "Defense Schedule";
$page_subtitle = "View and request your thesis defense schedule";

// Initialize variables
$errors = [];
$success = '';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user has a thesis group
try {
    $group_check = $pdo->prepare("
        SELECT tg.*, gm.role as member_role
        FROM thesis_groups tg
        INNER JOIN group_members gm ON tg.group_id = gm.group_id
        WHERE gm.user_id = ?
    ");
    $group_check->execute([$_SESSION['user_id']]);
    $thesis_group = $group_check->fetch();
} catch (PDOException $e) {
    error_log("Group Check Error: " . $e->getMessage());
    $thesis_group = null;
}

// Get current defense schedule if exists
$current_schedule = null;
$schedule_request = null;
if ($thesis_group) {
    try {
        // Check for approved or completed schedule
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
        $current_schedule = $schedule_stmt->fetch();

        // Check for pending schedule request
        $request_stmt = $pdo->prepare("
            SELECT sr.*, ds.defense_date, ds.defense_time, v.venue_name
            FROM schedule_requests sr
            INNER JOIN defense_schedules ds ON sr.requested_schedule_id = ds.schedule_id
            LEFT JOIN venues v ON ds.venue_id = v.venue_id
            WHERE sr.group_id = ? AND sr.status = 'pending'
            ORDER BY sr.requested_at DESC
            LIMIT 1
        ");
        $request_stmt->execute([$thesis_group['group_id']]);
        $schedule_request = $request_stmt->fetch();
    } catch (PDOException $e) {
        error_log("Schedule Fetch Error: " . $e->getMessage());
    }
}

// Get available schedules
$available_schedules = [];
if ($thesis_group && $thesis_group['status'] === 'approved' && !$current_schedule && !$schedule_request) {
    try {
        $available_stmt = $pdo->prepare("
            SELECT ds.*, v.venue_name, v.building, v.room_number, v.capacity
            FROM defense_schedules ds
            INNER JOIN venues v ON ds.venue_id = v.venue_id
            WHERE ds.status = 'available' 
            AND ds.defense_date >= CURDATE()
            AND ds.group_id IS NULL
            ORDER BY ds.defense_date ASC, ds.defense_time ASC
        ");
        $available_stmt->execute();
        $available_schedules = $available_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Available Schedules Error: " . $e->getMessage());
    }
}

// Process schedule request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid request. Please try again.";
    } elseif (!$thesis_group) {
        $errors[] = "You must be part of a thesis group to request a schedule.";
    } elseif ($thesis_group['status'] !== 'approved') {
        $errors[] = "Your thesis group must be approved before requesting a schedule.";
    } elseif ($current_schedule) {
        $errors[] = "You already have an approved defense schedule.";
    } elseif ($schedule_request) {
        $errors[] = "You already have a pending schedule request.";
    } else {
        $action = $_POST['action'];

        // REQUEST SCHEDULE
    }    // REPLACE THE ENTIRE "REQUEST SCHEDULE" SECTION IN student/schedule.php
// This goes around line 135-185 in the original file
// Look for: if ($action === 'request_schedule') {

// REQUEST SCHEDULE - FIXED VERSION
if ($action === 'request_schedule') {
    $schedule_id = intval($_POST['schedule_id']);
    $notes = trim($_POST['notes'] ?? '');

    // Verify schedule is available
    try {
        $verify_stmt = $pdo->prepare("
            SELECT * FROM defense_schedules 
            WHERE schedule_id = ? AND status = 'available' AND group_id IS NULL
        ");
        $verify_stmt->execute([$schedule_id]);
        $schedule = $verify_stmt->fetch();

        if (!$schedule) {
            $errors[] = "Selected schedule is no longer available.";
        } else {
            $pdo->beginTransaction();

            // FIXED: Update schedule with group_id FIRST, then change status to 'requested'
            $update_schedule = $pdo->prepare("
                UPDATE defense_schedules 
                SET status = 'requested', group_id = ?, updated_at = NOW()
                WHERE schedule_id = ?
            ");
            $update_schedule->execute([$thesis_group['group_id'], $schedule_id]);

            // Insert schedule request
            $request_insert = $pdo->prepare("
                INSERT INTO schedule_requests (group_id, requested_schedule_id, requested_at, status, notes) 
                VALUES (?, ?, NOW(), 'pending', ?)
            ");
            $request_insert->execute([$thesis_group['group_id'], $schedule_id, $notes]);

            // Notify group members
            $members_stmt = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ?");
            $members_stmt->execute([$thesis_group['group_id']]);
            $members = $members_stmt->fetchAll();

            foreach ($members as $member) {
                $notif_stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, title, message, type, created_at) 
                    VALUES (?, 'Schedule Request Submitted', 'Your defense schedule request has been submitted and is pending admin approval.', 'schedule', NOW())
                ");
                $notif_stmt->execute([$member['user_id']]);
            }

            // Log activity
            $log_stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, created_at) 
                VALUES (?, 'request_schedule', 'schedule_requests', ?, ?, NOW())
            ");
            $log_stmt->execute([$_SESSION['user_id'], $pdo->lastInsertId(), $_SERVER['REMOTE_ADDR']]);

            $pdo->commit();

            $success = "Schedule request submitted successfully! You will be notified once reviewed.";

            // Refresh data
            $request_stmt->execute([$thesis_group['group_id']]);
            $schedule_request = $request_stmt->fetch();
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Schedule Request Error: " . $e->getMessage());
        $errors[] = "An error occurred while submitting your request. Please try again.";
    }
}
    }

// Get panelist assignments if schedule exists
$panelists = [];
if ($current_schedule) {
    try {
        $panelists_stmt = $pdo->prepare("
            SELECT pa.*, u.name, u.email, pd.specialization, pd.title
            FROM panel_assignments pa
            INNER JOIN users u ON pa.panelist_id = u.user_id
            LEFT JOIN panelist_details pd ON u.user_id = pd.user_id
            WHERE pa.schedule_id = ?
            ORDER BY pa.role ASC
        ");
        $panelists_stmt->execute([$current_schedule['schedule_id']]);
        $panelists = $panelists_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Panelists Fetch Error: " . $e->getMessage());
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

                <?php if (!$thesis_group): ?>
                    <!-- No Group Message -->
                    <div class="content-card">
                        <div class="card-body">
                            <div class="empty-state">
                                <div class="icon">📅</div>
                                <h3>No Thesis Group</h3>
                                <p>You need to create or join a thesis group before requesting a defense schedule.</p>
                                <a href="thesis-group.php" class="btn btn-primary">Create Thesis Group</a>
                            </div>
                        </div>
                    </div>
                <?php elseif ($thesis_group['status'] !== 'approved'): ?>
                    <!-- Thesis Not Approved -->
                    <div class="content-card">
                        <div class="card-body">
                            <div class="empty-state">
                                <div class="icon">⏳</div>
                                <h3>Thesis Pending Approval</h3>
                                <p>Your thesis group must be approved by an administrator before you can request a defense schedule.</p>
                                <p>Current Status: 
                                    <span class="badge badge-<?php echo $thesis_group['status'] === 'rejected' ? 'danger' : 'warning'; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $thesis_group['status'])); ?>
                                    </span>
                                </p>
                                <a href="thesis-group.php" class="btn btn-secondary">View Thesis Group</a>
                            </div>
                        </div>
                    </div>
                <?php elseif ($current_schedule): ?>
                    <!-- Current Schedule -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3>📅 Your Defense Schedule</h3>
                            <span class="badge badge-<?php echo $current_schedule['status'] === 'completed' ? 'success' : 'primary'; ?>">
                                <?php echo ucfirst($current_schedule['status']); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                                <div>
                                    <strong>📆 Date:</strong>
                                    <p style="font-size: 1.1rem; color: var(--primary-color); font-weight: 600;">
                                        <?php echo date('l, F d, Y', strtotime($current_schedule['defense_date'])); ?>
                                    </p>
                                </div>
                                <div>
                                    <strong>🕒 Time:</strong>
                                    <p style="font-size: 1.1rem; color: var(--primary-color); font-weight: 600;">
                                        <?php echo date('h:i A', strtotime($current_schedule['defense_time'])); ?>
                                    </p>
                                </div>
                                <div>
                                    <strong>⏱️ Duration:</strong>
                                    <p style="font-size: 1.1rem;">
                                        <?php echo $current_schedule['duration_minutes']; ?> minutes
                                    </p>
                                </div>
                            </div>

                            <div style="padding: 1rem; background: var(--bg-color); border-radius: 8px; margin-bottom: 1.5rem;">
                                <strong>📍 Venue:</strong>
                                <p style="font-size: 1.1rem; margin-top: 0.5rem;">
                                    <strong><?php echo htmlspecialchars($current_schedule['venue_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($current_schedule['building'] . ' - Room ' . $current_schedule['room_number']); ?></small>
                                </p>
                            </div>

                            <?php if (!empty($panelists)): ?>
                                <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 2px solid var(--border-color);">
                                    <strong style="font-size: 1.1rem;">👨‍🏫 Panel Members:</strong>
                                    <div style="margin-top: 1rem;">
                                        <?php foreach ($panelists as $panelist): ?>
                                            <div style="padding: 1rem; background: white; border: 2px solid var(--border-color); border-radius: 8px; margin-bottom: 0.5rem;">
                                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($panelist['name']); ?></strong>
                                                        <?php if ($panelist['title']): ?>
                                                            <small style="color: var(--text-secondary);"> - <?php echo htmlspecialchars($panelist['title']); ?></small>
                                                        <?php endif; ?>
                                                        <br>
                                                        <small style="color: var(--text-secondary);">
                                                            <?php echo htmlspecialchars($panelist['email']); ?>
                                                            <?php if ($panelist['specialization']): ?>
                                                                | <?php echo htmlspecialchars($panelist['specialization']); ?>
                                                            <?php endif; ?>
                                                        </small>
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
                        </div>
                    </div>
                <?php elseif ($schedule_request): ?>
                    <!-- Pending Request -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3>⏳ Pending Schedule Request</h3>
                            <span class="badge badge-warning">Pending Approval</span>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-warning">
                                Your schedule request is currently being reviewed by the administrator. You will be notified once it's approved or if changes are needed.
                            </div>
                            
                            <div style="margin-top: 1rem;">
                                <strong>Requested Date:</strong>
                                <p><?php echo date('F d, Y', strtotime($schedule_request['defense_date'])); ?></p>
                            </div>
                            <div style="margin-top: 1rem;">
                                <strong>Requested Time:</strong>
                                <p><?php echo date('h:i A', strtotime($schedule_request['defense_time'])); ?></p>
                            </div>
                            <div style="margin-top: 1rem;">
                                <strong>Venue:</strong>
                                <p><?php echo htmlspecialchars($schedule_request['venue_name']); ?></p>
                            </div>
                            <?php if ($schedule_request['notes']): ?>
                                <div style="margin-top: 1rem;">
                                    <strong>Your Notes:</strong>
                                    <p><?php echo nl2br(htmlspecialchars($schedule_request['notes'])); ?></p>
                                </div>
                            <?php endif; ?>
                            <div style="margin-top: 1rem;">
                                <strong>Request Submitted:</strong>
                                <p><?php echo date('F d, Y h:i A', strtotime($schedule_request['requested_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                <?php elseif (!empty($available_schedules)): ?>
                    <!-- Available Schedules -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3>📅 Request Defense Schedule</h3>
                            <p style="margin: 0; font-weight: normal; color: var(--text-secondary);">
                                Choose a date and time for your thesis defense
                            </p>
                        </div>
                        <div class="card-body">
                            <div style="display: grid; gap: 1rem;">
                                <?php foreach ($available_schedules as $schedule): ?>
                                    <div style="border: 2px solid var(--border-color); padding: 1.5rem; border-radius: 8px; transition: all 0.3s ease;" class="schedule-item">
                                        <div style="display: flex; justify-content: space-between; align-items: start; flex-wrap: wrap; gap: 1rem;">
                                            <div style="flex: 1;">
                                                <div style="font-size: 1.2rem; font-weight: 600; color: var(--primary-color); margin-bottom: 0.5rem;">
                                                    📆 <?php echo date('l, F d, Y', strtotime($schedule['defense_date'])); ?>
                                                </div>
                                                <div style="font-size: 1.1rem; margin-bottom: 0.5rem;">
                                                    🕒 <?php echo date('h:i A', strtotime($schedule['defense_time'])); ?>
                                                    (<?php echo $schedule['duration_minutes']; ?> minutes)
                                                </div>
                                                <div style="margin-top: 0.5rem;">
                                                    <strong>📍 Venue:</strong> <?php echo htmlspecialchars($schedule['venue_name']); ?><br>
                                                    <small style="color: var(--text-secondary);">
                                                        <?php echo htmlspecialchars($schedule['building'] . ' - Room ' . $schedule['room_number']); ?>
                                                        (Capacity: <?php echo $schedule['capacity']; ?>)
                                                    </small>
                                                </div>
                                            </div>
                                            <div>
                                                <button 
                                                    onclick="openRequestModal(<?php echo $schedule['schedule_id']; ?>, '<?php echo date('F d, Y', strtotime($schedule['defense_date'])); ?>', '<?php echo date('h:i A', strtotime($schedule['defense_time'])); ?>', '<?php echo htmlspecialchars($schedule['venue_name']); ?>')" 
                                                    class="btn btn-primary"
                                                >
                                                    Request This Slot
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- No Available Schedules -->
                    <div class="content-card">
                        <div class="card-body">
                            <div class="empty-state">
                                <div class="icon">📅</div>
                                <h3>No Available Schedules</h3>
                                <p>There are currently no available defense schedules. Please check back later or contact your administrator.</p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Request Modal -->
    <div id="requestModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: white; padding: 2rem; border-radius: 12px; max-width: 500px; width: 90%;">
            <h3 style="margin-bottom: 1rem;">Confirm Schedule Request</h3>
            
            <div style="background: var(--bg-color); padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <p><strong>Date:</strong> <span id="modalDate"></span></p>
                <p><strong>Time:</strong> <span id="modalTime"></span></p>
                <p><strong>Venue:</strong> <span id="modalVenue"></span></p>
            </div>

            <form method="POST" action="schedule.php" id="requestForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="request_schedule">
                <input type="hidden" name="schedule_id" id="modalScheduleId">
                
                <div class="form-group">
                    <label for="notes">Notes (Optional)</label>
                    <textarea 
                        id="notes" 
                        name="notes" 
                        rows="4"
                        placeholder="Any special requirements or notes for the administrator"
                    ></textarea>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 1.5rem;">
                    <button type="button" onclick="closeRequestModal()" class="btn btn-secondary">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary" id="submitRequestBtn">
                        Confirm Request
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        function openRequestModal(scheduleId, date, time, venue) {
            document.getElementById('modalScheduleId').value = scheduleId;
            document.getElementById('modalDate').textContent = date;
            document.getElementById('modalTime').textContent = time;
            document.getElementById('modalVenue').textContent = venue;
            document.getElementById('requestModal').style.display = 'flex';
        }

        function closeRequestModal() {
            document.getElementById('requestModal').style.display = 'none';
            document.getElementById('notes').value = '';
        }

        // Close modal on outside click
        document.getElementById('requestModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeRequestModal();
            }
        });

        // Form submission
        document.getElementById('requestForm')?.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitRequestBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
        });

        // Hover effect for schedule items
        document.querySelectorAll('.schedule-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.borderColor = 'var(--primary-color)';
                this.style.boxShadow = 'var(--shadow-lg)';
            });
            item.addEventListener('mouseleave', function() {
                this.style.borderColor = 'var(--border-color)';
                this.style.boxShadow = 'none';
            });
        });
    </script>
    <script src="../assets/js/icons.js"></script>
</body>
</html>