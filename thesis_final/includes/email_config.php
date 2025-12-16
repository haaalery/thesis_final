<?php
/**
 * Email Configuration for PHPMailer
 * File: thesis_final/includes/email_config.php
 * 
 * SETUP INSTRUCTIONS:
 * 1. Replace 'your-email@gmail.com' with your actual Gmail address (2 places)
 * 2. Replace 'your-app-password' with your 16-character app password from Google
 * 3. Save this file
 */

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// ========================================
// 🔧 CHANGE THESE 3 LINES WITH YOUR INFO
// ========================================
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'kittearnings@gmail.com');  // ⚠️ CHANGE THIS
define('SMTP_PASSWORD', 'gxxsrwbykqkpecuy');      // ⚠️ CHANGE THIS (16 chars, no spaces)
define('SMTP_FROM_EMAIL', 'kittearnings@gmail.com'); // ⚠️ CHANGE THIS (same as SMTP_USERNAME)
define('SMTP_FROM_NAME', 'Thesis Panel Scheduling System');

/**
 * Create and configure PHPMailer instance
 */
function getMailer() {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // Sender
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Content settings
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        
        return $mail;
    } catch (Exception $e) {
        error_log("PHPMailer Configuration Error: {$e->getMessage()}");
        return null;
    }
}

/**
 * Send email notification
 */
function sendEmail($to_email, $to_name, $subject, $body) {
    $mail = getMailer();
    
    if (!$mail) {
        error_log("❌ Failed to create mailer instance");
        return false;
    }
    
    try {
        $mail->addAddress($to_email, $to_name);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        $mail->send();
        error_log("✅ Email sent to: $to_email - Subject: $subject");
        return true;
    } catch (Exception $e) {
        error_log("❌ Email sending failed to $to_email: {$mail->ErrorInfo}");
        return false;
    }
}