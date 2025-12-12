<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'panelist') {
    die("Unauthorized");
}

require_once '../db_connect.php';
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$document_id) die("Invalid document ID.");

try {
    $stmt = $pdo->prepare("
        SELECT td.*
        FROM thesis_documents td
        INNER JOIN defense_schedules ds ON td.group_id = ds.group_id
        INNER JOIN panel_assignments pa ON ds.schedule_id = pa.schedule_id
        WHERE td.document_id = ? AND pa.panelist_id = ?
    ");
    $stmt->execute([$document_id, $_SESSION['user_id']]);
    $document = $stmt->fetch();

    if (!$document || !file_exists($document['file_path'])) {
        die("Document not found.");
    }

    $pdo->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, created_at) VALUES (?, 'download_document', 'thesis_documents', ?, ?, NOW())")->execute([$_SESSION['user_id'], $document_id, $_SERVER['REMOTE_ADDR']]);

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($document['file_name']) . '"');
    header('Content-Length: ' . filesize($document['file_path']));
    ob_clean();
    flush();
    readfile($document['file_path']);
    exit();
} catch (PDOException $e) {
    die("Error downloading file.");
}
?>