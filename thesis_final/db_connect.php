<?php
/**
 * Database Connection Handler
 * Centralized PDO connection for all pages
 */

// Database configuration constants
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'thesis_final');

try {
    // Create PDO instance with error handling
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    // Log error and display user-friendly message
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please contact the system administrator.");
}
?>