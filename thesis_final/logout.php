<?php
/**
 * Session Termination Handler
 * Safely logs out user and destroys session
 */

session_start();

require_once 'db_connect.php';

// Log logout activity if user is logged in
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, ip_address, created_at) 
            VALUES (?, 'logout', ?, NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $_SERVER['REMOTE_ADDR']]);
    } catch (PDOException $e) {
        error_log("Logout Log Error: " . $e->getMessage());
    }
}

// Unset all session variables
$_SESSION = [];

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to index page
header("Location: index.php");
exit();
?>