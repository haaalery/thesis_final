<?php
/**
 * Panelist Sidebar Navigation
 * Displays navigation menu for panelist dashboard
 */

// Get current page for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <h2>👨‍🏫 Panelist Portal</h2>
        <p><?php echo htmlspecialchars($_SESSION['name']); ?></p>
    </div>

    <nav class="sidebar-nav">
        <ul>
            <li>
                <a href="dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="icon">📊</span>
                    Dashboard
                </a>
            </li>
            <li>
                <a href="profile.php" class="<?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
                    <span class="icon">👤</span>
                    My Profile
                </a>
            </li>
            <li>
                <a href="assignments.php" class="<?php echo $current_page === 'assignments.php' ? 'active' : ''; ?>">
                    <span class="icon">📋</span>
                    Panel Assignments
                </a>
            </li>
            <li>
                <a href="schedule.php" class="<?php echo $current_page === 'schedule.php' ? 'active' : ''; ?>">
                    <span class="icon">📅</span>
                    Defense Schedule
                </a>
            </li>
            <li>
                <a href="evaluations.php" class="<?php echo $current_page === 'evaluations.php' ? 'active' : ''; ?>">
                    <span class="icon">📝</span>
                    Evaluations
                </a>
            </li>
            <li>
                <a href="documents.php" class="<?php echo $current_page === 'documents.php' ? 'active' : ''; ?>">
                    <span class="icon">📄</span>
                    Thesis Documents
                </a>
            </li>
            <li>
                <a href="notifications.php" class="<?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">
                    <span class="icon">🔔</span>
                    Notifications
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <a href="../logout.php">
            <span class="icon">🚪</span>
            Logout
        </a>
    </div>
</aside>