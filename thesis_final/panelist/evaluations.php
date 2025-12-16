<?php
/**
 * Evaluations Page
 * Submit and view thesis defense evaluations
 * FIXED: Auto-marks defense as completed when all panelists evaluate
 */

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'panelist') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

$page_title = "Evaluations";
$page_subtitle = "Submit thesis defense evaluations";

$errors = [];
$success = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$schedule_id = isset($_GET['schedule_id']) ? intval($_GET['schedule_id']) : 0;

// Get schedule details
$schedule_details = null;
$existing_evaluation = null;

if ($schedule_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT ds.*, v.venue_name, tg.group_id, tg.group_name, tg.thesis_title,
                   pa.role
            FROM defense_schedules ds
            INNER JOIN panel_assignments pa ON ds.schedule_id = pa.schedule_id
            LEFT JOIN venues v ON ds.venue_id = v.venue_id
            LEFT JOIN thesis_groups tg ON ds.group_id = tg.group_id
            WHERE ds.schedule_id = ? AND pa.panelist_id = ? AND pa.status = 'accepted'
        ");
        $stmt->execute([$schedule_id, $_SESSION['user_id']]);
        $schedule_details = $stmt->fetch();

        if ($schedule_details) {
            // Check for existing evaluation
            $eval_check = $pdo->prepare("
                SELECT * FROM evaluations 
                WHERE schedule_id = ? AND panelist_id = ? AND group_id = ?
            ");
            $eval_check->execute([$schedule_id, $_SESSION['user_id'], $schedule_details['group_id']]);
            $existing_evaluation = $eval_check->fetch();
        }
    } catch (PDOException $e) {
        error_log("Schedule Fetch Error: " . $e->getMessage());
    }
}

// Process form submission - FIXED VERSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid request.";
    } elseif (!$schedule_details) {
        $errors[] = "Invalid schedule.";
    } else {
        $action = $_POST['action'];

        if ($action === 'submit_evaluation') {
            $score = floatval($_POST['score']);
            $comments = trim($_POST['comments']);
            $verdict = trim($_POST['verdict']);

            // Validation
            if ($score < 0 || $score > 100) {
                $errors[] = "Score must be between 0 and 100.";
            }

            if (empty($comments) || strlen($comments) < 20) {
                $errors[] = "Comments must be at least 20 characters.";
            }

            if (!in_array($verdict, ['passed', 'revisions_required', 'failed'])) {
                $errors[] = "Invalid verdict.";
            }

            // Handle file upload
            $file_path = null;
            if (isset($_FILES['evaluation_file']) && $_FILES['evaluation_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['evaluation_file'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['pdf', 'doc', 'docx'];

                if (!in_array($file_ext, $allowed_extensions)) {
                    $errors[] = "Only PDF, DOC, DOCX files allowed.";
                } elseif ($file['size'] > 10485760) {
                    $errors[] = "File must be less than 10MB.";
                } else {
                    $upload_dir = '../uploads/evaluations/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    $unique_filename = 'eval_' . $schedule_id . '_' . $_SESSION['user_id'] . '_' . time() . '.' . $file_ext;
                    $file_path = $upload_dir . $unique_filename;
                    
                    if (!move_uploaded_file($file['tmp_name'], $file_path)) {
                        $errors[] = "Failed to upload file.";
                        $file_path = null;
                    }
                }
            }

            // FIXED: Complete evaluation submission with auto-complete defense
            if (empty($errors)) {
                try {
                    $pdo->beginTransaction();

                    if ($existing_evaluation) {
                        // Update existing evaluation
                        $update_stmt = $pdo->prepare("
                            UPDATE evaluations 
                            SET comments = ?, score = ?, verdict = ?, file_path = ?, evaluated_at = NOW()
                            WHERE evaluation_id = ?
                        ");
                        $update_stmt->execute([$comments, $score, $verdict, $file_path, $existing_evaluation['evaluation_id']]);
                        $success = "Evaluation updated successfully!";
                    } else {
                        // Insert new evaluation
                        $insert_stmt = $pdo->prepare("
                            INSERT INTO evaluations (schedule_id, group_id, panelist_id, comments, score, verdict, file_path, evaluated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $insert_stmt->execute([
                            $schedule_id,
                            $schedule_details['group_id'],
                            $_SESSION['user_id'],
                            $comments,
                            $score,
                            $verdict,
                            $file_path
                        ]);
                        $success = "Evaluation submitted successfully!";
                    }

                    // CRITICAL FIX: Check if all panelists have submitted evaluations
                    $check_evals = $pdo->prepare("
                        SELECT COUNT(DISTINCT pa.panelist_id) as total_panelists,
                               COUNT(DISTINCT e.panelist_id) as evaluated_panelists
                        FROM panel_assignments pa
                        LEFT JOIN evaluations e ON pa.schedule_id = e.schedule_id AND pa.panelist_id = e.panelist_id
                        WHERE pa.schedule_id = ? AND pa.status = 'accepted'
                    ");
                    $check_evals->execute([$schedule_id]);
                    $eval_status = $check_evals->fetch();
                    
                    // If all panelists have evaluated, mark defense as completed
                    if ($eval_status['total_panelists'] > 0 && 
                        $eval_status['total_panelists'] == $eval_status['evaluated_panelists']) {
                        
                        $pdo->prepare("
                            UPDATE defense_schedules 
                            SET status = 'completed', updated_at = NOW() 
                            WHERE schedule_id = ?
                        ")->execute([$schedule_id]);
                        
                        // Notify students that all evaluations are complete
                        $notify_students = $pdo->prepare("
                            SELECT DISTINCT gm.user_id 
                            FROM group_members gm
                            INNER JOIN defense_schedules ds ON gm.group_id = ds.group_id
                            WHERE ds.schedule_id = ?
                        ");
                        $notify_students->execute([$schedule_id]);
                        
                        foreach ($notify_students->fetchAll() as $student) {
                            $pdo->prepare("
                                INSERT INTO notifications (user_id, title, message, type, created_at) 
                                VALUES (?, 'Evaluations Complete', 'All panelists have submitted their evaluations. You can now view your results.', 'evaluation', NOW())
                            ")->execute([$student['user_id']]);
                        }
                        
                        $success .= " All panelists have evaluated - defense marked as completed!";
                    }

                    // Notify group members about new evaluation
                    $members_stmt = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ?");
                    $members_stmt->execute([$schedule_details['group_id']]);
                    foreach ($members_stmt->fetchAll() as $member) {
                        $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, 'Evaluation Submitted', 'A panelist has submitted an evaluation for your defense.', 'evaluation', NOW())");
                        $notif->execute([$member['user_id']]);
                    }

                    $pdo->commit();

                    // Refresh evaluation
                    $eval_check->execute([$schedule_id, $_SESSION['user_id'], $schedule_details['group_id']]);
                    $existing_evaluation = $eval_check->fetch();
                    
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Evaluation Submit Error: " . $e->getMessage());
                    $errors[] = "An error occurred. Please try again.";
                }
            }
        }
    }
}

// Get all completed schedules for selection
try {
    $all_schedules = $pdo->prepare("
        SELECT ds.schedule_id, ds.defense_date, tg.group_name, tg.thesis_title,
               (SELECT COUNT(*) FROM evaluations WHERE schedule_id = ds.schedule_id AND panelist_id = ?) as has_eval
        FROM defense_schedules ds
        INNER JOIN panel_assignments pa ON ds.schedule_id = pa.schedule_id
        LEFT JOIN thesis_groups tg ON ds.group_id = tg.group_id
        WHERE pa.panelist_id = ? AND pa.status = 'accepted' AND ds.status IN ('approved', 'completed')
        ORDER BY ds.defense_date DESC
    ");
    $all_schedules->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $completed_schedules = $all_schedules->fetchAll();
} catch (PDOException $e) {
    $completed_schedules = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluations - Thesis Panel System</title>
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
                    <div class="alert alert-error"><ul><?php foreach ($errors as $error): ?><li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?></ul></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if (!empty($completed_schedules)): ?>
                <div class="content-card">
                    <div class="card-header"><h3>📝 Select Defense to Evaluate</h3></div>
                    <div class="card-body">
                        <div class="filter-group">
                            <label>Defense:</label>
                            <select onchange="window.location.href='evaluations.php?schedule_id=' + this.value">
                                <option value="">Select a defense...</option>
                                <?php foreach ($completed_schedules as $sched): ?>
                                    <option value="<?php echo $sched['schedule_id']; ?>" <?php echo $schedule_id == $sched['schedule_id'] ? 'selected' : ''; ?>>
                                        <?php echo date('M d, Y', strtotime($sched['defense_date'])) . ' - ' . htmlspecialchars($sched['group_name']); ?>
                                        <?php echo $sched['has_eval'] > 0 ? ' ✅' : ' ⏳'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($schedule_details): ?>
                <div class="form-card">
                    <div class="form-card-header">
                        <h3><?php echo $existing_evaluation ? '📝 Update Evaluation' : '📝 Submit Evaluation'; ?></h3>
                        <p><?php echo htmlspecialchars($schedule_details['group_name']) . ' - ' . htmlspecialchars($schedule_details['thesis_title']); ?></p>
                    </div>
                    <form method="POST" action="evaluations.php?schedule_id=<?php echo $schedule_id; ?>" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="action" value="submit_evaluation">
                        
                        <div class="form-group">
                            <label for="score">Score (0-100) <span class="required">*</span></label>
                            <input type="number" id="score" name="score" min="0" max="100" step="0.01" required value="<?php echo $existing_evaluation ? $existing_evaluation['score'] : ''; ?>" placeholder="Enter score">
                        </div>

                        <div class="form-group">
                            <label for="verdict">Verdict <span class="required">*</span></label>
                            <select id="verdict" name="verdict" required>
                                <option value="">Select Verdict</option>
                                <option value="passed" <?php echo ($existing_evaluation && $existing_evaluation['verdict'] === 'passed') ? 'selected' : ''; ?>>Passed</option>
                                <option value="revisions_required" <?php echo ($existing_evaluation && $existing_evaluation['verdict'] === 'revisions_required') ? 'selected' : ''; ?>>Revisions Required</option>
                                <option value="failed" <?php echo ($existing_evaluation && $existing_evaluation['verdict'] === 'failed') ? 'selected' : ''; ?>>Failed</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="comments">Comments & Feedback <span class="required">*</span></label>
                            <textarea id="comments" name="comments" required minlength="20" rows="8" placeholder="Provide detailed feedback on the thesis defense..."><?php echo $existing_evaluation ? htmlspecialchars($existing_evaluation['comments']) : ''; ?></textarea>
                            <small class="form-help">Minimum 20 characters. Be specific about strengths and areas for improvement.</small>
                        </div>

                        <div class="form-group">
                            <label for="evaluation_file">Evaluation Document (Optional)</label>
                            <input type="file" id="evaluation_file" name="evaluation_file" accept=".pdf,.doc,.docx">
                            <small class="form-help">Upload evaluation sheet or additional comments (PDF, DOC, DOCX - Max 10MB)</small>
                            <?php if ($existing_evaluation && $existing_evaluation['file_path']): ?>
                                <p style="margin-top: 0.5rem;"><strong>Current file:</strong> <a href="download-evaluation.php?id=<?php echo $existing_evaluation['evaluation_id']; ?>">Download</a></p>
                            <?php endif; ?>
                        </div>

                        <div class="form-actions">
                            <a href="schedule.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary"><?php echo $existing_evaluation ? 'Update Evaluation' : 'Submit Evaluation'; ?></button>
                        </div>
                    </form>
                </div>
                <?php elseif (empty($completed_schedules)): ?>
                <div class="content-card">
                    <div class="card-body">
                        <div class="empty-state">
                            <div class="icon">📝</div>
                            <h3>No Defenses Available</h3>
                            <p>You don't have any defenses available for evaluation yet.</p>
                            <a href="schedule.php" class="btn btn-secondary">View Schedule</a>
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