<?php
/**
 * Panelist Topbar Navigation
 * Displays top navigation bar with user info and notifications
 */

// Get unread notifications count
try {
    $notif_stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $notif_stmt->execute([$_SESSION['user_id']]);
    $unread_count = $notif_stmt->fetch()['unread_count'];
} catch (PDOException $e) {
    $unread_count = 0;
    error_log("Notification Count Error: " . $e->getMessage());
}

// Get user initials for avatar
$name_parts = explode(' ', $_SESSION['name']);
$initials = strtoupper(substr($name_parts[0], 0, 1));
if (isset($name_parts[1])) {
    $initials .= strtoupper(substr($name_parts[1], 0, 1));
}
?>
<div class="topbar">
    <div class="topbar-left">
        <button class="menu-toggle" id="menuToggle">
            ☰
        </button>
        <div>
            <h1><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dashboard'; ?></h1>
            <p><?php echo isset($page_subtitle) ? htmlspecialchars($page_subtitle) : 'Welcome to your panelist portal'; ?></p>
        </div>
    </div>

    <div class="topbar-right">
        <a href="notifications.php" class="notification-icon">
            🔔
            <?php if ($unread_count > 0): ?>
                <span class="notification-badge"><?php echo $unread_count; ?></span>
            <?php endif; ?>
        </a>

        <div class="user-info">
            <div class="user-avatar">
                <?php echo $initials; ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <span class="user-role">Panelist</span>
            </div>
        </div>
    </div>
</div>

<script>
// Mobile menu toggle
document.getElementById('menuToggle')?.addEventListener('click', function() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('active');
});

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(e) {
    const sidebar = document.querySelector('.sidebar');
    const menuToggle = document.getElementById('menuToggle');
    
    if (window.innerWidth <= 1024) {
        if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
            sidebar.classList.remove('active');
        }
    }
});
</script>