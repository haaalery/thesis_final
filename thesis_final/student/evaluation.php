<?php
/**
 * Evaluation Results Page
 * View panelist evaluations and feedback
 */

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

// Page title and subtitle
$page_title = "Evaluations";
$page_subtitle = "View your thesis defense evaluations";

// Check if user has a thesis group
try {
    $group_check = $pdo->prepare("
        SELECT tg.*
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

// Get completed defense schedule
$defense_schedule = null;
$evaluations = [];
$evaluation_summary = null;

if ($thesis_group) {
    try {
        // Get completed defense schedule
        $schedule_stmt = $pdo->prepare("
            SELECT ds.*, v.venue_name, v.building, v.room_number
            FROM defense_schedules ds
            LEFT JOIN venues v ON ds.venue_id = v.venue_id
            WHERE ds.group_id = ? AND ds.status = 'completed'
            ORDER BY ds.defense_date DESC, ds.defense_time DESC
            LIMIT 1
        ");
        $schedule_stmt->execute([$thesis_group['group_id']]);
        $defense_schedule = $schedule_stmt->fetch();

        if ($defense_schedule) {
            // Get all evaluations
            $eval_stmt = $pdo->prepare("
                SELECT e.*, u.name as panelist_name, pd.title, pd.specialization, pa.role
                FROM evaluations e
                INNER JOIN users u ON e.panelist_id = u.user_id
                LEFT JOIN panelist_details pd ON u.user_id = pd.user_id
                LEFT JOIN panel_assignments pa ON e.schedule_id = pa.schedule_id AND e.panelist_id = pa.panelist_id
                WHERE e.schedule_id = ? AND e.group_id = ?
                ORDER BY pa.role ASC, u.name ASC
            ");
            $eval_stmt->execute([$defense_schedule['schedule_id'], $thesis_group['group_id']]);
            $evaluations = $eval_stmt->fetchAll();

            // Get evaluation summary
            $summary_stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_evaluations,
                    AVG(score) as average_score,
                    MIN(score) as min_score,
                    MAX(score) as max_score,
                    SUM(CASE WHEN verdict = 'passed' THEN 1 ELSE 0 END) as passed_count,
                    SUM(CASE WHEN verdict = 'revisions_required' THEN 1 ELSE 0 END) as revision_count,
                    SUM(CASE WHEN verdict = 'failed' THEN 1 ELSE 0 END) as failed_count
                FROM evaluations
                WHERE schedule_id = ? AND group_id = ?
            ");
            $summary_stmt->execute([$defense_schedule['schedule_id'], $thesis_group['group_id']]);
            $evaluation_summary = $summary_stmt->fetch();
        }
    } catch (PDOException $e) {
        error_log("Evaluation Fetch Error: " . $e->getMessage());
    }
}

// Determine overall result
$overall_result = null;
$overall_badge = '';
if ($evaluation_summary && $evaluation_summary['total_evaluations'] > 0) {
    $total = $evaluation_summary['total_evaluations'];
    $passed = $evaluation_summary['passed_count'];
    $failed = $evaluation_summary['failed_count'];
    $revision = $evaluation_summary['revision_count'];

    // Majority passed
    if ($passed > ($total / 2)) {
        $overall_result = 'Passed';
        $overall_badge = 'badge-success';
    }
    // Majority failed
    elseif ($failed > ($total / 2)) {
        $overall_result = 'Failed';
        $overall_badge = 'badge-danger';
    }
    // Revisions required or mixed
    else {
        $overall_result = 'Revisions Required';
        $overall_badge = 'badge-warning';
    }
}
// ADD THIS CODE in panelist/evaluations.php
// Place it AFTER the evaluation insert/update (around line 100-130)
// Look for: if (empty($errors)) {

if (empty($errors)) {
    try {
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
        }

        // Notify group members about new evaluation
        $members_stmt = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ?");
        $members_stmt->execute([$schedule_details['group_id']]);
        foreach ($members_stmt->fetchAll() as $member) {
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) VALUES (?, 'Evaluation Submitted', 'A panelist has submitted an evaluation for your defense.', 'evaluation', NOW())");
            $notif->execute([$member['user_id']]);
        }

        // Refresh evaluation
        $eval_check->execute([$schedule_id, $_SESSION['user_id'], $schedule_details['group_id']]);
        $existing_evaluation = $eval_check->fetch();
        
    } catch (PDOException $e) {
        error_log("Evaluation Submit Error: " . $e->getMessage());
        $errors[] = "An error occurred. Please try again.";
    }
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
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/topbar.php'; ?>

            <div class="dashboard-content">
                <?php if (!$thesis_group): ?>
                    <!-- No Group Message -->
                    <div class="content-card">
                        <div class="card-body">
                            <div class="empty-state">
                                <div class="icon">📝</div>
                                <h3>No Thesis Group</h3>
                                <p>You need to create or join a thesis group to view evaluations.</p>
                                <a href="thesis-group.php" class="btn btn-primary">Create Thesis Group</a>
                            </div>
                        </div>
                    </div>
                <?php elseif (!$defense_schedule): ?>
                    <!-- No Defense Yet -->
                    <div class="content-card">
                        <div class="card-body">
                            <div class="empty-state">
                                <div class="icon">📝</div>
                                <h3>No Completed Defense</h3>
                                <p>Evaluation results will appear here after your thesis defense is completed.</p>
                                <a href="schedule.php" class="btn btn-secondary">View Schedule</a>
                            </div>
                        </div>
                    </div>
                <?php elseif (empty($evaluations)): ?>
                    <!-- No Evaluations Yet -->
                    <div class="content-card">
                        <div class="card-body">
                            <div class="empty-state">
                                <div class="icon">⏳</div>
                                <h3>Evaluations Pending</h3>
                                <p>Your defense was completed on <?php echo date('F d, Y', strtotime($defense_schedule['defense_date'])); ?>.</p>
                                <p>Panelists are currently reviewing and will submit their evaluations soon.</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Evaluation Summary -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3>📊 Evaluation Summary</h3>
                            <?php if ($overall_result): ?>
                                <span class="badge <?php echo $overall_badge; ?>" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                    <?php echo $overall_result; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                                <div style="background: var(--bg-color); padding: 1rem; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 2rem; font-weight: 700; color: var(--primary-color);">
                                        <?php echo number_format($evaluation_summary['average_score'], 2); ?>
                                    </div>
                                    <div style="color: var(--text-secondary); font-size: 0.9rem;">Average Score</div>
                                </div>
                                <div style="background: var(--bg-color); padding: 1rem; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 2rem; font-weight: 700; color: var(--success-color);">
                                        <?php echo $evaluation_summary['passed_count']; ?>
                                    </div>
                                    <div style="color: var(--text-secondary); font-size: 0.9rem;">Passed</div>
                                </div>
                                <div style="background: var(--bg-color); padding: 1rem; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 2rem; font-weight: 700; color: var(--warning-color);">
                                        <?php echo $evaluation_summary['revision_count']; ?>
                                    </div>
                                    <div style="color: var(--text-secondary); font-size: 0.9rem;">Revisions Required</div>
                                </div>
                                <div style="background: var(--bg-color); padding: 1rem; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 2rem; font-weight: 700; color: var(--error-color);">
                                        <?php echo $evaluation_summary['failed_count']; ?>
                                    </div>
                                    <div style="color: var(--text-secondary); font-size: 0.9rem;">Failed</div>
                                </div>
                            </div>

                            <div style="background: #e0f2fe; padding: 1rem; border-radius: 8px; border-left: 4px solid var(--primary-color);">
                                <strong>Defense Details:</strong><br>
                                Date: <?php echo date('F d, Y', strtotime($defense_schedule['defense_date'])); ?> | 
                                Time: <?php echo date('h:i A', strtotime($defense_schedule['defense_time'])); ?> | 
                                Venue: <?php echo htmlspecialchars($defense_schedule['venue_name']); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Individual Evaluations -->
                    <div style="display: grid; gap: 1.5rem;">
                        <?php foreach ($evaluations as $eval): ?>
                            <div class="content-card">
                                <div class="card-header">
                                    <div>
                                        <h3 style="margin-bottom: 0.25rem;">
                                            👨‍🏫 <?php echo htmlspecialchars($eval['panelist_name']); ?>
                                        </h3>
                                        <?php if ($eval['title']): ?>
                                            <p style="margin: 0; font-size: 0.9rem; color: var(--text-secondary);">
                                                <?php echo htmlspecialchars($eval['title']); ?>
                                                <?php if ($eval['specialization']): ?>
                                                    | <?php echo htmlspecialchars($eval['specialization']); ?>
                                                <?php endif; ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: right;">
                                        <span class="badge badge-<?php echo $eval['role'] === 'chair' ? 'primary' : 'secondary'; ?>">
                                            <?php echo ucfirst($eval['role']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 1rem;">
                                        <div>
                                            <strong>Score:</strong>
                                            <div style="font-size: 1.5rem; font-weight: 700; color: var(--primary-color);">
                                                <?php echo $eval['score'] ? number_format($eval['score'], 2) : 'N/A'; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <strong>Verdict:</strong>
                                            <div style="margin-top: 0.25rem;">
                                                <span class="badge badge-<?php 
                                                    echo $eval['verdict'] === 'passed' ? 'success' : 
                                                         ($eval['verdict'] === 'failed' ? 'danger' : 'warning'); 
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $eval['verdict'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div>
                                            <strong>Evaluated:</strong>
                                            <div style="font-size: 0.9rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                                <?php echo date('M d, Y h:i A', strtotime($eval['evaluated_at'])); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 2px solid var(--border-color);">
                                        <strong>Comments & Feedback:</strong>
                                        <div style="background: var(--bg-color); padding: 1rem; border-radius: 8px; margin-top: 0.5rem; white-space: pre-wrap;">
                                            <?php echo nl2br(htmlspecialchars($eval['comments'])); ?>
                                        </div>
                                    </div>

                                    <?php if ($eval['file_path']): ?>
                                        <div style="margin-top: 1rem;">
                                            <a href="download-evaluation.php?id=<?php echo $eval['evaluation_id']; ?>" class="btn btn-secondary">
                                                📄 Download Evaluation Document
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Overall Recommendation -->
                    <?php if ($overall_result): ?>
                        <div class="content-card" style="margin-top: 1.5rem;">
                            <div class="card-header">
                                <h3>📋 Overall Recommendation</h3>
                            </div>
                            <div class="card-body">
                                <div style="background: <?php 
                                    echo $overall_result === 'Passed' ? '#d1fae5' : 
                                         ($overall_result === 'Failed' ? '#fee2e2' : '#fef3c7'); 
                                ?>; padding: 1.5rem; border-radius: 8px; text-align: center;">
                                    <div style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">
                                        <?php echo $overall_result; ?>
                                    </div>
                                    <p style="margin: 0; color: var(--text-secondary);">
                                        <?php if ($overall_result === 'Passed'): ?>
                                            Congratulations! Your thesis has been approved by the panel.
                                        <?php elseif ($overall_result === 'Failed'): ?>
                                            Your thesis requires substantial revisions. Please consult with your adviser.
                                        <?php else: ?>
                                            Your thesis requires revisions. Please address the panelists' comments and resubmit.
                                        <?php endif; ?>
                                    </p>
                                </div>

                                <?php if ($overall_result !== 'Passed'): ?>
                                    <div style="margin-top: 1rem; padding: 1rem; background: var(--bg-color); border-radius: 8px;">
                                        <strong>Next Steps:</strong>
                                        <ul style="margin: 0.5rem 0 0 1.5rem;">
                                            <li>Review all panelist comments carefully</li>
                                            <li>Address each concern raised by the panelists</li>
                                            <li>Make necessary revisions to your manuscript</li>
                                            <li>Consult with your thesis adviser</li>
                                            <li>Prepare for re-evaluation if required</li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>