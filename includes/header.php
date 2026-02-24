<?php
/**
 * Header with Role-Based Sidebar Navigation
 * St. Dominic Savio College - Visitor Management System
 */

// Include appropriate sidebar based on user role
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    switch ($_SESSION['user_role']) {
        case ROLE_ADMIN:
        case ROLE_SUPERVISOR:
            include_once __DIR__ . '/admin-sidebar.php';
            break;
        
        case ROLE_GUARD:
            include_once __DIR__ . '/guard-sidebar.php';
            break;
        
        default:
            // Unknown role - show minimal header with logout
            ?>
            <nav class="navbar navbar-expand-lg navbar-dark bg-secondary fixed-top">
                <div class="container-fluid">
                    <a class="navbar-brand" href="../index.php">
                        <i class="fas fa-home me-2"></i>
                        <?php echo APP_NAME; ?>
                    </a>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="logoutUser(); return false;">
                                <i class="fas fa-sign-out-alt me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            <div style="margin-top: 80px;"></div>
            <script>
            function logoutUser() {
                if (confirm('Are you sure you want to logout?')) {
                    fetch('ajax/logout-ajax.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'}
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = data.redirect;
                        } else {
                            window.location.href = 'logout.php';
                        }
                    })
                    .catch(() => window.location.href = 'logout.php');
                }
            }
            </script>
            <?php
            break;
    }
} else {
    // User not logged in - show basic header
    ?>
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-home me-2"></i>
                <?php echo APP_NAME; ?>
            </a>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../admin/login.php">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </a>
                </li>
            </ul>
        </div>
    </nav>
    <?php
}
?>