<?php
/**
 * Document Download Handler
 * Securely downloads thesis documents
 */

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

// Get document ID
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$document_id) {
    die("Invalid document ID.");
}

try {
    // Get document details and verify access
    $stmt = $pdo->prepare("
        SELECT td.*, tg.group_id
        FROM thesis_documents td
        INNER JOIN thesis_groups tg ON td.group_id = tg.group_id
        INNER JOIN group_members gm ON tg.group_id = gm.group_id
        WHERE td.document_id = ? AND gm.user_id = ?
    ");
    $stmt->execute([$document_id, $_SESSION['user_id']]);
    $document = $stmt->fetch();

    if (!$document) {
        die("Document not found or you don't have permission to access it.");
    }

    // Check if file exists
    if (!file_exists($document['file_path'])) {
        die("File not found on server.");
    }

    // Log download activity
    $log_stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, created_at) 
        VALUES (?, 'download_document', 'thesis_documents', ?, ?, NOW())
    ");
    $log_stmt->execute([$_SESSION['user_id'], $document_id, $_SERVER['REMOTE_ADDR']]);

    // Set headers for download
    header('Content-Description: File Transfer');
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($document['file_name']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($document['file_path']));

    // Clear output buffer
    ob_clean();
    flush();

    // Read and output file
    readfile($document['file_path']);
    exit();

} catch (PDOException $e) {
    error_log("Download Error: " . $e->getMessage());
    die("An error occurred while downloading the file.");
}
?>