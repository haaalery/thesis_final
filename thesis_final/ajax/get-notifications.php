<?php
/**
 * FIXED AJAX Notification Fetcher
 * File: thesis_final/ajax/get-notifications.php
 */

session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'success' => false]);
    exit();
}

require_once '../db_connect.php';

try {
    // Get unread count
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $count_stmt->execute([$_SESSION['user_id']]);
    $unread_count = $count_stmt->fetch()['unread_count'];
    
    // Get recent notifications (last 10)
    $notif_stmt = $pdo->prepare("
        SELECT notification_id, title, message, type, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $notif_stmt->execute([$_SESSION['user_id']]);
    $notifications = $notif_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format timestamps
    foreach ($notifications as &$notif) {
        $time_diff = time() - strtotime($notif['created_at']);
        
        if ($time_diff < 60) {
            $notif['time_ago'] = 'Just now';
        } elseif ($time_diff < 3600) {
            $minutes = floor($time_diff / 60);
            $notif['time_ago'] = $minutes . ' min' . ($minutes > 1 ? 's' : '') . ' ago';
        } elseif ($time_diff < 86400) {
            $hours = floor($time_diff / 3600);
            $notif['time_ago'] = $hours . ' hr' . ($hours > 1 ? 's' : '') . ' ago';
        } else {
            $days = floor($time_diff / 86400);
            $notif['time_ago'] = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'unread_count' => (int)$unread_count,
        'notifications' => $notifications
    ]);
    
} catch (PDOException $e) {
    error_log("❌ Notification Fetch Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'success' => false
    ]);
}