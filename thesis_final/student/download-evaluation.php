<?php
/**
 * Evaluation Document Download Handler
 * Securely downloads evaluation documents
 */

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

// Get evaluation ID
$evaluation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$evaluation_id) {
    die("Invalid evaluation ID.");
}

try {
    // Get evaluation details and verify access
    $stmt = $pdo->prepare("
        SELECT e.*, tg.group_id
        FROM evaluations e
        INNER JOIN thesis_groups tg ON e.group_id = tg.group_id
        INNER JOIN group_members gm ON tg.group_id = gm.group_id
        WHERE e.evaluation_id = ? AND gm.user_id = ?
    ");
    $stmt->execute([$evaluation_id, $_SESSION['user_id']]);
    $evaluation = $stmt->fetch();

    if (!$evaluation) {
        die("Evaluation not found or you don't have permission to access it.");
    }

    if (!$evaluation['file_path']) {
        die("No file attached to this evaluation.");
    }

    // Check if file exists
    if (!file_exists($evaluation['file_path'])) {
        die("File not found on server.");
    }

    // Log download activity
    $log_stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, created_at) 
        VALUES (?, 'download_evaluation', 'evaluations', ?, ?, NOW())
    ");
    $log_stmt->execute([$_SESSION['user_id'], $evaluation_id, $_SERVER['REMOTE_ADDR']]);

    // Get file extension
    $file_ext = pathinfo($evaluation['file_path'], PATHINFO_EXTENSION);
    $filename = "evaluation_" . $evaluation_id . "." . $file_ext;

    // Set headers for download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($evaluation['file_path']));

    // Clear output buffer
    ob_clean();
    flush();

    // Read and output file
    readfile($evaluation['file_path']);
    exit();

} catch (PDOException $e) {
    error_log("Evaluation Download Error: " . $e->getMessage());
    die("An error occurred while downloading the file.");
}
?>