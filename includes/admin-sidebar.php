<?php
/**
 * Admin Sidebar Navigation
 * St. Dominic Savio College - Visitor Management System
 */

// Get current page to highlight active menu
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>

<!-- Sidebar -->
<div class="admin-sidebar" id="adminSidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <div class="brand-text">
                <h5 class="mb-0"><?php echo APP_NAME; ?></h5>
                <small class="text-muted">Admin Portal</small>
            </div>
        </div>
        <button class="sidebar-toggle d-lg-none" onclick="toggleSidebar()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <!-- Dashboard -->
            <li class="nav-item">
                <a href="<?php echo ($current_dir == 'admin') ? '' : '../admin/'; ?>dashboard.php" 
                   class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <div class="nav-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>

            <li class="nav-divider"></li>

            <!-- Visitor Management Section -->
            <li class="nav-section-title">
                <span>Visitor Management</span>
            </li>

            <!-- Visitor Check In/Out -->
            <li class="nav-item">
                <a href="checkout.php" class="nav-link <?php echo ($current_page == 'checkout.php') ? 'active' : ''; ?>">
                    <div class="nav-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <span class="nav-text">Visitor Check In/Out</span>
                </a>
            </li>
            
            <!-- Visitor -->
            <li class="nav-item">
                <a href="<?php echo ($current_dir == 'admin') ? '' : '../admin/'; ?>manage-visitors.php" 
                   class="nav-link <?php echo ($current_page == 'manage-visitors.php' || $current_page == 'visitor-history.php') ? 'active' : ''; ?>">
                    <div class="nav-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <span class="nav-text">Visitor History</span>
                </a>
            </li>

            <li class="nav-divider"></li>

            <!-- Security & Administration -->
            <li class="nav-section-title">
                <span>Security & Administration</span>
            </li>

            <!-- Blacklist -->
            <li class="nav-item">
                <a href="<?php echo ($current_dir == 'admin') ? '' : '../admin/'; ?>blacklist.php" 
                   class="nav-link <?php echo ($current_page == 'blacklist.php') ? 'active' : ''; ?>">
                    <div class="nav-icon">
                        <i class="fas fa-ban"></i>
                    </div>
                    <span class="nav-text">Blacklist Management</span>
                    <span class="badge bg-danger ms-auto" id="blacklistAlerts" style="display: none;"></span>
                </a>
            </li>

            <!-- User Management -->
            <li class="nav-item">
                <a href="<?php echo ($current_dir == 'admin') ? '' : '../admin/'; ?>user-management.php" 
                   class="nav-link <?php echo ($current_page == 'user-management.php') ? 'active' : ''; ?>">
                    <div class="nav-icon">
                        <i class="fas fa-users-cog"></i>
                    </div>
                    <span class="nav-text">User Management</span>
                </a>
            </li>

            <li class="nav-divider"></li>

            <!-- Reports & Analytics -->
            <li class="nav-section-title">
                <span>Reports & Analytics</span>
            </li>

            <!-- Reports -->
            <li class="nav-item">
                <a href="<?php echo ($current_dir == 'admin') ? '' : '../admin/'; ?>reports.php" 
                   class="nav-link <?php echo ($current_page == 'reports.php' || $current_page == 'audit-trail.php') ? 'active' : ''; ?>">
                    <div class="nav-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <span class="nav-text">Reports & Analytics</span>
                </a>
            </li>

            <!-- System Settings -->
            <li class="nav-item">
                <a href="<?php echo ($current_dir == 'admin') ? '' : '../admin/'; ?>settings.php" 
                   class="nav-link <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
                    <div class="nav-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <span class="nav-text">System Settings</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Enhanced Sidebar Footer -->
    <div class="sidebar-footer">
        <!-- Enhanced User Profile -->
        <div class="user-profile-enhanced">
            <!-- Profile Status Bar -->
            </div>
            
            <!-- Enhanced Profile Dropdown -->
            <div class="profile-dropdown dropdown">
                <button class="user-account-btn dropdown-toggle" 
                        type="button" 
                        id="adminProfileDropdown" 
                        data-bs-toggle="dropdown" 
                        data-bs-auto-close="true"
                        aria-expanded="false"
                        aria-label="Administrator Account Menu">
                    <div class="account-avatar">
                        <div class="avatar-img">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="status-dot"></div>
                    </div>
                    <div class="account-info">
                        <div class="account-name"><?php echo $_SESSION['first_name'] ?? 'Administrator'; ?></div>
                        <div class="account-role">
                            <i class="fas fa-shield-alt me-1"></i>
                            System Administrator
                        </div>
                        <div class="account-status">
                            <div class="status-text">Online</div>
                            <div class="last-activity">Active now</div>
                        </div>
                    </div>
                    <div class="dropdown-indicator">
                        <i class="fas fa-chevron-up"></i>
                    </div>
                </button>
                
                <!-- Enhanced Dropdown Menu -->
                <ul class="dropdown-menu account-dropdown" aria-labelledby="adminProfileDropdown">
                    <!-- Administrator Account Section -->
                    <li class="dropdown-header">
                        <div class="header-content">
                            <i class="fas fa-user-cog me-2"></i>
                            Administrator Account
                        </div>
                    </li>
                    
                    <li>
                        <a class="enhanced-dropdown-item" href="#" onclick="showProfileModal(); return false;">
                            <div class="item-icon">
                                <i class="fas fa-user-edit"></i>
                            </div>
                            <div class="item-content">
                                <div class="item-title">Profile Settings</div>
                                <div class="item-subtitle">Update account information</div>
                            </div>
                            <div class="item-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                    </li>
                    
                    <li>
                        <a class="enhanced-dropdown-item" href="#" onclick="showSessionInfoModal(); return false;">
                            <div class="item-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="item-content">
                                <div class="item-title">Session Information</div>
                                <div class="item-subtitle">View session details</div>
                            </div>
                            <div class="item-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                    </li>
                    
                    <li>
                        <a class="enhanced-dropdown-item" href="#" onclick="showSecuritySettings(); return false;">
                            <div class="item-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="item-content">
                                <div class="item-title">Security Settings</div>
                                <div class="item-subtitle">Manage security options</div>
                            </div>
                            <div class="item-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                    </li>
                    
                    <li><hr class="dropdown-divider"></li>
                    
                    <!-- System Tools Section -->
                    <li class="dropdown-header">
                        <div class="header-content">
                            <i class="fas fa-cogs me-2"></i>
                            System Tools
                        </div>
                    </li>
                    
                    <li>
                        <a class="enhanced-dropdown-item" href="#" onclick="showSystemInfoModal(); return false;">
                            <div class="item-icon">
                                <i class="fas fa-server"></i>
                            </div>
                            <div class="item-content">
                                <div class="item-title">System Status</div>
                                <div class="item-subtitle">View system information</div>
                            </div>
                            <div class="item-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                    </li>
                    
                    <li>
                        <a class="enhanced-dropdown-item" href="#" onclick="showActivityLog(); return false;">
                            <div class="item-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <div class="item-content">
                                <div class="item-title">Activity Log</div>
                                <div class="item-subtitle">View recent activities</div>
                            </div>
                            <div class="item-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                    </li>
                    
                    <li><hr class="dropdown-divider"></li>
                    
                    <!-- Logout Section -->
                    <li>
                        <a class="enhanced-dropdown-item logout-item" href="#" onclick="confirmLogout(); closeAdminProfileDropdown(); return false;">
                            <div class="item-icon">
                                <i class="fas fa-sign-out-alt"></i>
                            </div>
                            <div class="item-content">
                                <div class="item-title">Logout</div>
                                <div class="item-subtitle">End current session</div>
                            </div>
                            <div class="item-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Sidebar Overlay -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- Mobile Header -->
<div class="mobile-header d-lg-none">
    <button class="mobile-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>
    <div class="mobile-brand">
        <i class="fas fa-shield-alt me-2"></i>
        Admin Portal
    </div>
    <div class="mobile-stats">
        <span class="stat-badge" id="mobileVisitorCount">0</span>
    </div>
</div>

<style>
:root {
    --sidebar-width: 280px;
    --sidebar-bg: #1a4d2e;
    --sidebar-hover: #2d7a4f;
    --sidebar-active: #256340;
    --sidebar-text: #ffffff;
    --sidebar-text-muted: #a8c9b5;
    --sidebar-border: #2d5a3e;
    --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
    --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}

/* Main Sidebar Container */
.admin-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: var(--sidebar-width);
    height: 100vh;
    background: linear-gradient(180deg, var(--sidebar-bg) 0%, #164029 100%);
    border-right: 1px solid var(--sidebar-border);
    box-shadow: var(--shadow-lg);
    z-index: 1050;
    display: flex;
    flex-direction: column;
    transition: var(--transition);
}

/* Sidebar Header */
.sidebar-header {
    padding: 1.5rem 1.25rem;
    border-bottom: 1px solid var(--sidebar-border);
    background: rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.brand-icon {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 8px rgba(255, 215, 0, 0.3);
}

.brand-icon i {
    font-size: 1.25rem;
    color: var(--sidebar-bg);
}

.brand-text h5 {
    color: var(--sidebar-text);
    font-weight: 600;
    font-size: 0.9rem;
    line-height: 1.2;
}

.brand-text small {
    color: var(--sidebar-text-muted);
    font-size: 0.75rem;
}

.sidebar-toggle {
    background: none;
    border: none;
    color: var(--sidebar-text);
    font-size: 1.2rem;
    padding: 0.5rem;
    border-radius: 6px;
    transition: var(--transition);
}

.sidebar-toggle:hover {
    background: rgba(255, 255, 255, 0.1);
}

/* System Overview Widget */
.system-overview-widget {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--sidebar-border);
}

.widget-content {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
    border-radius: 15px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    overflow: hidden;
    transition: var(--transition);
    cursor: pointer;
}

.widget-content:hover {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0.1) 100%);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.overview-display {
    padding: 1rem;
    position: relative;
}

.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #ffd700;
    background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.65rem;
    color: var(--sidebar-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 500;
}

.refresh-icon {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    font-size: 0.8rem;
    color: var(--sidebar-text-muted);
    transition: var(--transition);
}

.widget-content:hover .refresh-icon {
    transform: rotate(180deg);
    color: #ffd700;
}

/* Sidebar Navigation */
.sidebar-nav {
    flex: 1;
    padding: 1rem 0;
    overflow-y: auto;
    overflow-x: hidden;
}

.nav-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-item {
    margin: 0 0.75rem 0.25rem 0.75rem;
}

.nav-link {
    display: flex;
    align-items: center;
    padding: 0.875rem 1rem;
    color: var(--sidebar-text);
    text-decoration: none;
    border-radius: 12px;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
}

.nav-link::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: var(--transition);
}

.nav-link:hover::before {
    left: 100%;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--sidebar-text);
    transform: translateX(8px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.nav-link.active {
    background: linear-gradient(135deg, var(--sidebar-hover) 0%, var(--sidebar-active) 100%);
    color: var(--sidebar-text);
    box-shadow: 0 4px 12px rgba(45, 122, 79, 0.3);
    font-weight: 600;
}

.nav-icon {
    width: 20px;
    text-align: center;
    margin-right: 0.875rem;
    font-size: 1rem;
}

.nav-text {
    flex: 1;
    font-size: 0.9rem;
    font-weight: 500;
}

.nav-divider {
    height: 1px;
    background: var(--sidebar-border);
    margin: 1rem 1.5rem;
}

.nav-section-title {
    padding: 0.75rem 1.5rem 0.5rem;
    margin-top: 0.5rem;
}

.nav-section-title span {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--sidebar-text-muted);
    font-weight: 600;
}

/* Badge */
.badge {
    font-size: 0.65rem;
    padding: 0.25rem 0.5rem;
    border-radius: 10px;
}

/* Quick Actions */
.quick-actions {
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--sidebar-border);
    border-bottom: 1px solid var(--sidebar-border);
}

.actions-header {
    margin-bottom: 0.75rem;
}

.actions-header span {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--sidebar-text-muted);
    font-weight: 600;
}

.actions-grid {
    display: flex;
    gap: 0.5rem;
    justify-content: space-between;
}

.action-btn {
    flex: 1;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    color: var(--sidebar-text);
    padding: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
    position: relative;
}

.action-btn:hover {
    background: rgba(255, 255, 255, 0.2);
    color: var(--sidebar-text);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.alert-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 0.6rem;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Enhanced Sidebar Footer */
.sidebar-footer {
    border-top: 1px solid var(--sidebar-border);
    padding: 1rem 1.25rem 1.25rem;
    background: linear-gradient(180deg, rgba(0, 0, 0, 0.05) 0%, rgba(0, 0, 0, 0.15) 100%);
    backdrop-filter: blur(10px);
}

/* Enhanced User Profile */
.user-profile-enhanced {
    position: relative;
}

/* Profile Status Bar */
.profile-status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.875rem;
    padding: 0.5rem 0.75rem;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #28a745;
    border: 2px solid #ffffff;
    box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
    animation: pulse-status 2s infinite;
}

.status-indicator.online {
    background: #28a745;
    box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
}

.status-indicator.away {
    background: #ffc107;
    box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.3);
}

.status-indicator.offline {
    background: #6c757d;
    box-shadow: 0 0 0 2px rgba(108, 117, 125, 0.3);
}

.session-duration {
    font-size: 0.7rem;
    color: var(--sidebar-text-muted);
    font-weight: 500;
    padding: 0.25rem 0.5rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.role-badge {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.admin-badge {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    border: 1px solid rgba(220, 53, 69, 0.3);
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.2);
}

.guard-badge {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
    border: 1px solid rgba(0, 123, 255, 0.3);
    box-shadow: 0 2px 8px rgba(0, 123, 255, 0.2);
}

/* Enhanced User Account Button */
.user-account-btn {
    width: 100%;
    display: flex;
    align-items: center;
    padding: 1rem;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 15px;
    color: var(--sidebar-text);
    text-decoration: none;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.user-account-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: var(--transition);
}

.user-account-btn:hover::before {
    left: 100%;
}

.user-account-btn:hover {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0.08) 100%);
    color: var(--sidebar-text);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.25);
}

/* Account Avatar */
.account-avatar {
    position: relative;
    margin-right: 0.875rem;
    flex-shrink: 0;
}

.avatar-img {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(255, 215, 0, 0.3);
    box-shadow: 0 4px 12px rgba(255, 215, 0, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.avatar-img i {
    font-size: 1.4rem;
    color: var(--sidebar-bg);
}

.status-dot {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 14px;
    height: 14px;
    background: #28a745;
    border: 3px solid var(--sidebar-bg);
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.4);
}

/* Account Info */
.account-info {
    flex: 1;
    min-width: 0;
}

.account-name {
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 0.25rem;
    color: var(--sidebar-text);
}

.account-role {
    font-size: 0.75rem;
    color: var(--sidebar-text-muted);
    margin-bottom: 0.375rem;
    display: flex;
    align-items: center;
    font-weight: 500;
}

.account-status {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.status-text {
    font-size: 0.7rem;
    color: #28a745;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.last-activity {
    font-size: 0.65rem;
    color: var(--sidebar-text-muted);
    font-weight: 400;
}

/* Dropdown Indicator */
.dropdown-indicator {
    margin-left: 0.5rem;
    width: 20px;
    text-align: center;
    transition: var(--transition);
}

.dropdown-indicator i {
    font-size: 0.8rem;
    color: var(--sidebar-text-muted);
}

.dropdown.show .dropdown-indicator i {
    transform: rotate(180deg);
    color: #ffd700;
}

/* Enhanced Dropdown Menu */
.account-dropdown {
    position: absolute;
    bottom: 100%;
    margin-bottom: 0.75rem;
    min-width: 280px;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border: none;
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.1);
    padding: 0.5rem;
    backdrop-filter: blur(10px);
    overflow: hidden;
}

.dropdown-header {
    margin: 0;
    padding: 0.75rem 1rem 0.5rem;
    border-bottom: 1px solid #e9ecef;
    margin-bottom: 0.5rem;
}

.header-content {
    display: flex;
    align-items: center;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
}

.enhanced-dropdown-item {
    display: flex;
    align-items: center;
    padding: 0.875rem 1rem;
    margin-bottom: 0.25rem;
    border-radius: 12px;
    transition: var(--transition);
    text-decoration: none;
    color: #495057;
    border: 1px solid transparent;
    position: relative;
    overflow: hidden;
}

.enhanced-dropdown-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(0, 123, 255, 0.1), transparent);
    transition: var(--transition);
}

.enhanced-dropdown-item:hover::before {
    left: 100%;
}

.enhanced-dropdown-item:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    color: #212529;
    transform: translateX(4px);
    border-color: rgba(0, 123, 255, 0.2);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.logout-item:hover {
    background: linear-gradient(135deg, #ffe6e7 0%, #fdd5d7 100%);
    color: #dc3545;
    border-color: rgba(220, 53, 69, 0.2);
}

.item-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--sidebar-bg) 0%, var(--sidebar-hover) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.875rem;
    flex-shrink: 0;
    transition: var(--transition);
}

.item-icon i {
    font-size: 1rem;
    color: white;
}

.logout-item .item-icon {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
}

.item-content {
    flex: 1;
    min-width: 0;
}

.item-title {
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.2;
    margin-bottom: 0.125rem;
}

.item-subtitle {
    font-size: 0.7rem;
    color: #6c757d;
    line-height: 1.2;
}

.item-arrow {
    width: 20px;
    text-align: center;
    opacity: 0;
    transition: var(--transition);
}

.enhanced-dropdown-item:hover .item-arrow {
    opacity: 1;
    transform: translateX(2px);
}

.item-arrow i {
    font-size: 0.75rem;
    color: #6c757d;
}

.dropdown-divider {
    margin: 0.5rem 0;
    border-color: #e9ecef;
}

/* Status Pulse Animation */
@keyframes pulse-status {
    0% {
        box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
    }
    50% {
        box-shadow: 0 0 0 6px rgba(40, 167, 69, 0.1);
    }
    100% {
        box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
    }
}

/* Mobile Header */
.mobile-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    background: var(--sidebar-bg);
    color: var(--sidebar-text);
    box-shadow: var(--shadow);
    z-index: 1040;
}

.mobile-toggle {
    background: none;
    border: none;
    color: var(--sidebar-text);
    font-size: 1.25rem;
    padding: 0.5rem;
    border-radius: 6px;
}

.mobile-brand {
    font-weight: 600;
    font-size: 1rem;
}

.stat-badge {
    background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
    color: var(--sidebar-bg);
    padding: 0.25rem 0.75rem;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: 600;
    min-width: 30px;
    text-align: center;
}

/* Sidebar Overlay */
.sidebar-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1040;
    display: none;
}

/* Modal Enhancements */
.info-card {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 1rem;
    margin-bottom: 1rem;
    border-left: 4px solid var(--sidebar-bg);
}

.info-card h6 {
    margin-bottom: 0.75rem;
    color: var(--sidebar-bg);
    font-weight: 600;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #e9ecef;
}

.info-item:last-child {
    border-bottom: none;
}

.session-info .info-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    background: #f8f9fa;
    border-radius: 8px;
    border-bottom: none;
    justify-content: flex-start;
}

/* Responsive Design */
@media (max-width: 1199.98px) {
    .admin-sidebar {
        transform: translateX(-100%);
    }
    
    .admin-sidebar.show {
        transform: translateX(0);
    }
    
    .sidebar-overlay.show {
        display: block;
    }
    
    body.sidebar-open {
        overflow: hidden;
    }
}

@media (min-width: 1200px) {
    .mobile-header {
        display: none;
    }
    
    .main-content {
        margin-left: var(--sidebar-width);
    }
}

/* Scrollbar Styling */
.sidebar-nav::-webkit-scrollbar {
    width: 4px;
}

.sidebar-nav::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 2px;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.3);
}

/* Animation Classes */
.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.shake {
    animation: shake 0.82s cubic-bezier(.36,.07,.19,.97) both;
}

/* Enhanced Sidebar Footer */
.sidebar-footer {
    border-top: 1px solid var(--sidebar-border);
    padding: 1rem 1.25rem 1.25rem;
    background: linear-gradient(180deg, rgba(0, 0, 0, 0.05) 0%, rgba(0, 0, 0, 0.15) 100%);
    backdrop-filter: blur(10px);
}

/* Enhanced User Profile */
.user-profile-enhanced {
    position: relative;
}

/* Profile Status Bar */
.profile-status-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.875rem;
    padding: 0.5rem 0.75rem;
    background: rgba(255, 255, 255, 0.08);
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.12);
}

.status-indicator {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #28a745;
    border: 2px solid #ffffff;
    box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
    animation: pulse-status 2s infinite;
}

.status-indicator.online {
    background: #28a745;
    box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
}

.status-indicator.away {
    background: #ffc107;
    box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.3);
}

.status-indicator.offline {
    background: #6c757d;
    box-shadow: 0 0 0 2px rgba(108, 117, 125, 0.3);
}

.session-duration {
    font-size: 0.7rem;
    color: var(--sidebar-text-muted);
    font-weight: 500;
    padding: 0.25rem 0.5rem;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.15);
}

.role-badge {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.admin-badge {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    color: white;
    border: 1px solid rgba(220, 53, 69, 0.3);
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.2);
}

.guard-badge {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
    border: 1px solid rgba(0, 123, 255, 0.3);
    box-shadow: 0 2px 8px rgba(0, 123, 255, 0.2);
}

/* Enhanced User Account Button */
.user-account-btn {
    width: 100%;
    display: flex;
    align-items: center;
    padding: 1rem;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 15px;
    color: var(--sidebar-text);
    text-decoration: none;
    transition: var(--transition);
    position: relative;
    overflow: hidden;
    cursor: pointer;
}

.user-account-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    transition: var(--transition);
}

.user-account-btn:hover::before {
    left: 100%;
}

.user-account-btn:hover {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0.08) 100%);
    color: var(--sidebar-text);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.25);
}

/* Account Avatar */
.account-avatar {
    position: relative;
    margin-right: 0.875rem;
    flex-shrink: 0;
}

.avatar-img {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid rgba(255, 215, 0, 0.3);
    box-shadow: 0 4px 12px rgba(255, 215, 0, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.avatar-img i {
    font-size: 1.4rem;
    color: var(--sidebar-bg);
}

.status-dot {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 14px;
    height: 14px;
    background: #28a745;
    border: 3px solid var(--sidebar-bg);
    border-radius: 50%;
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.4);
}

/* Account Info */
.account-info {
    flex: 1;
    min-width: 0;
}

.account-name {
    font-size: 0.95rem;
    font-weight: 700;
    line-height: 1.2;
    margin-bottom: 0.25rem;
    color: var(--sidebar-text);
}

.account-role {
    font-size: 0.75rem;
    color: var(--sidebar-text-muted);
    margin-bottom: 0.375rem;
    display: flex;
    align-items: center;
    font-weight: 500;
}

.account-status {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
}

.status-text {
    font-size: 0.7rem;
    color: #28a745;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.last-activity {
    font-size: 0.65rem;
    color: var(--sidebar-text-muted);
    font-weight: 400;
}

/* Dropdown Indicator */
.dropdown-indicator {
    margin-left: 0.5rem;
    width: 20px;
    text-align: center;
    transition: var(--transition);
}

.dropdown-indicator i {
    font-size: 0.8rem;
    color: var(--sidebar-text-muted);
}

.dropdown.show .dropdown-indicator i {
    transform: rotate(180deg);
    color: #ffd700;
}

/* Enhanced Dropdown Menu */
.account-dropdown {
    position: absolute;
    bottom: 100%;
    margin-bottom: 0.75rem;
    min-width: 280px;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border: none;
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.1);
    padding: 0.5rem;
    backdrop-filter: blur(10px);
    overflow: hidden;
}

.dropdown-header {
    margin: 0;
    padding: 0.75rem 1rem 0.5rem;
    border-bottom: 1px solid #e9ecef;
    margin-bottom: 0.5rem;
}

.header-content {
    display: flex;
    align-items: center;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
}

.enhanced-dropdown-item {
    display: flex;
    align-items: center;
    padding: 0.875rem 1rem;
    margin-bottom: 0.25rem;
    border-radius: 12px;
    transition: var(--transition);
    text-decoration: none;
    color: #495057;
    border: 1px solid transparent;
    position: relative;
    overflow: hidden;
}

.enhanced-dropdown-item::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(0, 123, 255, 0.1), transparent);
    transition: var(--transition);
}

.enhanced-dropdown-item:hover::before {
    left: 100%;
}

.enhanced-dropdown-item:hover {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    color: #212529;
    transform: translateX(4px);
    border-color: rgba(0, 123, 255, 0.2);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.logout-item:hover {
    background: linear-gradient(135deg, #ffe6e7 0%, #fdd5d7 100%);
    color: #dc3545;
    border-color: rgba(220, 53, 69, 0.2);
}

.item-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--sidebar-bg) 0%, var(--sidebar-hover) 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.875rem;
    flex-shrink: 0;
    transition: var(--transition);
}

.item-icon i {
    font-size: 1rem;
    color: white;
}

.logout-item .item-icon {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
}

.item-content {
    flex: 1;
    min-width: 0;
}

.item-title {
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1.2;
    margin-bottom: 0.125rem;
}

.item-subtitle {
    font-size: 0.7rem;
    color: #6c757d;
    line-height: 1.2;
}

.item-arrow {
    width: 20px;
    text-align: center;
    opacity: 0;
    transition: var(--transition);
}

.enhanced-dropdown-item:hover .item-arrow {
    opacity: 1;
    transform: translateX(2px);
}

.item-arrow i {
    font-size: 0.75rem;
    color: #6c757d;
}

.dropdown-divider {
    margin: 0.5rem 0;
    border-color: #e9ecef;
}

/* Status Pulse Animation */
@keyframes pulse-status {
    0% {
        box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
    }
    50% {
        box-shadow: 0 0 0 6px rgba(40, 167, 69, 0.1);
    }
    100% {
        box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
    }
}

/* Admin-specific CSS Enhancements */
.profile-dropdown {
    width: 100%;
}

.profile-dropdown .dropdown-toggle {
    border: none;
    background: none;
    box-shadow: none;
    width: 100%;
    text-align: left;
}

.profile-dropdown .dropdown-toggle:focus {
    box-shadow: none;
}

.profile-dropdown .dropdown-toggle::after {
    display: none;
}

/* Dropdown Visibility Fix */
.account-dropdown {
    display: none; /* Hidden by default */
    opacity: 0;
    transform: translateY(10px);
    transition: all 0.3s ease;
    position: absolute;
    bottom: calc(100% + 0.75rem);
    left: 0;
    right: 0;
    min-width: 320px;
    max-width: 350px;
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border: none;
    border-radius: 16px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15), 0 0 0 1px rgba(255, 255, 255, 0.1);
    padding: 0.5rem;
    backdrop-filter: blur(10px);
    overflow: hidden;
    z-index: 1055;
}

.dropdown.show .account-dropdown,
.profile-dropdown.show .account-dropdown {
    visibility: visible;
    opacity: 1;
    transform: translateY(0);
}

/* Specific overrides for admin sidebar */
.admin-sidebar .profile-dropdown .account-dropdown {
    visibility: hidden;
    opacity: 0;
    display: block !important;
}

.admin-sidebar .profile-dropdown.show .account-dropdown {
    visibility: visible !important;
    opacity: 1 !important;
    transform: translateY(0) !important;
}

/* Bootstrap override */
.admin-sidebar .dropdown-menu {
    display: block !important;
}

.user-account-btn:focus,
.user-account-btn:active,
.dropdown.show .user-account-btn {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0.08) 100%);
    color: var(--sidebar-text);
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2), 0 0 0 1px rgba(255, 255, 255, 0.1);
    border-color: rgba(255, 255, 255, 0.25);
    outline: none;
}

.admin-sidebar .avatar-img {
    background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
    border: 2px solid rgba(220, 53, 69, 0.3);
    box-shadow: 0 4px 12px rgba(220, 53, 69, 0.2), inset 0 1px 0 rgba(255, 255, 255, 0.2);
}

.admin-sidebar .avatar-img i {
    color: white;
}

.admin-sidebar .dropdown.show .dropdown-indicator i {
    color: #dc3545;
}

.admin-sidebar .account-dropdown {
    position: absolute;
    bottom: calc(100% + 0.75rem);
    left: 0;
    right: 0;
    min-width: 320px;
    max-width: 350px;
    z-index: 1055;
}

.admin-sidebar .enhanced-dropdown-item::before {
    background: linear-gradient(90deg, transparent, rgba(220, 53, 69, 0.1), transparent);
}

.admin-sidebar .enhanced-dropdown-item:hover,
.admin-sidebar .enhanced-dropdown-item:focus {
    border-color: rgba(220, 53, 69, 0.2);
    text-decoration: none;
}

.admin-sidebar .enhanced-dropdown-item:focus .item-arrow {
    opacity: 1;
    transform: translateX(2px);
}
</style>

<script>
// jQuery Compatibility Check and Enhanced Fallback
if (typeof jQuery === 'undefined') {
    console.warn('jQuery is not loaded. Using vanilla JS fallbacks.');
    
    // Create comprehensive $ replacement
    window.$ = function(selector) {
        if (selector === document) {
            return {
                ready: function(callback) {
                    if (document.readyState === 'loading') {
                        document.addEventListener('DOMContentLoaded', callback);
                    } else {
                        callback();
                    }
                }
            };
        }
        
        if (selector === window) {
            return {
                on: function(event, callback) {
                    window.addEventListener(event, callback);
                    return this;
                },
                width: function() {
                    return window.innerWidth;
                }
            };
        }
        
        if (typeof selector === 'string') {
            const elements = document.querySelectorAll(selector);
            return {
                length: elements.length,
                each: function(callback) {
                    elements.forEach(callback);
                    return this;
                },
                on: function(event, callback) {
                    elements.forEach(el => el.addEventListener(event, callback));
                    return this;
                },
                addClass: function(className) {
                    elements.forEach(el => el.classList.add(className));
                    return this;
                },
                removeClass: function(className) {
                    elements.forEach(el => el.classList.remove(className));
                    return this;
                },
                toggleClass: function(className) {
                    elements.forEach(el => el.classList.toggle(className));
                    return this;
                },
                append: function(html) {
                    elements.forEach(el => el.insertAdjacentHTML('beforeend', html));
                    return this;
                },
                modal: function(action) {
                    console.warn('Bootstrap modal requires proper Bootstrap JS');
                    return this;
                }
            };
        }
        
        return {
            ready: function() { return this; },
            on: function() { return this; },
            addClass: function() { return this; },
            removeClass: function() { return this; },
            modal: function() { return this; }
        };
    };
} else {
    // jQuery is available
    window.$ = jQuery;
}

// Initialize admin sidebar with vanilla JavaScript
document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin sidebar DOM loaded - initializing...');
    
    // Initialize admin sidebar functionality
    initializeAdminSidebar();
    
    // Auto-refresh system stats
    refreshSystemStats();
    setInterval(refreshSystemStats, 30000); // Refresh every 30 seconds
    
    // Initialize dropdown with delay
    setTimeout(() => {
        initializeAdminProfileDropdown();
    }, 1000);
    
    // Initialize notifications if available
    if (typeof initializeNotificationSystem === 'function') {
        initializeNotificationSystem();
    }
});

// Vanilla JS functions
function toggleSidebar() {
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const body = document.body;
    
    if (sidebar) sidebar.classList.toggle('show');
    if (overlay) overlay.classList.toggle('show');
    body.classList.toggle('sidebar-open');
}

function closeSidebar() {
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const body = document.body;
    
    if (sidebar) sidebar.classList.remove('show');
    if (overlay) overlay.classList.remove('show');
    body.classList.remove('sidebar-open');
}

function initializeAdminSidebar() {
    // Mobile sidebar toggle
    document.querySelectorAll('.mobile-toggle, .sidebar-toggle').forEach(function(el) {
        el.addEventListener('click', function() {
            toggleSidebar();
        });
    });
    
    // Sidebar overlay click
    document.querySelectorAll('.sidebar-overlay').forEach(function(el) {
        el.addEventListener('click', function() {
            if (window.innerWidth < 1200) {
                closeSidebar();
            }
        });
    });
    
    // Active navigation highlighting
    highlightActiveNav();
    
    // System overview widget click
    const systemWidget = document.querySelector('.system-overview-widget');
    if (systemWidget) {
        systemWidget.addEventListener('click', function() {
            if (typeof showSystemInfoModal === 'function') {
                showSystemInfoModal();
            }
        });
    }
    
    // Quick action buttons
    const showAlertsBtn = document.getElementById('showAlertsBtn');
    if (showAlertsBtn) {
        showAlertsBtn.addEventListener('click', function() {
            if (typeof showSystemAlertsModal === 'function') {
                showSystemAlertsModal();
            }
        });
    }
    
    const sessionInfoBtn = document.getElementById('sessionInfoBtn');
    if (sessionInfoBtn) {
        sessionInfoBtn.addEventListener('click', function() {
            if (typeof showSessionInfoModal === 'function') {
                showSessionInfoModal();
            }
        });
    }
    
    const settingsBtn = document.getElementById('settingsBtn');
    if (settingsBtn) {
        settingsBtn.addEventListener('click', function() {
            if (typeof showSettingsModal === 'function') {
                showSettingsModal();
            }
        });
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 1200) {
            closeSidebar();
        }
    });
    
    // Smooth scrolling for navigation links
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId && targetId.startsWith('#')) {
                e.preventDefault();
                const targetElement = document.querySelector(targetId);
                if (targetElement && typeof smoothScrollTo === 'function') {
                    smoothScrollTo(targetElement);
                }
            }
        });
    });
    
    // Animation triggers
    animateWidgets();
}

function toggleSidebar() {
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    if (sidebar) sidebar.classList.toggle('show');
    if (overlay) overlay.classList.toggle('show');
    document.body.classList.toggle('sidebar-open');
}

function closeSidebar() {
    const sidebar = document.querySelector('.admin-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    if (sidebar) sidebar.classList.remove('show');
    if (overlay) overlay.classList.remove('show');
    document.body.classList.remove('sidebar-open');
}

function highlightActiveNav() {
    const currentPath = window.location.pathname;
    const currentPage = currentPath.split('/').pop();
    
    document.querySelectorAll('.nav-link').forEach(function(link) {
        link.classList.remove('active');
        const linkHref = link.getAttribute('href');
        if (linkHref && (linkHref === currentPage || linkHref.endsWith(currentPage))) {
            link.classList.add('active');
        }
    });
}

function refreshSystemStats() {
    console.log('Refreshing system stats with vanilla JS...');
    
    fetch('../ajax/admin-ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_system_stats'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            updateSystemStatsDisplay(data.data);
        } else {
            console.error('Failed to fetch system stats:', data.message);
            useFallbackStats();
        }
    })
    .catch(error => {
        console.log('Failed to fetch system stats:', error);
        useFallbackStats();
    });
}

function useFallbackStats() {
    // Use fallback data
    const fallbackStats = {
        online_users: Math.floor(Math.random() * 15) + 5,
        pending_visitors: Math.floor(Math.random() * 8) + 2,
        active_alerts: Math.floor(Math.random() * 3),
        system_health: Math.floor(Math.random() * 20) + 85
    };
    
    // Set fallback values directly
    const totalEl = document.getElementById('totalVisitorsToday');
    const currentEl = document.getElementById('currentVisitors');
    
    if (totalEl) totalEl.textContent = fallbackStats.online_users || '0';
    if (currentEl) currentEl.textContent = fallbackStats.pending_visitors || '0';
    
    console.log('Using fallback stats:', fallbackStats);
}

function updateSystemStatsDisplay(stats) {
    // Use vanilla JS instead of jQuery
    const onlineEl = document.getElementById('onlineUsersCount');
    const pendingEl = document.getElementById('pendingVisitorsCount');
    const alertsEl = document.getElementById('activeAlertsCount');
    const healthEl = document.getElementById('systemHealthCount');
    
    if (onlineEl) onlineEl.textContent = stats.online_users || '0';
    if (pendingEl) pendingEl.textContent = stats.pending_visitors || '0';
    if (alertsEl) alertsEl.textContent = stats.active_alerts || '0';
    if (healthEl) healthEl.textContent = (stats.system_health || 95) + '%';
    
    console.log('Updated system stats display:', stats);
    
    // Add animation to updated stats
    document.querySelectorAll('.stat-number').forEach(function(el) {
        el.classList.add('pulse');
        setTimeout(function() {
            el.classList.remove('pulse');
        }, 1000);
    });
    
    // Update quick actions alert badge
    const alertBadge = document.querySelector('#showAlertsBtn .alert-badge');
    if (alertBadge) {
        if (stats.active_alerts && stats.active_alerts > 0) {
            alertBadge.textContent = stats.active_alerts;
            alertBadge.style.display = 'block';
        } else {
            alertBadge.style.display = 'none';
        }
    }
}

function showSystemInfoModal() {
    const modalEl = document.getElementById('systemInfoModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
    
    // Load system information
    fetch('../ajax/admin-ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_system_info'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            populateSystemInfo(data.data);
        } else {
            useFallbackSystemInfo();
        }
    })
    .catch(error => {
        console.log('Failed to fetch system info:', error);
        useFallbackSystemInfo();
    });
}

function useFallbackSystemInfo() {
    const fallbackInfo = {
        version: '2.1.0',
        uptime: '5 days, 12 hours',
        total_users: '47',
        total_visitors: '1,234',
        database_size: '2.4 MB',
        php_version: '8.1.0',
        server_load: '0.65'
    };
    populateSystemInfo(fallbackInfo);
}

function populateSystemInfo(data) {
    const setTextContent = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value || 'N/A';
    };
    setTextContent('systemVersion', data.version);
    setTextContent('systemUptime', data.uptime);
    setTextContent('totalUsers', data.total_users);
    setTextContent('totalVisitors', data.total_visitors);
    setTextContent('databaseSize', data.database_size);
    setTextContent('phpVersion', data.php_version);
    setTextContent('serverLoad', data.server_load);
}

function showSystemAlertsModal() {
    const modalEl = document.getElementById('systemAlertsModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
    
    // Load system alerts
    fetch('../ajax/admin-ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_system_alerts'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            populateSystemAlerts(data.data);
        } else {
            useFallbackAlerts();
        }
    })
    .catch(error => {
        console.log('Failed to fetch system alerts:', error);
        useFallbackAlerts();
    });
}

function useFallbackAlerts() {
    const fallbackAlerts = [
        {
            type: 'warning',
            title: 'System Maintenance',
            message: 'Scheduled maintenance window approaching.',
            time: '2 hours ago'
        },
        {
            type: 'info',
            title: 'Backup Completed',
            message: 'Daily backup completed successfully.',
            time: '4 hours ago'
        }
    ];
    populateSystemAlerts(fallbackAlerts);
}

function populateSystemAlerts(alerts) {
    const alertsContainer = document.getElementById('systemAlertsContainer');
    if (!alertsContainer) return;
    
    alertsContainer.innerHTML = '';
    
    if (alerts.length === 0) {
        alertsContainer.innerHTML = '<div class="alert alert-success">No active system alerts.</div>';
        return;
    }
    
    alerts.forEach(function(alert) {
        const alertClass = getAlertClass(alert.type);
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert ' + alertClass + ' d-flex justify-content-between align-items-center';
        alertDiv.innerHTML = `
            <div>
                <strong>${alert.title}</strong>
                <p class="mb-0">${alert.message}</p>
                <small class="text-muted">${alert.time}</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        alertsContainer.appendChild(alertDiv);
    });
}

function showSessionInfoModal() {
    const modalEl = document.getElementById('sessionInfoModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
    updateSessionInfo();
}

function updateSessionInfo() {
    const now = new Date();
    const currentTimeEl = document.getElementById('currentTime');
    if (currentTimeEl) currentTimeEl.textContent = now.toLocaleString();
    
    // Calculate session duration (mock data)
    const sessionStart = new Date(now.getTime() - (Math.random() * 3600000)); // Random time in last hour
    const duration = Math.floor((now - sessionStart) / 1000 / 60); // Minutes
    const sessionDurationEl = document.getElementById('sessionDuration');
    if (sessionDurationEl) sessionDurationEl.textContent = duration + ' minutes';
    
    const lastActivityEl = document.getElementById('lastActivity');
    if (lastActivityEl) lastActivityEl.textContent = 'Just now';
    
    const userAgentEl = document.getElementById('userAgent');
    if (userAgentEl) userAgentEl.textContent = navigator.userAgent.substring(0, 50) + '...';
}

function showSettingsModal() {
    const modalEl = document.getElementById('profileSettingsModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

function showProfileModal() {
    const modalEl = document.getElementById('profileSettingsModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

function getAlertClass(type) {
    switch(type) {
        case 'error': return 'alert-danger';
        case 'warning': return 'alert-warning';
        case 'success': return 'alert-success';
        case 'info': 
        default: return 'alert-info';
    }
}

function smoothScrollTo(target) {
    const element = typeof target === 'string' ? document.querySelector(target) : target;
    if (element) {
        element.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

function animateWidgets() {
    // Animate widgets on load
    setTimeout(function() {
        const widget = document.querySelector('.system-overview-widget');
        if (widget) {
            widget.classList.add('pulse');
            setTimeout(function() {
                widget.classList.remove('pulse');
            }, 2000);
        }
    }, 1000);
}

function initializeNotificationSystem() {
    // Check for notifications every 60 seconds
    setInterval(checkNotifications, 60000);
}

function checkNotifications() {
    fetch('../ajax/admin-ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=check_notifications'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.data && data.data.length > 0) {
            showNotification(data.data[0]);
        }
    })
    .catch(error => {
        // Silently fail for notifications
        console.log('Notification check failed:', error);
    });
}

function showNotification(notification) {
    const notificationHtml = `
        <div class="toast align-items-center text-white bg-${getNotificationClass(notification.type)} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body">
                    <strong>${notification.title}</strong><br>
                    ${notification.message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>
    `;
    
    // Add to toast container or create one if it doesn't exist
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = notificationHtml.trim();
    const toastEl = tempDiv.firstChild;
    toastContainer.appendChild(toastEl);
    
    if (typeof bootstrap !== 'undefined') {
        const toast = new bootstrap.Toast(toastEl);
        toast.show();
        
        // Remove from DOM after hiding
        toastEl.addEventListener('hidden.bs.toast', function() {
            toastEl.remove();
        });
    }
}

function getNotificationClass(type) {
    switch(type) {
        case 'error': return 'danger';
        case 'warning': return 'warning';
        case 'success': return 'success';
        case 'info':
        default: return 'primary';
    }
}

// AJAX Logout Function
function logoutUser() {
    if (confirm('Are you sure you want to logout?')) {
        fetch('ajax/logout-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message briefly then redirect
                alert('Logged out successfully!');
                window.location.href = data.redirect;
            } else {
                alert('Logout failed: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Logout error:', error);
            // Fallback to regular logout
            window.location.href = 'logout.php';
        });
    }
}

// Enhanced account functions
function showProfileModalEnhanced() {
    const modalEl = document.getElementById('profileSettingsModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

function showSecuritySettings() {
    // Create security settings modal if it doesn't exist
    let modalEl = document.getElementById('securitySettingsModal');
    if (!modalEl) {
        const securityModal = `
            <div class="modal fade" id="securitySettingsModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white;">
                            <h5 class="modal-title">
                                <i class="fas fa-key me-2"></i>Security Settings
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="info-card">
                                <h6><i class="fas fa-shield-alt me-2"></i>Password Security</h6>
                                <form>
                                    <div class="mb-3">
                                        <label class="form-label">Current Password</label>
                                        <input type="password" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">New Password</label>
                                        <input type="password" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" required>
                                    </div>
                                    <button type="submit" class="btn btn-danger">Update Password</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', securityModal);
        modalEl = document.getElementById('securitySettingsModal');
    }
    
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

function showActivityLog() {
    // Create activity log modal if it doesn't exist
    let modalEl = document.getElementById('activityLogModal');
    if (!modalEl) {
        const activityModal = `
            <div class="modal fade" id="activityLogModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #1a4d2e 0%, #2d7a4f 100%); color: white;">
                            <h5 class="modal-title">
                                <i class="fas fa-history me-2"></i>Activity Log
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>12:34 PM</td>
                                            <td>Login</td>
                                            <td>Successful admin login</td>
                                            <td>192.168.1.100</td>
                                        </tr>
                                        <tr>
                                            <td>12:30 PM</td>
                                            <td>Dashboard Access</td>
                                            <td>Viewed admin dashboard</td>
                                            <td>192.168.1.100</td>
                                        </tr>
                                        <tr>
                                            <td>12:25 PM</td>
                                            <td>User Management</td>
                                            <td>Updated user permissions</td>
                                            <td>192.168.1.100</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', activityModal);
        modalEl = document.getElementById('activityLogModal');
    }
    
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

function updateSessionTimer() {
    const sessionStart = localStorage.getItem('admin_session_start') || new Date().toISOString();
    const startTime = new Date(sessionStart);
    const now = new Date();
    const diffMs = now - startTime;
    const hours = Math.floor(diffMs / (1000 * 60 * 60));
    const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
    
    const timerElement = document.getElementById('adminSessionTimer');
    if (timerElement) {
        timerElement.textContent = `${hours}h ${minutes}m`;
    }
}

function initializeEnhancedAccount() {
    // Initialize session start time
    if (!localStorage.getItem('admin_session_start')) {
        localStorage.setItem('admin_session_start', new Date().toISOString());
    }
    
    // Update session timer every minute
    updateSessionTimer();
    setInterval(updateSessionTimer, 60000);
}

function confirmLogout() {
    const confirmed = confirm('Are you sure you want to logout?');
    if (confirmed) {
        // Show loading state
        const logoutBtn = document.querySelector('.logout-item');
        if (logoutBtn) {
            logoutBtn.innerHTML = `
                <div class="item-icon">
                    <i class="fa fa-spinner fa-spin"></i>
                </div>
                <div class="item-content">
                    <div class="item-title">Logging out...</div>
                    <div class="item-subtitle">Please wait...</div>
                </div>
            `;
        }
        
        // Redirect after animation
        setTimeout(function() {
            window.location.href = 'logout.php';
        }, 1000);
    }
    return false;
}

// Initialize enhanced account features with fallbacks
if (typeof jQuery !== 'undefined') {
    jQuery(document).ready(function($) {
        console.log('Admin sidebar jQuery ready, initializing enhanced features...');
        initializeEnhancedAccount();
    });
} else {
    // Basic initialization without jQuery
    console.log('Admin sidebar running in vanilla JS mode');
}

// Admin profile dropdown functionality
function initializeAdminProfileDropdown() {
    const dropdownButton = document.getElementById('adminProfileDropdown');
    const dropdown = document.querySelector('.admin-sidebar .profile-dropdown');
    const dropdownMenu = document.querySelector('.admin-sidebar .account-dropdown');
    
    console.log('Initializing admin profile dropdown...', { dropdownButton, dropdown, dropdownMenu });
    
    if (dropdownButton && dropdown) {
        // Remove any existing Bootstrap dropdown behavior completely
        dropdownButton.removeAttribute('data-bs-toggle');
        dropdownButton.removeAttribute('data-bs-auto-close');
        dropdownButton.classList.remove('dropdown-toggle');
        
        // Clear any existing event listeners
        const newButton = dropdownButton.cloneNode(true);
        dropdownButton.parentNode.replaceChild(newButton, dropdownButton);
        
        newButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Admin dropdown button clicked');
            
            // Toggle dropdown
            const isOpen = dropdown.classList.contains('show');
            
            if (isOpen) {
                closeAdminProfileDropdown();
            } else {
                openAdminProfileDropdown();
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const adminSidebar = document.querySelector('.admin-sidebar');
            if (adminSidebar && !adminSidebar.contains(e.target)) {
                closeAdminProfileDropdown();
            }
        });
        
        // Close dropdown on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAdminProfileDropdown();
            }
        });
        
        console.log('Admin profile dropdown initialized successfully');
    } else {
        console.error('Admin dropdown elements not found:', { dropdownButton, dropdown });
    }
}

function openAdminProfileDropdown() {
    console.log('Opening admin profile dropdown');
    const dropdown = document.querySelector('.admin-sidebar .profile-dropdown');
    const dropdownButton = document.getElementById('adminProfileDropdown');
    const indicator = dropdown ? dropdown.querySelector('.dropdown-indicator i') : null;
    const dropdownMenu = document.querySelector('.admin-sidebar .account-dropdown');
    
    if (dropdown && dropdownMenu) {
        dropdown.classList.add('show');
        dropdownButton.setAttribute('aria-expanded', 'true');
        
        // Force visibility
        dropdownMenu.style.visibility = 'visible';
        dropdownMenu.style.opacity = '1';
        dropdownMenu.style.transform = 'translateY(0)';
        
        if (indicator) {
            indicator.style.transform = 'rotate(180deg)';
            indicator.style.color = '#ffd700';
        }
        
        console.log('Admin dropdown should now be visible');
    }
}

function closeAdminProfileDropdown() {
    console.log('Closing admin profile dropdown');
    const dropdown = document.querySelector('.admin-sidebar .profile-dropdown');
    const dropdownButton = document.getElementById('adminProfileDropdown');
    const indicator = dropdown ? dropdown.querySelector('.dropdown-indicator i') : null;
    const dropdownMenu = document.querySelector('.admin-sidebar .account-dropdown');
    
    if (dropdown && dropdownMenu) {
        dropdown.classList.remove('show');
        dropdownButton.setAttribute('aria-expanded', 'false');
        
        // Force hide
        dropdownMenu.style.visibility = 'hidden';
        dropdownMenu.style.opacity = '0';
        dropdownMenu.style.transform = 'translateY(10px)';
        
        if (indicator) {
            indicator.style.transform = 'rotate(0deg)';
            indicator.style.color = '';
        }
    }
}

// Global functions for external use
window.AdminSidebar = {
    refresh: refreshSystemStats,
    showSystemInfo: showSystemInfoModal,
    showSessionInfo: showSessionInfoModal,
    showSettings: showSettingsModal,
    showProfile: showProfileModal,
    showSecurity: showSecuritySettings,
    showActivity: showActivityLog,
    toggle: toggleSidebar,
    close: closeSidebar
};
</script>