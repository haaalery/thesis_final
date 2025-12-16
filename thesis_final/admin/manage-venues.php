<?php
// ============================================
// FILE: thesis_final/admin/manage-venues.php
// ============================================
?>
<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../db_connect.php';
$page_title = "Manage Venues";
$page_subtitle = "Add and manage defense venues";
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

        if ($action === 'add_venue') {
            $venue_name = trim($_POST['venue_name']);
            $building = trim($_POST['building']);
            $room_number = trim($_POST['room_number']);
            $capacity = intval($_POST['capacity']);
            $facilities = trim($_POST['facilities']);

            if (empty($venue_name) || empty($building)) {
                $errors[] = "Venue name and building required.";
            } else {
                try {
                    $pdo->prepare("INSERT INTO venues (venue_name, building, room_number, capacity, facilities, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())")->execute([$venue_name, $building, $room_number, $capacity, $facilities]);
                    $success = "Venue added successfully!";
                } catch (PDOException $e) {
                    $errors[] = "Error adding venue.";
                }
            }
        } elseif ($action === 'update_status') {
            $venue_id = intval($_POST['venue_id']);
            $status = $_POST['status'];
            if (in_array($status, ['active', 'inactive'])) {
                try {
                    $pdo->prepare("UPDATE venues SET status = ? WHERE venue_id = ?")->execute([$status, $venue_id]);
                    $success = "Venue status updated.";
                } catch (PDOException $e) {
                    $errors[] = "Error updating venue.";
                }
            }
        } elseif ($action === 'delete_venue') {
            $venue_id = intval($_POST['venue_id']);
            try {
                $pdo->prepare("DELETE FROM venues WHERE venue_id = ?")->execute([$venue_id]);
                $success = "Venue deleted.";
            } catch (PDOException $e) {
                $errors[] = "Cannot delete venue with associated schedules.";
            }
        }
    }
}

try {
    $venues = $pdo->query("SELECT v.*, COUNT(DISTINCT ds.schedule_id) as schedule_count FROM venues v LEFT JOIN defense_schedules ds ON v.venue_id = ds.venue_id GROUP BY v.venue_id ORDER BY v.venue_name")->fetchAll();
} catch (PDOException $e) {
    $venues = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Venues - Admin</title>
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
                    <div class="form-card-header"><h3>➕ Add New Venue</h3></div>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="add_venue">
                        <div class="form-row">
                            <div class="form-group"><label>Venue Name *</label><input type="text" name="venue_name" required></div>
                            <div class="form-group"><label>Building *</label><input type="text" name="building" required></div>
                        </div>
                        <div class="form-row">
                            <div class="form-group"><label>Room Number</label><input type="text" name="room_number"></div>
                            <div class="form-group"><label>Capacity</label><input type="number" name="capacity" value="20" min="1"></div>
                        </div>
                        <div class="form-group"><label>Facilities</label><textarea name="facilities" rows="3" placeholder="e.g., Projector, Whiteboard, AC"></textarea></div>
                        <div class="form-actions"><button type="submit" class="btn btn-primary">Add Venue</button></div>
                    </form>
                </div>

                <div class="content-card">
                    <div class="card-header"><h3>📍 All Venues</h3></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead><tr><th>Venue Name</th><th>Building</th><th>Room</th><th>Capacity</th><th>Facilities</th><th>Status</th><th>Schedules</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php foreach ($venues as $v): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($v['venue_name']); ?></td>
                                            <td><?php echo htmlspecialchars($v['building']); ?></td>
                                            <td><?php echo htmlspecialchars($v['room_number']); ?></td>
                                            <td><?php echo $v['capacity']; ?></td>
                                            <td><?php echo htmlspecialchars($v['facilities']); ?></td>
                                            <td><span class="badge badge-<?php echo $v['status'] === 'active' ? 'success' : 'secondary'; ?>"><?php echo ucfirst($v['status']); ?></span></td>
                                            <td><?php echo $v['schedule_count']; ?></td>
                                            <td class="action-buttons">
                                                <form method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="update_status"><input type="hidden" name="venue_id" value="<?php echo $v['venue_id']; ?>"><input type="hidden" name="status" value="<?php echo $v['status'] === 'active' ? 'inactive' : 'active'; ?>"><button type="submit" class="btn-icon"><?php echo $v['status'] === 'active' ? '🔒' : '🔓'; ?></button></form>
                                                <?php if ($v['schedule_count'] == 0): ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this venue?');"><input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"><input type="hidden" name="action" value="delete_venue"><input type="hidden" name="venue_id" value="<?php echo $v['venue_id']; ?>"><button type="submit" class="btn-icon btn-delete">🗑️</button></form>
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