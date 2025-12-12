<?php
/**
 * Admin Sidebar Navigation
 * File: thesis_final/admin/includes/sidebar.php
 */

$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <h2>🛠️ Admin Portal</h2>
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
                <a href="manage-users.php" class="<?php echo $current_page === 'manage-users.php' ? 'active' : ''; ?>">
                    <span class="icon">👥</span>
                    Manage Users
                </a>
            </li>
            <li>
                <a href="manage-thesis.php" class="<?php echo $current_page === 'manage-thesis.php' ? 'active' : ''; ?>">
                    <span class="icon">📚</span>
                    Manage Thesis
                </a>
            </li>
            <li>
                <a href="manage-schedules.php" class="<?php echo $current_page === 'manage-schedules.php' ? 'active' : ''; ?>">
                    <span class="icon">📅</span>
                    Manage Schedules
                </a>
            </li>
            <li>
                <a href="schedule-requests.php" class="<?php echo $current_page === 'schedule-requests.php' ? 'active' : ''; ?>">
                    <span class="icon">⏳</span>
                    Schedule Requests
                </a>
            </li>
            <li>
                <a href="manage-venues.php" class="<?php echo $current_page === 'manage-venues.php' ? 'active' : ''; ?>">
                    <span class="icon">📍</span>
                    Manage Venues
                </a>
            </li>
            <li>
                <a href="assign-panelists.php" class="<?php echo $current_page === 'assign-panelists.php' ? 'active' : ''; ?>">
                    <span class="icon">👨‍🏫</span>
                    Assign Panelists
                </a>
            </li>
            <li>
                <a href="reports.php" class="<?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                    <span class="icon">📈</span>
                    Reports
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