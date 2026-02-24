<?php
/**
 * Guard Sidebar Navigation
 * St. Dominic Savio College - Visitor Management System
 */

// Get current page to highlight active menu
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
?>

<!-- Sidebar -->
<div class="guard-sidebar" id="guardSidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="fas fa-user-shield"></i>
            </div>
            <div class="brand-text">
                <h5 class="mb-0"><?php echo APP_NAME; ?></h5>
                <small class="text-muted">Guard Portal</small>
            </div>
        </div>
        <button class="sidebar-toggle d-lg-none" onclick="toggleSidebar()">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Navigation Menu -->
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <!-- Quick Check In -->
            <li class="nav-item">
                <a href="checkin.php" class="nav-link <?php echo ($current_page == 'checkin.php') ? 'active' : ''; ?>">
                    <div class="nav-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <span class="nav-text">Quick Check In</span>
                </a>
            </li>

            <!-- Quick Check Out -->
            <li class="nav-item">
                <a href="checkout.php" class="nav-link <?php echo ($current_page == 'checkout.php') ? 'active' : ''; ?>">
                    <div class="nav-icon">
                        <i class="fas fa-user-minus"></i>
                    </div>
                    <span class="nav-text">Quick Check Out</span>
                </a>
            </li>

            <!-- Current Visitors -->
            <li class="nav-item">
                <a href="current-visitors.php" class="nav-link <?php echo ($current_page == 'current-visitors.php') ? 'active' : ''; ?>">
                    <div class="nav-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <span class="nav-text">Current Visitors</span>
                </a>
            </li>

            <li class="nav-divider"></li>

            <!-- Reports Section -->
            <li class="nav-section-title">
                <span>Reports</span>
            </li>
            <li class="nav-item">
                <a href="blacklist.php" class="nav-link <?php echo ($current_page == 'blacklist.php') ? 'active' : ''; ?>">
                    <div class="nav-icon">
                        <i class="fas fa-ban"></i>
                    </div>
                    <span class="nav-text">Blacklist</span>
                    <span class="badge bg-danger ms-auto" id="blacklistCount" style="display: none;"></span>
                </a>
            </li>
            <!-- Audit Trail -->
            <li class="nav-item">
                <a href="<?php echo ($current_dir == 'admin') ? '' : '../admin/'; ?>reports.php" 
                   class="nav-link <?php echo ($current_page == 'reports.php' || $current_page == 'guard-reports.php') ? 'active' : ''; ?>">
                    <div class="nav-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <span class="nav-text">Audit Trail</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <!-- Enhanced User Profile -->
        <div class="user-profile-enhanced">
           
            </div>
            
            <!-- Dropdown Profile Button -->
            <div class="dropdown profile-dropdown">
                <button class="user-account-btn dropdown-toggle" 
                        type="button"
                        id="guardProfileDropdown" 
                        data-bs-toggle="dropdown" 
                        data-bs-auto-close="true"
                        aria-expanded="false"
                        aria-haspopup="true"
                        title="Open account menu">
                    <div class="account-avatar">
                        <div class="avatar-img">
                            <i class="fas fa-user-shield" aria-hidden="true"></i>
                        </div>
                        <div class="status-dot" title="Online"></div>
                    </div>
                    <div class="account-info">
                        <div class="account-name"><?php echo htmlspecialchars($_SESSION['first_name'] ?? 'Guard'); ?></div>
                        <div class="account-role">
                            <i class="fas fa-shield-alt me-1" aria-hidden="true"></i>
                            <span>Security Guard</span>
                        </div>
                        <div class="account-status">
                            <span class="status-text">On Duty</span>
                            <span class="last-activity" id="lastActivity">Active now</span>
                        </div>
                    </div>
                    <div class="dropdown-indicator">
                        <i class="fas fa-chevron-up" aria-hidden="true"></i>
                    </div>
                </button>
                
                <!-- Dropdown Menu -->
                <ul class="dropdown-menu account-dropdown" aria-labelledby="guardProfileDropdown">
                    <!-- Account Section -->
                    <li class="dropdown-header">
                        <div class="header-content">
                            <i class="fas fa-user-circle me-2" aria-hidden="true"></i>
                            <span>Guard Account</span>
                        </div>
                    </li>
                    
                    <!-- Profile Settings -->
                    <li>
                        <a class="dropdown-item enhanced-dropdown-item" 
                           href="#" 
                           role="menuitem"
                           onclick="showProfileSettings(); return false;">
                            <div class="item-icon">
                                <i class="fas fa-user-edit" aria-hidden="true"></i>
                            </div>
                            <div class="item-content">
                                <div class="item-title">Profile Settings</div>
                                <div class="item-subtitle">Update personal details</div>
                            </div>
                            <div class="item-arrow">
                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </div>
                        </a>
                    </li>
                    
                    <!-- Shift Information -->
                    <li>
                        <a class="dropdown-item enhanced-dropdown-item" 
                           href="#" 
                           role="menuitem"
                           onclick="showShiftInfo(); return false;">
                            <div class="item-icon">
                                <i class="fas fa-clock" aria-hidden="true"></i>
                            </div>
                            <div class="item-content">
                                <div class="item-title">Shift Information</div>
                                <div class="item-subtitle">View current shift details</div>
                            </div>
                            <div class="item-arrow">
                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </div>
                        </a>
                    </li>
                    
                    <!-- Duty Log -->
                    <li>
                        <a class="dropdown-item enhanced-dropdown-item" 
                           href="#" 
                           role="menuitem"
                           onclick="showDutyLog(); return false;">
                            <div class="item-icon">
                                <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                            </div>
                            <div class="item-content">
                                <div class="item-title">Duty Log</div>
                                <div class="item-subtitle">Record incidents & notes</div>
                            </div>
                            <div class="item-arrow">
                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </div>
                        </a>
                    </li>
                    
                    <li><hr class="dropdown-divider"></li>
                    
                    <!-- Tools Section -->
                    <li class="dropdown-header">
                        <div class="header-content">
                            <i class="fas fa-tools me-2" aria-hidden="true"></i>
                            <span>Security Tools</span>
                        </div>
                    </li>
                    
                    <!-- Emergency Contacts -->
                    <li>
                        <a class="dropdown-item enhanced-dropdown-item" 
                           href="#" 
                           role="menuitem"
                           onclick="showEmergencyContacts(); return false;">
                            <div class="item-icon emergency-icon">
                                <i class="fas fa-phone-alt" aria-hidden="true"></i>
                            </div>
                            <div class="item-content">
                                <div class="item-title">Emergency Contacts</div>
                                <div class="item-subtitle">Quick access to contacts</div>
                            </div>
                            <div class="item-arrow">
                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </div>
                        </a>
                    </li>
                    
                    <!-- Security Protocols -->
                    <li>
                        <a class="dropdown-item enhanced-dropdown-item" 
                           href="#" 
                           role="menuitem"
                           onclick="showSecurityProtocols(); return false;">
                            <div class="item-icon">
                                <i class="fas fa-book-open" aria-hidden="true"></i>
                            </div>
                            <div class="item-content">
                                <div class="item-title">Security Protocols</div>
                                <div class="item-subtitle">View procedures & guidelines</div>
                            </div>
                            <div class="item-arrow">
                                <i class="fas fa-chevron-right" aria-hidden="true"></i>
                            </div>
                        </a>
                    </li>
                    
                    <li><hr class="dropdown-divider"></li>
                    
                    <!-- Logout Section -->
                    <li>
                        <a class="dropdown-item enhanced-dropdown-item logout-item" 
                           href="#" 
                           role="menuitem"
                           onclick="confirmLogout(); closeProfileDropdown(); return false;">
                            <div class="item-icon">
                                <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                            </div>
                            <div class="item-content">
                                <div class="item-title">End Shift</div>
                                <div class="item-subtitle">Logout and end duty</div>
                            </div>
                            <div class="item-arrow">
                                <i class="fas fa-external-link-alt" aria-hidden="true"></i>
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
        <i class="fas fa-user-shield me-2"></i>
        Guard Portal
    </div>
    <div class="mobile-visitor-count">
        <span class="visitor-badge" id="mobileVisitorCount">0</span>
    </div>
</div>

            </div>
        </div>
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
.guard-sidebar {
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

/* Visitor Count Widget */
.visitor-count-widget {
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

.count-display {
    padding: 1.25rem;
    text-align: center;
    position: relative;
}

.count-number {
    font-size: 2rem;
    font-weight: 700;
    color: #ffd700;
    background: linear-gradient(135deg, #ffd700 0%, #ffed4e 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.count-label {
    font-size: 0.75rem;
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

.visitor-badge {
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
.emergency-contact-list .contact-item {
    display: flex;
    align-items: flex-start;
    padding: 1rem;
    margin-bottom: 1rem;
    background: #f8f9fa;
    border-radius: 10px;
    border-left: 4px solid var(--sidebar-bg);
}

.contact-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
    background: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.contact-icon i {
    font-size: 1.5rem;
}

.contact-info h6 {
    margin-bottom: 0.5rem;
    color: var(--sidebar-bg);
    font-weight: 600;
}

.shift-info .info-item {
    display: flex;
    align-items: center;
    padding: 0.75rem;
    margin-bottom: 0.5rem;
    background: #f8f9fa;
    border-radius: 8px;
}

/* Responsive Design */
@media (max-width: 1199.98px) {
    .guard-sidebar {
        transform: translateX(-100%);
    }
    
    .guard-sidebar.show {
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

@keyframes shake {
    10%, 90% { transform: translate3d(-1px, 0, 0); }
    20%, 80% { transform: translate3d(2px, 0, 0); }
    30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
    40%, 60% { transform: translate3d(4px, 0, 0); }
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

/* Dropdown Visibility Fix */
.account-dropdown {
    visibility: hidden;
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
    display: block;
}

.dropdown.show .account-dropdown,
.profile-dropdown.show .account-dropdown {
    visibility: visible;
    opacity: 1;
    transform: translateY(0);
}

/* Specific overrides for guard sidebar */
.guard-sidebar .profile-dropdown .account-dropdown {
    visibility: hidden;
    opacity: 0;
    display: block !important;
}

.guard-sidebar .profile-dropdown.show .account-dropdown {
    visibility: visible !important;
    opacity: 1 !important;
    transform: translateY(0) !important;
}

/* Bootstrap override */
.guard-sidebar .dropdown-menu {
    display: block !important;
}

/* Override Bootstrap dropdown behavior */
.profile-dropdown .dropdown-toggle[data-bs-toggle="dropdown"]:not([aria-expanded="true"]) ~ .dropdown-menu {
    visibility: hidden !important;
    opacity: 0 !important;
}

.profile-dropdown .dropdown-toggle[aria-expanded="true"] ~ .dropdown-menu {
    visibility: visible !important;
    opacity: 1 !important;
}

/* Profile Dropdown Container */
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
    display: none; /* Hide default Bootstrap arrow */
}

.user-account-btn:hover,
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
        
        if (typeof selector === 'string') {
            const elements = document.querySelectorAll(selector);
            const element = elements[0];
            return {
                length: elements.length,
                modal: function(action) {
                    if (element && typeof bootstrap !== 'undefined') {
                        const modal = bootstrap.Modal.getOrCreateInstance(element);
                        if (action === 'show') modal.show();
                        else if (action === 'hide') modal.hide();
                    } else {
                        console.warn('Bootstrap modal not available');
                    }
                    return this;
                },
                append: function(html) {
                    if (element) element.insertAdjacentHTML('beforeend', html);
                    return this;
                },
                prepend: function(html) {
                    if (element) element.insertAdjacentHTML('afterbegin', html);
                    return this;
                }
            };
        }
        
        return {
            modal: function() { return this; },
            append: function() { return this; },
            prepend: function() { return this; }
        };
    };
    
    // Minimal AJAX replacement
    window.$.ajax = function(options) {
        console.warn('Using fetch fallback for AJAX');
        if (options.url && typeof fetch !== 'undefined') {
            const fetchOptions = {
                method: options.method || 'GET',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                }
            };
            
            if (options.data && fetchOptions.method === 'POST') {
                fetchOptions.body = new URLSearchParams(options.data).toString();
            }
            
            const url = options.url + (options.data && fetchOptions.method === 'GET' ? '?' + new URLSearchParams(options.data).toString() : '');
            
            fetch(url, fetchOptions)
                .then(response => response.json())
                .then(data => {
                    if (options.success) options.success(data);
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                    if (options.error) options.error(null, 'error', error);
                })
                .finally(() => {
                    if (options.complete) options.complete();
                });
        }
    };
} else {
    // jQuery is available
    window.$ = jQuery;
}

// Global variables
let loginTime = new Date();
let shiftStartTime = loginTime;

// Sidebar toggle functionality
function toggleSidebar() {
    const sidebar = document.getElementById('guardSidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const body = document.body;
    
    if (window.innerWidth < 1200) {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
        body.classList.toggle('sidebar-open');
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('guardSidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const body = document.body;
    
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
    body.classList.remove('sidebar-open');
}

// AJAX Logout Function
function logoutUser() {
    if (confirm('Are you sure you want to logout?')) {
        // Show loading state
        const userLink = document.querySelector('.user-link');
        if (userLink) {
            userLink.style.opacity = '0.6';
            userLink.style.pointerEvents = 'none';
        }
        
        fetch('../ajax/logout-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message briefly then redirect
                showNotification('Logged out successfully!', 'success');
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, 1500);
            } else {
                showNotification('Logout failed: ' + data.message, 'error');
                if (userLink) {
                    userLink.style.opacity = '1';
                    userLink.style.pointerEvents = 'auto';
                }
            }
        })
        .catch(error => {
            console.error('Logout error:', error);
            showNotification('Network error during logout', 'error');
            // Fallback to regular logout
            setTimeout(() => {
                window.location.href = '../admin/logout.php';
            }, 2000);
        });
    }
}

// Enhanced visitor count refresh
function refreshVisitorCount() {
    const countElement = document.getElementById('currentVisitorCount');
    const mobileCountElement = document.getElementById('mobileVisitorCount');
    const refreshIcon = document.querySelector('.refresh-icon');
    
    // Add loading animation
    if (refreshIcon) {
        refreshIcon.classList.add('fa-spin');
    }
    
    // Add loading state to count
    if (countElement) {
        countElement.style.opacity = '0.6';
    }
    
    fetch('../ajax/visitor-ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_current_count'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update count with animation
            if (countElement) {
                countElement.style.opacity = '1';
                countElement.textContent = data.count;
                
                // Add pulse animation for changes
                if (parseInt(data.count) !== parseInt(countElement.dataset.lastCount || '0')) {
                    countElement.parentElement.classList.add('pulse');
                    setTimeout(() => {
                        countElement.parentElement.classList.remove('pulse');
                    }, 2000);
                }
                countElement.dataset.lastCount = data.count;
            }
            
            // Update mobile count
            if (mobileCountElement) {
                mobileCountElement.textContent = data.count;
            }
            
            // Update blacklist count with enhanced alerts
            if (data.blacklist_attempts > 0) {
                const badge = document.getElementById('blacklistCount');
                if (badge) {
                    badge.textContent = data.blacklist_attempts;
                    badge.style.display = 'inline-block';
                    badge.classList.add('pulse');
                    
                    // Show alert for new blacklist attempts
                    if (parseInt(data.blacklist_attempts) > parseInt(badge.dataset.lastCount || '0')) {
                        showBlacklistAlert(data.blacklist_attempts);
                    }
                    badge.dataset.lastCount = data.blacklist_attempts;
                }
            }
            
            showNotification('Visitor count updated', 'success');
        } else {
            showNotification('Failed to update visitor count', 'error');
        }
    })
    .catch(error => {
        console.error('Error refreshing visitor count:', error);
        showNotification('Network error', 'error');
        
        // Fallback: try alternative endpoint
        fetch('../ajax/get-visitor-count.php')
        .then(response => response.json())
        .then(data => {
            if (data.count !== undefined) {
                if (countElement) {
                    countElement.textContent = data.count;
                    countElement.style.opacity = '1';
                }
                if (mobileCountElement) {
                    mobileCountElement.textContent = data.count;
                }
            }
        })
        .catch(() => {
            if (countElement) {
                countElement.style.opacity = '1';
            }
        });
    })
    .finally(() => {
        // Remove loading animation
        if (refreshIcon) {
            refreshIcon.classList.remove('fa-spin');
        }
    });
}

// Show emergency contacts modal
function showEmergencyContacts() {
    const modal = new bootstrap.Modal(document.getElementById('emergencyContactsModal'));
    modal.show();
}

// Show shift information modal
function showShiftInfo() {
    // Update shift information
    document.getElementById('currentDate').textContent = new Date().toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
    
    document.getElementById('loginTime').textContent = loginTime.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    
    updateShiftDuration();
    
    const modal = new bootstrap.Modal(document.getElementById('shiftInfoModal'));
    modal.show();
}

// Update shift duration
function updateShiftDuration() {
    const now = new Date();
    const diffMs = now - shiftStartTime;
    const hours = Math.floor(diffMs / (1000 * 60 * 60));
    const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
    
    const durationElement = document.getElementById('shiftDuration');
    if (durationElement) {
        durationElement.textContent = `${hours}h ${minutes}m`;
    }
}

// Contact security function
function contactSecurity() {
    if (confirm('This will initiate an emergency contact. Continue?')) {
        // Log the security contact attempt
        fetch('../ajax/visitor-ajax.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=log_security_contact&reason=blacklist_alert'
        });
        
        // Show contact information
        showNotification('Security contact logged. Follow emergency procedures.', 'warning');
        
        // You can add actual phone dialing or notification system here
        if ('vibrate' in navigator) {
            navigator.vibrate([200, 100, 200]);
        }
    }
}

// View blacklist function
function viewBlacklist() {
    window.location.href = '../admin/blacklist.php';
}

// Show blacklist alert
function showBlacklistAlert(attempts) {
    const alertMessage = document.getElementById('blacklistAlertMessage');
    if (alertMessage) {
        alertMessage.textContent = `${attempts} blacklisted individual(s) have attempted access recently.`;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('blacklistAlertModal'));
    modal.show();
    
    // Add shake animation to sidebar
    const sidebar = document.getElementById('guardSidebar');
    if (sidebar) {
        sidebar.classList.add('shake');
        setTimeout(() => {
            sidebar.classList.remove('shake');
        }, 820);
    }
}

// Enhanced notification system
function showNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.toast-notification');
    existingNotifications.forEach(n => n.remove());
    
    // Create notification
    const notification = document.createElement('div');
    notification.className = `toast-notification alert alert-${type === 'success' ? 'success' : type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'info'}`;
    notification.style.cssText = `
        position: fixed;
        top: 2rem;
        right: 2rem;
        z-index: 9999;
        min-width: 300px;
        padding: 0.75rem 1rem;
        border-radius: 10px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        transform: translateX(100%);
        transition: all 0.3s ease;
    `;
    
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : type === 'warning' ? 'exclamation-circle' : 'info-circle'} me-2"></i>
            <span>${message}</span>
            <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Auto remove
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 300);
    }, 4000);
}

// Handle window resize
window.addEventListener('resize', () => {
    if (window.innerWidth >= 1200) {
        const sidebar = document.getElementById('guardSidebar');
        const overlay = document.querySelector('.sidebar-overlay');
        const body = document.body;
        
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        body.classList.remove('sidebar-open');
    }
});

// Auto-refresh visitor count every 30 seconds
setInterval(refreshVisitorCount, 30000);

// Update shift duration every minute
setInterval(updateShiftDuration, 60000);

// Initialize on document ready
document.addEventListener('DOMContentLoaded', function() {
    // Initial visitor count load
    refreshVisitorCount();
    
    // Initialize login time from session if available
    const sessionStart = localStorage.getItem('guard_session_start');
    if (sessionStart) {
        shiftStartTime = new Date(sessionStart);
    } else {
        localStorage.setItem('guard_session_start', loginTime.toISOString());
    }
    
    // Add click handlers for navigation links
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Add active state handling
            if (!this.classList.contains('active') && this.getAttribute('href') !== '#') {
                // Add loading state
                this.style.opacity = '0.7';
                setTimeout(() => {
                    this.style.opacity = '1';
                }, 300);
            }
        });
    });
    
    // Initialize tooltips if needed
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Show welcome notification
    setTimeout(() => {
        showNotification(`Welcome back, ${document.querySelector('.user-name')?.textContent || 'Guard'}!`, 'success');
    }, 1000);
});

// Enhanced guard account functions
function showProfileSettings() {
    // Create profile settings modal if it doesn't exist
    let modalEl = document.getElementById('guardProfileModal');
    if (!modalEl) {
        const profileModal = `
            <div class="modal fade" id="guardProfileModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #1a4d2e 0%, #2d7a4f 100%); color: white;">
                            <h5 class="modal-title">
                                <i class="fas fa-user-edit me-2"></i>Profile Settings
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="info-card">
                                <h6><i class="fas fa-user me-2"></i>Personal Information</h6>
                                <form>
                                    <div class="mb-3">
                                        <label class="form-label">First Name</label>
                                        <input type="text" class="form-control" value="${document.querySelector('.account-name')?.textContent || 'Guard'}">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" class="form-control" value="User">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Employee ID</label>
                                        <input type="text" class="form-control" value="GRD001">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Contact Number</label>
                                        <input type="tel" class="form-control" value="+1-234-567-8900">
                                    </div>
                                    <button type="submit" class="btn btn-primary">Update Profile</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', profileModal);
        modalEl = document.getElementById('guardProfileModal');
    }
    
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

function showDutyLog() {
    // Create duty log modal if it doesn't exist
    let modalEl = document.getElementById('dutyLogModal');
    if (!modalEl) {
        const dutyModal = `
            <div class="modal fade" id="dutyLogModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #1a4d2e 0%, #2d7a4f 100%); color: white;">
                            <h5 class="modal-title">
                                <i class="fas fa-clipboard-list me-2"></i>Duty Log
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <button class="btn btn-success" onclick="addDutyEntry()">Add New Entry</button>
                            </div>
                            <div class="duty-entries">
                                <div class="info-card">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6>Routine Patrol</h6>
                                            <p class="mb-1">Completed rounds of all building sectors. No incidents reported.</p>
                                            <small class="text-muted">Today, 2:30 PM</small>
                                        </div>
                                        <span class="badge bg-success">Normal</span>
                                    </div>
                                </div>
                                <div class="info-card">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6>Visitor Check-in Issue</h6>
                                            <p class="mb-1">Visitor forgot ID, verified identity through alternative means.</p>
                                            <small class="text-muted">Today, 1:45 PM</small>
                                        </div>
                                        <span class="badge bg-warning">Minor</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', dutyModal);
        modalEl = document.getElementById('dutyLogModal');
    }
    
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

function showSecurityProtocols() {
    // Create security protocols modal if it doesn't exist
    let modalEl = document.getElementById('securityProtocolsModal');
    if (!modalEl) {
        const protocolsModal = `
            <div class="modal fade" id="securityProtocolsModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #1a4d2e 0%, #2d7a4f 100%); color: white;">
                            <h5 class="modal-title">
                                <i class="fas fa-book-open me-2"></i>Security Protocols
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="accordion" id="protocolsAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#emergency">
                                            <i class="fas fa-exclamation-triangle me-2"></i>Emergency Procedures
                                        </button>
                                    </h2>
                                    <div id="emergency" class="accordion-collapse collapse show">
                                        <div class="accordion-body">
                                            <ol>
                                                <li>Assess the situation immediately</li>
                                                <li>Contact emergency services if required</li>
                                                <li>Notify supervisor and administration</li>
                                                <li>Secure the area and ensure safety</li>
                                                <li>Document the incident thoroughly</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#visitor">
                                            <i class="fas fa-users me-2"></i>Visitor Management
                                        </button>
                                    </h2>
                                    <div id="visitor" class="accordion-collapse collapse">
                                        <div class="accordion-body">
                                            <ol>
                                                <li>Verify visitor identity and purpose</li>
                                                <li>Check blacklist before entry</li>
                                                <li>Issue visitor badge and log entry</li>
                                                <li>Escort if required by policy</li>
                                                <li>Ensure proper check-out procedure</li>
                                            </ol>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', protocolsModal);
        modalEl = document.getElementById('securityProtocolsModal');
    }
    
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

function addDutyEntry() {
    const entry = prompt('Enter duty log entry:');
    if (entry) {
        const entryHtml = `
            <div class="info-card">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6>Manual Entry</h6>
                        <p class="mb-1">${entry}</p>
                        <small class="text-muted">Just now</small>
                    </div>
                    <span class="badge bg-info">New</span>
                </div>
            </div>
        `;
        const dutyEntriesEl = document.querySelector('.duty-entries');
        if (dutyEntriesEl) {
            dutyEntriesEl.insertAdjacentHTML('afterbegin', entryHtml);
        }
        showNotification('Duty entry added successfully', 'success');
    }
}

function updateGuardSessionTimer() {
    const sessionStart = localStorage.getItem('guard_session_start') || new Date().toISOString();
    const startTime = new Date(sessionStart);
    const now = new Date();
    const diffMs = now - startTime;
    const hours = Math.floor(diffMs / (1000 * 60 * 60));
    const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
    
    const timerElement = document.getElementById('guardSessionTimer');
    if (timerElement) {
        timerElement.textContent = `${hours}h ${minutes}m`;
    }
}

function initializeEnhancedGuardAccount() {
    // Update session timer every minute
    updateGuardSessionTimer();
    setInterval(updateGuardSessionTimer, 60000);
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

function showEmergencyContacts() {
    // Check if modal already exists
    if (!document.getElementById('emergencyContactsModal')) {
        const emergencyModal = `
            <div class="modal fade" id="emergencyContactsModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fa fa-exclamation-triangle text-warning me-2"></i>
                                Emergency Contacts
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="emergency-card">
                                        <div class="emergency-icon">
                                            <i class="fa fa-fire text-danger"></i>
                                        </div>
                                        <h6>Fire Department</h6>
                                        <p class="emergency-number">911</p>
                                        <small class="text-muted">Fire & Rescue Services</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="emergency-card">
                                        <div class="emergency-icon">
                                            <i class="fa fa-shield-alt text-primary"></i>
                                        </div>
                                        <h6>Police</h6>
                                        <p class="emergency-number">911</p>
                                        <small class="text-muted">Law Enforcement</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="emergency-card">
                                        <div class="emergency-icon">
                                            <i class="fa fa-ambulance text-success"></i>
                                        </div>
                                        <h6>Medical Emergency</h6>
                                        <p class="emergency-number">911</p>
                                        <small class="text-muted">Emergency Medical Services</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="emergency-card">
                                        <div class="emergency-icon">
                                            <i class="fa fa-phone text-info"></i>
                                        </div>
                                        <h6>Security Office</h6>
                                        <p class="emergency-number">(555) 123-0911</p>
                                        <small class="text-muted">Internal Security</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal styles
        const emergencyStyles = `
            <style>
            .emergency-card {
                border: 1px solid #e9ecef;
                border-radius: 12px;
                padding: 1.5rem;
                text-align: center;
                margin-bottom: 1rem;
                transition: all 0.3s ease;
                background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            }
            
            .emergency-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
                border-color: #007bff;
            }
            
            .emergency-icon {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: rgba(0, 123, 255, 0.1);
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1rem;
                font-size: 1.5rem;
            }
            
            .emergency-number {
                font-size: 1.5rem;
                font-weight: 700;
                color: #2d7a4f;
                margin: 0.5rem 0;
            }
            
            .emergency-card h6 {
                font-weight: 600;
                margin-bottom: 0.5rem;
                color: #495057;
            }
            </style>
        `;
        
        document.head.insertAdjacentHTML('beforeend', emergencyStyles);
        document.body.insertAdjacentHTML('beforeend', emergencyModal);
    }
    
    const modalEl = document.getElementById('emergencyContactsModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
    return false;
}

// Initialize enhanced guard account features on document ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('Guard sidebar initialized with vanilla JS');
    
    // Initialize dropdown functionality with longer delay to match admin sidebar
    setTimeout(() => {
        initializeProfileDropdown();
    }, 1000);
    
    // Initialize enhanced guard account if jQuery is available
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ready(function($) {
            initializeEnhancedGuardAccount();
        });
    }
});

// Backup initialization on window load
window.addEventListener('load', function() {
    // Ensure dropdown is initialized even if DOMContentLoaded missed it
    setTimeout(() => {
        const button = document.getElementById('guardProfileDropdown');
        if (button && !button.dataset.initialized) {
            console.log('Guard dropdown backup initialization...');
            initializeProfileDropdown();
        }
    }, 500);
});

// Profile dropdown functionality
function initializeProfileDropdown() {
    const dropdownButton = document.getElementById('guardProfileDropdown');
    const dropdown = document.querySelector('.guard-sidebar .profile-dropdown');
    const dropdownMenu = document.querySelector('.guard-sidebar .account-dropdown');
    
    console.log('Initializing guard profile dropdown...', { dropdownButton, dropdown, dropdownMenu });
    
    if (dropdownButton && dropdown) {
        // Check if already initialized
        if (dropdownButton.dataset.initialized === 'true') {
            console.log('Guard dropdown already initialized, skipping...');
            return;
        }
        
        // Remove any existing Bootstrap dropdown behavior completely
        dropdownButton.removeAttribute('data-bs-toggle');
        dropdownButton.removeAttribute('data-bs-auto-close');
        dropdownButton.classList.remove('dropdown-toggle');
        
        // Clear any existing event listeners
        const newButton = dropdownButton.cloneNode(true);
        dropdownButton.parentNode.replaceChild(newButton, dropdownButton);
        
        // Mark as initialized
        const button = document.getElementById('guardProfileDropdown');
        button.dataset.initialized = 'true';
        
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('Guard dropdown button clicked');
            
            // Toggle dropdown
            const isOpen = dropdown.classList.contains('show');
            
            if (isOpen) {
                closeProfileDropdown();
            } else {
                openProfileDropdown();
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const guardSidebar = document.querySelector('.guard-sidebar');
            if (guardSidebar && !guardSidebar.contains(e.target)) {
                closeProfileDropdown();
            }
        });
        
        // Close dropdown on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeProfileDropdown();
            }
        });
        
        console.log('Guard profile dropdown initialized successfully');
    } else {
        console.error('Guard dropdown elements not found:', { dropdownButton, dropdown });
    }
}

function openProfileDropdown() {
    console.log('Opening guard profile dropdown');
    const dropdown = document.querySelector('.guard-sidebar .profile-dropdown');
    const dropdownButton = document.getElementById('guardProfileDropdown');
    const indicator = dropdown ? dropdown.querySelector('.dropdown-indicator i') : null;
    const dropdownMenu = document.querySelector('.guard-sidebar .account-dropdown');
    
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
        
        console.log('Guard dropdown should now be visible');
    }
}

function closeProfileDropdown() {
    console.log('Closing guard profile dropdown');
    const dropdown = document.querySelector('.guard-sidebar .profile-dropdown');
    const dropdownButton = document.getElementById('guardProfileDropdown');
    const indicator = dropdown ? dropdown.querySelector('.dropdown-indicator i') : null;
    const dropdownMenu = document.querySelector('.guard-sidebar .account-dropdown');
    
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

// Global functions for external use and testing
window.GuardSidebar = {
    openDropdown: openProfileDropdown,
    closeDropdown: closeProfileDropdown,
    initDropdown: initializeProfileDropdown,
    // Test function - call GuardSidebar.testDropdown() in browser console
    testDropdown: function() {
        console.log('Testing guard dropdown...');
        const button = document.getElementById('guardProfileDropdown');
        const dropdown = document.querySelector('.guard-sidebar .profile-dropdown');
        const menu = document.querySelector('.guard-sidebar .account-dropdown');
        
        console.log('Elements found:', { button, dropdown, menu });
        
        if (button) {
            console.log('Simulating button click...');
            button.click();
        }
    }
};

// Cleanup on unload
window.addEventListener('beforeunload', function() {
    // Update session end time
    localStorage.setItem('guard_session_end', new Date().toISOString());
});
</script>