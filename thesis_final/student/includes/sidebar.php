<?php
/**
 * Student Sidebar Navigation
 * Displays navigation menu for student dashboard
 */

// Get current page for active link highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <h2>🎓 Student Portal</h2>
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
                <a href="thesis-group.php" class="<?php echo $current_page === 'thesis-group.php' ? 'active' : ''; ?>">
                    <span class="icon">👥</span>
                    Thesis Group
                </a>
            </li>
            <li>
                <a href="documents.php" class="<?php echo $current_page === 'documents.php' ? 'active' : ''; ?>">
                    <span class="icon">📄</span>
                    Documents
                </a>
            </li>
            <li>
                <a href="schedule.php" class="<?php echo $current_page === 'schedule.php' ? 'active' : ''; ?>">
                    <span class="icon">📅</span>
                    Defense Schedule
                </a>
            </li>
            <li>
                <a href="evaluation.php" class="<?php echo $current_page === 'evaluation.php' ? 'active' : ''; ?>">
                    <span class="icon">📝</span>
                    Evaluations
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