<?php
/**
 * Student Topbar Navigation - WITH AUTO-REFRESH NOTIFICATIONS
 * File: thesis_final/student/includes/topbar.php
 * FIXED: Real-time notification updates every 30 seconds
 */

// Get unread notifications count and recent notifications
try {
    $notif_stmt = $pdo->prepare("
        SELECT COUNT(*) as unread_count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $notif_stmt->execute([$_SESSION['user_id']]);
    $unread_count = $notif_stmt->fetch()['unread_count'];
    
    // Get recent notifications for dropdown
    $recent_notif = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $recent_notif->execute([$_SESSION['user_id']]);
    $recent_notifications = $recent_notif->fetchAll();
} catch (PDOException $e) {
    $unread_count = 0;
    $recent_notifications = [];
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
            <p><?php echo isset($page_subtitle) ? htmlspecialchars($page_subtitle) : 'Welcome to your portal'; ?></p>
        </div>
    </div>

    <div class="topbar-right">
        <div class="notification-wrapper">
            <button class="notification-icon" id="notifBell" onclick="toggleNotificationDropdown()">
                🔔
                <span class="notification-badge" id="notifBadge" style="<?php echo $unread_count > 0 ? '' : 'display: none;'; ?>"><?php echo $unread_count; ?></span>
            </button>
            
            <!-- NOTIFICATION DROPDOWN -->
            <div class="notification-dropdown" id="notificationDropdown" style="display: none;">
                <div class="notification-dropdown-header">
                    <h4>Notifications</h4>
                    <span class="badge badge-primary" id="notifHeaderBadge" style="<?php echo $unread_count > 0 ? '' : 'display: none;'; ?>"><?php echo $unread_count; ?> New</span>
                </div>
                <div class="notification-dropdown-body" id="notificationList">
                    <?php if (empty($recent_notifications)): ?>
                        <div class="notification-empty">
                            <p>No notifications</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_notifications as $notif): ?>
                            <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                                <div class="notification-content">
                                    <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                                    <p><?php echo htmlspecialchars(substr($notif['message'], 0, 80)) . (strlen($notif['message']) > 80 ? '...' : ''); ?></p>
                                    <small><?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="notification-dropdown-footer">
                    <a href="notifications.php" class="btn-view-all">View All Notifications</a>
                </div>
            </div>
        </div>

        <div class="user-info">
            <div class="user-avatar">
                <?php echo $initials; ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['name']); ?></span>
                <span class="user-role">Student</span>
            </div>
        </div>
    </div>
</div>

<style>
/* Notification Dropdown Styles */
.notification-wrapper {
    position: relative;
}

.notification-icon {
    background: none;
    border: none;
    cursor: pointer;
    position: relative;
    font-size: 1.5rem;
    padding: 0.5rem;
    transition: transform 0.2s ease;
}

.notification-icon:hover {
    transform: scale(1.1);
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background-color: var(--error-color);
    color: white;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: 600;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 0.5rem;
    width: 350px;
    max-height: 500px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    overflow: hidden;
}

.notification-dropdown-header {
    padding: 1rem;
    border-bottom: 2px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-color);
}

.notification-dropdown-header h4 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--text-primary);
}

.notification-dropdown-body {
    max-height: 400px;
    overflow-y: auto;
}

.notification-item {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    transition: background 0.2s ease;
    cursor: pointer;
}

.notification-item:hover {
    background: var(--bg-color);
}

.notification-item.unread {
    background: #e0f2fe;
}

.notification-item.unread:hover {
    background: #bae6fd;
}

.notification-content strong {
    display: block;
    margin-bottom: 0.25rem;
    color: var(--text-primary);
    font-size: 0.95rem;
}

.notification-content p {
    margin: 0.25rem 0;
    color: var(--text-secondary);
    font-size: 0.85rem;
    line-height: 1.4;
}

.notification-content small {
    color: var(--text-secondary);
    font-size: 0.75rem;
}

.notification-empty {
    padding: 2rem;
    text-align: center;
    color: var(--text-secondary);
}

.notification-dropdown-footer {
    padding: 0.75rem;
    border-top: 2px solid var(--border-color);
    background: var(--bg-color);
    text-align: center;
}

.btn-view-all {
    display: block;
    color: var(--primary-color);
    font-weight: 600;
    text-decoration: none;
    transition: color 0.2s ease;
}

.btn-view-all:hover {
    color: var(--primary-hover);
}

@media (max-width: 768px) {
    .notification-dropdown {
        width: 300px;
        right: -50px;
    }
}
</style>

<script>
// Mobile menu toggle
document.getElementById('menuToggle')?.addEventListener('click', function() {
    const sidebar = document.querySelector('.sidebar');
    sidebar.classList.toggle('active');
});

// Notification Dropdown Toggle
function toggleNotificationDropdown() {
    const dropdown = document.getElementById('notificationDropdown');
    const isVisible = dropdown.style.display === 'block';
    dropdown.style.display = isVisible ? 'none' : 'block';
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const notifWrapper = document.querySelector('.notification-wrapper');
    const dropdown = document.getElementById('notificationDropdown');
    
    if (notifWrapper && !notifWrapper.contains(e.target)) {
        dropdown.style.display = 'none';
    }
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

// AUTO-REFRESH NOTIFICATIONS - Fetch every 30 seconds
let lastNotificationCheck = Date.now();

function updateNotifications() {
    fetch('../ajax/get-notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update badge
                const badge = document.getElementById('notifBadge');
                const headerBadge = document.getElementById('notifHeaderBadge');
                
                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count;
                    badge.style.display = '';
                    headerBadge.textContent = data.unread_count + ' New';
                    headerBadge.style.display = '';
                } else {
                    badge.style.display = 'none';
                    headerBadge.style.display = 'none';
                }
                
                // Update notification list
                const notifList = document.getElementById('notificationList');
                if (data.notifications.length === 0) {
                    notifList.innerHTML = '<div class="notification-empty"><p>No notifications</p></div>';
                } else {
                    let html = '';
                    data.notifications.forEach(notif => {
                        html += `
                            <div class="notification-item ${notif.is_read == 0 ? 'unread' : ''}">
                                <div class="notification-content">
                                    <strong>${escapeHtml(notif.title)}</strong>
                                    <p>${escapeHtml(notif.message.substring(0, 80))}${notif.message.length > 80 ? '...' : ''}</p>
                                    <small>${notif.time_ago}</small>
                                </div>
                            </div>
                        `;
                    });
                    notifList.innerHTML = html;
                }
            }
        })
        .catch(error => {
            console.error('Notification fetch error:', error);
        });
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Update notifications every 30 seconds
setInterval(updateNotifications, 30000);

// Also update when page becomes visible again
document.addEventListener('visibilitychange', function() {
    if (!document.hidden) {
        updateNotifications();
    }
});

// Initial update after 5 seconds (to catch any new notifications after page load)
setTimeout(updateNotifications, 5000);
</script>