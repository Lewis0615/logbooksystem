<?php
/**
 * Admin Dashboard
 * St. Dominic Savio College - Visitor Management System
 */

require_once '../config/config.php';
require_once '../config/auth.php';

// Require admin or supervisor role
$auth->requireLogin('login.php');
if (!$auth->hasRole(ROLE_ADMIN) && !$auth->hasRole(ROLE_SUPERVISOR)) {
    header('Location: checkin.php');
    exit();
}

$error_message = '';

try {
    // Get current date statistics
    $today_stats = $db->fetch("
        SELECT 
            COUNT(*) as total_visits,
            COUNT(CASE WHEN status = 'checked_in' THEN 1 END) as current_inside,
            COUNT(CASE WHEN status = 'checked_out' THEN 1 END) as completed_today,
            COUNT(CASE WHEN status = 'overstayed' OR 
                         (status = 'checked_in' AND NOW() > expected_checkout_time) THEN 1 END) as overstayed,
            AVG(CASE WHEN actual_duration IS NOT NULL THEN actual_duration END) as avg_duration
        FROM visits 
        WHERE DATE(check_in_time) = CURDATE()
    ");
    
    // Get weekly statistics
    $week_stats = $db->fetch("
        SELECT 
            COUNT(*) as total_visits,
            COUNT(DISTINCT visitor_id) as unique_visitors
        FROM visits 
        WHERE YEARWEEK(check_in_time, 1) = YEARWEEK(CURDATE(), 1)
    ");
    
    // Get monthly statistics
    $month_stats = $db->fetch("
        SELECT 
            COUNT(*) as total_visits,
            COUNT(DISTINCT visitor_id) as unique_visitors
        FROM visits 
        WHERE YEAR(check_in_time) = YEAR(CURDATE()) 
        AND MONTH(check_in_time) = MONTH(CURDATE())
    ");
    
    // Get currently active visits
    $active_visits = $db->fetchAll("
        SELECT v.*, vis.first_name, vis.last_name, vis.phone, vis.company_organization,
               v.person_to_visit, v.purpose,
               TIMESTAMPDIFF(MINUTE, v.check_in_time, NOW()) as duration_minutes,
               CASE WHEN NOW() > v.expected_checkout_time THEN 1 ELSE 0 END as is_overstay
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.id
        WHERE v.status = 'checked_in'
        ORDER BY 
            CASE WHEN NOW() > v.expected_checkout_time THEN 0 ELSE 1 END,
            v.check_in_time DESC
        LIMIT 10
    ");
    
    // Get recent activities
    $recent_activities = $db->fetchAll("
        SELECT al.*, u.first_name, u.last_name
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        ORDER BY al.created_at DESC
        LIMIT 15
    ");
    
    // Get daily visits for the last 7 days (for chart)
    $daily_chart_data = $db->fetchAll("
        SELECT 
            DATE(check_in_time) as visit_date,
            COUNT(*) as visit_count,
            COUNT(CASE WHEN status = 'checked_out' THEN 1 END) as completed_count
        FROM visits 
        WHERE check_in_time >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(check_in_time)
        ORDER BY visit_date ASC
    ");
    
    // Get top visit purposes (simplified)
    $purpose_stats = $db->fetchAll("
        SELECT 
            CASE 
                WHEN purpose IS NULL OR purpose = '' THEN 'Not Specified'
                ELSE purpose 
            END as purpose_name,
            COUNT(*) as count
        FROM visits v
        WHERE DATE(v.check_in_time) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY purpose
        ORDER BY count DESC
        LIMIT 5
    ");
    
    // Get top companies/organizations
    $company_stats = $db->fetchAll("
        SELECT 
            CASE 
                WHEN vis.company_organization IS NULL OR vis.company_organization = '' THEN 'Individual Visitor'
                ELSE vis.company_organization 
            END as company_name,
            COUNT(*) as count
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.id
        WHERE DATE(v.check_in_time) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY vis.company_organization
        ORDER BY count DESC
        LIMIT 5
    ");
    
    // Get system alerts
    $alerts = [];
    
    // Check for overstayed visitors
    $overstayed_count = $db->fetch("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE status = 'checked_in' AND NOW() > expected_checkout_time
    ")['count'];
    
    if ($overstayed_count > 0) {
        $alerts[] = [
            'type' => 'warning',
            'icon' => 'fas fa-clock',
            'message' => "$overstayed_count visitor(s) have overstayed their expected checkout time",
            'action' => 'checkout.php'
        ];
    }
    
    // Check for blacklisted visitor attempts (recent)
    $blacklist_attempts = $db->fetch("
        SELECT COUNT(*) as count 
        FROM activity_logs 
        WHERE action = 'BLACKLIST_ATTEMPT' 
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ")['count'];
    
    if ($blacklist_attempts > 0) {
        $alerts[] = [
            'type' => 'danger',
            'icon' => 'fas fa-ban',
            'message' => "$blacklist_attempts blacklisted visitor access attempt(s) in the last 24 hours",
            'action' => 'blacklist.php'
        ];
    }
    
    // Check for high visitor volume
    if ($today_stats['total_visits'] > 100) {
        $alerts[] = [
            'type' => 'info',
            'icon' => 'fas fa-users',
            'message' => "High visitor volume today: {$today_stats['total_visits']} visits",
            'action' => null
        ];
    }
    
} catch (Exception $e) {
    $error_message = 'Error loading dashboard data: ' . $e->getMessage();
    // Set default values to prevent errors
    $today_stats = ['total_visits' => 0, 'current_inside' => 0, 'completed_today' => 0, 'overstayed' => 0, 'avg_duration' => 0];
    $week_stats = ['total_visits' => 0, 'unique_visitors' => 0];
    $month_stats = ['total_visits' => 0, 'unique_visitors' => 0];
    $active_visits = [];
    $recent_activities = [];
    $daily_chart_data = [];
    $purpose_stats = [];
    $company_stats = [];
    $alerts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/vendor/font-awesome.min.css" rel="stylesheet">
    <style>
        :root {
            /* Modern Green Palette */
            --primary: #069668;
            --primary-dark: #047857;
            --primary-light: #10b981;
            --primary-bg: #ecfdf5;
            --primary-hover: #059669;
            
            /* Accent Colors */
            --accent-gold: #f59e0b;
            --accent-purple: #8b5cf6;
            --accent-blue: #3b82f6;
            --accent-red: #ef4444;
            
            /* Neutral Colors */
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            
            /* Semantic Colors */
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            
            /* UI Colors */
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        * {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            overflow-x: hidden;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
            background: var(--gray-50);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            padding: 2rem;
            margin-bottom: 1.5rem;
            border: none;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        
        .page-header h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.875rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.25rem;
            line-height: 1.2;
            letter-spacing: -0.025em;
        }

        .page-header .subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9375rem;
            font-weight: 400;
            margin-bottom: 0;
        }

        .page-header .time-display {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 0.75rem 1.25rem;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .stats-card {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            position: relative;
            background: white;
            height: 100%;
        }
        
        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary);
        }

        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--card-accent, var(--primary));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stats-card:hover::before {
            opacity: 1;
        }
        
        .stats-card .card-body {
            padding: 1.5rem;
            position: relative;
            z-index: 2;
        }
        
        .stats-number {
            font-family: 'Poppins', sans-serif;
            font-size: 2.25rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            line-height: 1;
            color: var(--gray-900);
            letter-spacing: -0.025em;
        }

        .stats-change {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.125rem 0.5rem;
            border-radius: 6px;
            margin-top: 0.5rem;
        }

        .stats-change.positive {
            color: var(--success);
            background: #ecfdf5;
        }

        .stats-change.negative {
            color: var(--danger);
            background: #fef2f2;
        }
        
        .stats-label {
            color: var(--gray-600);
            font-size: 0.8125rem;
            font-weight: 500;
            margin-bottom: 0.75rem;
            display: block;
        }
        
        .stats-icon {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: var(--icon-bg, #f3f4f6);
            color: var(--icon-color, var(--primary));
            font-size: 1.5rem;
        }
        
        .chart-card, .activity-card {
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            background: white;
            transition: all 0.3s ease;
            height: 100%;
        }

        .chart-card:hover, .activity-card:hover {
            box-shadow: var(--shadow-md);
        }
        
        .card-header {
            background: white;
            color: var(--gray-900);
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }
        
        .card-header h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            margin: 0;
            font-size: 1rem;
            color: var(--gray-900);
        }

        .card-header .btn-outline-primary {
            color: var(--primary);
            border-color: var(--border-color);
            background: var(--gray-50);
            border-radius: 8px;
            padding: 0.375rem 0.875rem;
            font-size: 0.8125rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .card-header .btn-outline-primary:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }

        .stats-row-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .activity-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }

        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }

        .content-section {
            margin-bottom: 2.5rem;
        }

        .content-section:last-child {
            margin-bottom: 0;
        }
        
        .active-visitor-item {
            border-left: 3px solid var(--primary);
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            margin: 0.5rem 0.75rem;
            border-radius: 8px;
            background: white;
            border: 1px solid var(--border-color);
            border-left: 3px solid var(--primary);
        }
        
        .active-visitor-item.overstay {
            border-left-color: var(--danger);
            background: #fef2f2;
        }
        
        .active-visitor-item:hover {
            background: var(--gray-50);
            cursor: pointer;
            transform: translateX(4px);
            box-shadow: var(--shadow-sm);
        }
        
        .activity-item {
            border-bottom: 1px solid var(--border-color);
            padding: 0.875rem 0;
            transition: all 0.2s ease;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-item:hover {
            background: var(--gray-50);
            border-radius: 6px;
        }
        
        .alert-item {
            border-radius: 10px;
            border: 1px solid var(--border-color);
            cursor: pointer;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        
        .alert-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .alert-warning.alert-item {
            border-left-color: var(--warning);
            background: #fffbeb;
        }

        .alert-danger.alert-item {
            border-left-color: var(--danger);
            background: #fef2f2;
        }

        .alert-info.alert-item {
            border-left-color: var(--info);
            background: #eff6ff;
        }
        
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
        }

        .quick-action-btn {
            border-radius: 10px;
            padding: 1rem;
            font-weight: 500;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border-color);
            font-family: 'Inter', sans-serif;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            position: relative;
            overflow: hidden;
            background: white;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .quick-action-btn i {
            font-size: 1.5rem;
            color: var(--btn-icon-color, var(--primary));
            transition: transform 0.2s ease;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--btn-border-hover, var(--primary));
            color: var(--gray-900);
            text-decoration: none;
        }

        .quick-action-btn:hover i {
            transform: scale(1.1);
        }

        .quick-action-btn:active {
            transform: translateY(0);
        }
        
        .btn-success.quick-action-btn {
            --btn-icon-color: var(--success);
            --btn-border-hover: var(--success);
        }
        
        .btn-danger.quick-action-btn {
            --btn-icon-color: var(--danger);
            --btn-border-hover: var(--danger);
        }
        
        .btn-info.quick-action-btn {
            --btn-icon-color: var(--info);
            --btn-border-hover: var(--info);
        }
        
        .btn-primary.quick-action-btn {
            --btn-icon-color: var(--primary);
            --btn-border-hover: var(--primary);
        }
        
        .btn-secondary.quick-action-btn {
            --btn-icon-color: var(--gray-600);
            --btn-border-hover: var(--gray-600);
        }
        
        .btn-warning.quick-action-btn {
            --btn-icon-color: var(--warning);
            --btn-border-hover: var(--warning);
        }
        
        .refresh-indicator {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 1000;
            background: var(--primary);
            color: white;
            padding: 0.75rem 1.25rem;
            border-radius: 10px;
            display: none;
            box-shadow: var(--shadow-lg);
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .badge {
            border-radius: 6px;
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .progress {
            border-radius: 8px;
            background-color: var(--gray-200);
            height: 8px;
        }
        
        .progress-bar {
            background: var(--primary);
            border-radius: 8px;
        }
        
        .progress-bar.bg-info {
            background: var(--info) !important;
        }
        
        h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

        .stat-mini-card {
            background: white;
            border-radius: 10px;
            padding: 1.25rem;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .stat-mini-card:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .stat-mini-card .h4 {
            font-family: 'Poppins', sans-serif;
            font-weight: 700;
            color: var(--gray-900);
        }

        .section-title i {
            color: var(--primary);
        }

        .stats-content-wrapper {
            padding: 1.5rem;
        }

        .progress-list-item:last-child {
            margin-bottom: 0 !important;
        }

        .empty-state {
            padding: 3rem 1.5rem;
            text-align: center;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .stats-grid {
                gap: 1rem;
            }

            .stats-row-grid {
                gap: 1rem;
            }

            .activity-grid {
                gap: 1rem;
            }

            .charts-grid {
                gap: 1rem;
            }

            .quick-actions-grid {
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            }

            .stats-number {
                font-size: 2rem;
            }
        }

        @media (max-width: 768px) {
            .page-header h2 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .stats-row-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .activity-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .charts-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .quick-actions-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .stats-number {
                font-size: 1.75rem;
            }

            .page-header .time-display {
                margin-top: 1rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>
    
    <!-- Auto-refresh indicator -->
    <div class="refresh-indicator" id="refreshIndicator">
        <i class="fas fa-sync-alt fa-spin me-2"></i>Refreshing...
    </div>
    
    <div class="main-content">
        <div class="page-header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-0">
                            <i class="fas fa-tachometer-alt me-2"></i>Admin Dashboard
                        </h2>
                        <p class="mb-0 subtitle">Real-time visitor management overview</p>
                    </div>
                    <div class="col-md-4">
                        <div class="time-display">
                            <div class="h5 mb-1" id="currentDateTime"><?php echo formatDisplayDateTime(getCurrentDateTime()); ?></div>
                            <small class="d-block">Last Updated: <span id="lastUpdate">Just now</span></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="container-fluid">
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-item mb-4">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- System Alerts -->
        <?php if ($alerts): ?>
            <section class="content-section">
            <div class="row">
                <div class="col-12">
                    <h5 class="section-title"><i class="fas fa-bell"></i>System Alerts</h5>
                    <?php foreach ($alerts as $alert): ?>
                        <div class="alert alert-<?php echo $alert['type']; ?> alert-item mb-2" 
                             <?php if ($alert['action']): ?>onclick="window.location.href='<?php echo $alert['action']; ?>'"<?php endif; ?>>
                            <i class="<?php echo $alert['icon']; ?> me-2"></i>
                            <?php echo $alert['message']; ?>
                            <?php if ($alert['action']): ?>
                                <i class="fas fa-arrow-right ms-2"></i>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            </section>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <section class="content-section">
        <div class="stats-grid">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="stats-icon-bg" style="background: rgba(6, 150, 104, 0.1); color: var(--primary);">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($today_stats['total_visits']); ?></div>
                    <div class="stats-label">Today's Visits</div>
                </div>
            </div>
            <div class="card stats-card">
                <div class="card-body">
                    <div class="stats-icon-bg" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($today_stats['current_inside']); ?></div>
                    <div class="stats-label">Currently Inside</div>
                </div>
            </div>
            <div class="card stats-card">
                <div class="card-body">
                    <div class="stats-icon-bg" style="background: rgba(59, 130, 246, 0.1); color: var(--info);">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($week_stats['total_visits']); ?></div>
                    <div class="stats-label">This Week</div>
                </div>
            </div>
            <div class="card stats-card">
                <div class="card-body">
                    <div class="stats-icon-bg" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($month_stats['total_visits']); ?></div>
                    <div class="stats-label">This Month</div>
                </div>
            </div>
        </div>
        </section>
        
        <!-- Quick Actions -->
        <section class="content-section">
        <div class="row">
            <div class="col-12">
                <h5 class="section-title"><i class="fas fa-bolt"></i>Quick Actions</h5>
                <div class="quick-actions-grid">
                    <a href="checkin.php" class="quick-action-btn">
                        <i class="fas fa-sign-in-alt" style="color: var(--success);"></i>
                        <span>Check-in Visitor</span>
                    </a>
                    <a href="checkout.php" class="quick-action-btn">
                        <i class="fas fa-sign-out-alt" style="color: var(--danger);"></i>
                        <span>Check-out Visitor</span>
                    </a>
                    <a href="current-visitors.php" class="quick-action-btn">
                        <i class="fas fa-users" style="color: var(--info);"></i>
                        <span>Current Visitors</span>
                    </a>
                    <a href="manage-visitors.php" class="quick-action-btn">
                        <i class="fas fa-users-cog" style="color: var(--primary);"></i>
                        <span>Manage Visitors</span>
                    </a>
                    <a href="reports.php" class="quick-action-btn">
                        <i class="fas fa-chart-pie" style="color: var(--gray-600);"></i>
                        <span>View Reports</span>
                    </a>
                    <a href="blacklist.php" class="quick-action-btn">
                        <i class="fas fa-ban" style="color: var(--warning);"></i>
                        <span>Blacklist</span>
                    </a>
                </div>
            </div>
        </div>
        </section>
        
        <!-- Active Visitors & Recent Activities -->
        <section class="content-section">
        <div class="activity-grid">
            <!-- Active Visitors -->
            <div class="card activity-card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas fa-users me-2"></i>Currently Inside 
                            <span class="badge bg-light text-dark ms-2"><?php echo count($active_visits); ?></span>
                        </h6>
                        <a href="checkout.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                        <?php if ($active_visits): ?>
                            <?php foreach ($active_visits as $visit): ?>
                                <div class="active-visitor-item p-3 <?php echo $visit['is_overstay'] ? 'overstay' : ''; ?>"
                                     onclick="window.location.href='checkout.php?search_term=<?php echo urlencode($visit['visitor_pass']); ?>'">
                                    <div class="row align-items-center">
                                        <div class="col-md-5">
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name']); ?>
                                                <?php if ($visit['is_overstay']): ?>
                                                    <span class="badge bg-danger ms-2">Overstay</span>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="text-muted mb-0 small">
                                                <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($visit['phone']); ?>
                                                <?php if ($visit['company_organization']): ?>
                                                    <br><i class="fas fa-building me-1"></i><?php echo htmlspecialchars($visit['company_organization']); ?>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="col-md-4">
                                            <p class="mb-0 small">
                                                <strong>Visiting:</strong> <?php echo htmlspecialchars($visit['person_to_visit'] ?: 'Not specified'); ?><br>
                                                <strong>Purpose:</strong> <?php echo htmlspecialchars($visit['purpose'] ?: 'Not specified'); ?>
                                            </p>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <div class="badge bg-secondary mb-1"><?php echo htmlspecialchars($visit['visitor_pass']); ?></div><br>
                                            <span class="badge bg-<?php echo $visit['is_overstay'] ? 'danger' : 'success'; ?>">
                                                <i class="fas fa-clock me-1"></i>
                                                <?php 
                                                $hours = floor($visit['duration_minutes'] / 60);
                                                $minutes = $visit['duration_minutes'] % 60;
                                                echo "${hours}h ${minutes}m";
                                                ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-user-slash text-muted"></i>
                                <h6 class="text-muted mb-0">No visitors currently inside</h6>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            
            <!-- Recent Activities -->
            <div class="card activity-card h-100">
                <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Recent Activities</h6>
                    </div>
                    <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                        <?php if ($recent_activities): ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item px-3">
                                    <div class="d-flex align-items-start">
                                        <div class="flex-shrink-0 me-2">
                                            <?php
                                            $icon = 'fas fa-info-circle text-info';
                                            switch ($activity['action']) {
                                                case 'VISITOR_CHECKIN': $icon = 'fas fa-sign-in-alt text-success'; break;
                                                case 'VISITOR_CHECKOUT': $icon = 'fas fa-sign-out-alt text-danger'; break;
                                                case 'VISITOR_REGISTRATION': $icon = 'fas fa-user-plus text-primary'; break;
                                                case 'LOGIN': $icon = 'fas fa-key text-info'; break;
                                                case 'LOGOUT': $icon = 'fas fa-sign-out-alt text-secondary'; break;
                                                case 'BLACKLIST_ADD': $icon = 'fas fa-ban text-warning'; break;
                                            }
                                            ?>
                                            <i class="<?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <p class="mb-1 small"><?php echo htmlspecialchars($activity['description']); ?></p>
                                            <small class="text-muted">
                                                <?php if ($activity['first_name'] && $activity['last_name']): ?>
                                                    by <?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?> •
                                                <?php endif; ?>
                                                <?php echo formatDisplayDateTime($activity['created_at']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-history text-muted"></i>
                                <p class="text-muted mb-0">No recent activities</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
        </div>
        </section>
        
        <!-- Charts Row -->
        <section class="content-section">
        <div class="charts-grid">
            <!-- Daily Visits Chart -->
            <div class="card chart-card h-100">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Daily Visits (Last 7 Days)</h6>
                    </div>
                    <div class="card-body p-4">
                        <canvas id="dailyVisitsChart" height="100"></canvas>
                    </div>
                </div>
            
            <!-- Statistics Summary -->
            <div class="card chart-card h-100">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Top Visit Purposes</h6>
                    </div>
                    <div class="card-body stats-content-wrapper">
                        <?php if ($purpose_stats): ?>
                            <?php 
                            $total_purposes = array_sum(array_column($purpose_stats, 'count'));
                            foreach ($purpose_stats as $purpose): 
                                $percentage = $total_purposes > 0 ? round(($purpose['count'] / $total_purposes) * 100) : 0;
                            ?>
                                <div class="mb-3 progress-list-item">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small fw-semibold text-dark"><?php echo htmlspecialchars($purpose['purpose_name']); ?></span>
                                        <span class="badge bg-primary"><?php echo $purpose['count']; ?></span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar" role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%" 
                                             aria-valuenow="<?php echo $percentage; ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state py-5">
                                <i class="fas fa-chart-pie text-muted"></i>
                                <p class="text-muted mb-0">No data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
        </div>
        </section>
        
        <!-- Additional Stats Row -->
        <section class="content-section">
        <div class="stats-row-grid">
            <div class="card chart-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-building me-2"></i>Top Organizations/Companies</h6>
                </div>
                    <div class="card-body stats-content-wrapper">
                        <?php if ($company_stats): ?>
                            <?php 
                            $total_company_visits = array_sum(array_column($company_stats, 'count'));
                            foreach ($company_stats as $company): 
                                $percentage = $total_company_visits > 0 ? round(($company['count'] / $total_company_visits) * 100) : 0;
                            ?>
                                <div class="mb-3 progress-list-item">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="small fw-semibold text-dark"><?php echo htmlspecialchars($company['company_name']); ?></span>
                                        <span class="badge bg-info"><?php echo $company['count']; ?></span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-info" role="progressbar" 
                                             style="width: <?php echo $percentage; ?>%" 
                                             aria-valuenow="<?php echo $percentage; ?>" 
                                             aria-valuemin="0" aria-valuemax="100">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state py-5">
                                <i class="fas fa-building text-muted"></i>
                                <p class="text-muted mb-0">No data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            
            <div class="card chart-card h-100">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Quick Stats</h6>
                </div>
                    <div class="card-body stats-content-wrapper">
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="stat-mini-card text-center">
                                    <div class="h4 text-primary mb-1"><?php echo number_format($today_stats['completed_today']); ?></div>
                                    <small class="text-muted fw-medium">Completed Today</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-mini-card text-center">
                                    <div class="h4 text-warning mb-1"><?php echo number_format($today_stats['overstayed']); ?></div>
                                    <small class="text-muted fw-medium">Overstayed</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-mini-card text-center">
                                    <div class="h4 text-info mb-1">
                                    <?php 
                                    if ($today_stats['avg_duration']) {
                                        $avg_hours = floor($today_stats['avg_duration'] / 60);
                                        $avg_minutes = round($today_stats['avg_duration'] % 60);
                                        echo $avg_hours . 'h ' . $avg_minutes . 'm';
                                    } else {
                                        echo '--';
                                    }
                                    ?>
                                    </div>
                                    <small class="text-muted fw-medium">Avg Duration</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="stat-mini-card text-center">
                                    <div class="h4 text-success mb-1"><?php echo number_format($month_stats['unique_visitors']); ?></div>
                                    <small class="text-muted fw-medium">Unique Visitors (Month)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
        </div>
        </section>
        </div>
    </div>
    
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/vendor/chart.min.js"></script>
    <script>
        // Update current time
        function updateDateTime() {
            const now = new Date();
            const options = {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            document.getElementById('currentDateTime').textContent = now.toLocaleDateString('en-US', options);
            
            // Update last update time
            document.getElementById('lastUpdate').textContent = now.toLocaleTimeString();
        }
        
        // Auto-refresh page data every 30 seconds
        function autoRefresh() {
            document.getElementById('refreshIndicator').style.display = 'block';
            
            setTimeout(function() {
                location.reload();
            }, 1000);
        }
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Daily visits chart
            const dailyCtx = document.getElementById('dailyVisitsChart').getContext('2d');
            
            const chartData = <?php echo json_encode($daily_chart_data); ?>;
            const labels = chartData.map(item => {
                const date = new Date(item.visit_date);
                return date.toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
            });
            const visitCounts = chartData.map(item => item.visit_count);
            const completedCounts = chartData.map(item => item.completed_count);
            
            new Chart(dailyCtx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Total Visits',
                        data: visitCounts,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        borderWidth: 3,
                        fill: true
                    }, {
                        label: 'Completed Visits',
                        data: completedCounts,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        borderWidth: 2,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
            
            // Update time every second
            updateDateTime();
            setInterval(updateDateTime, 1000);
            
            // Auto-refresh every 30 seconds
            setInterval(autoRefresh, 30000);
        });
    </script>
</body>
</html>