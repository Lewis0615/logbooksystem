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
        SELECT v.*, vis.first_name, vis.last_name, vis.phone,
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
    
    // Get recent activities from visits table
    $recent_activities = $db->fetchAll("
        SELECT v.id, v.status, v.check_in_time, v.check_out_time, v.updated_at, v.purpose,
               vis.first_name AS visitor_first, vis.last_name AS visitor_last,
               u.first_name AS user_first, u.last_name AS user_last
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.id
        LEFT JOIN users u ON v.checked_in_by = u.id
        ORDER BY v.updated_at DESC
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
            COUNT(*) as count
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.id
        WHERE DATE(v.check_in_time) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
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
    
    // Check for new blacklist entries in last 24 hours
    $blacklist_recent = $db->fetch("
        SELECT COUNT(*) as count 
        FROM blacklist 
        WHERE status = 'active'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ")['count'];
    
    if ($blacklist_recent > 0) {
        $alerts[] = [
            'type' => 'warning',
            'icon' => 'fas fa-ban',
            'message' => "$blacklist_recent visitor(s) added to the blocklist in the last 24 hours",
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
    <title>Admin Dashboard</title>
    <link rel="icon" type="image/png" href="/logbooksystem/assets/images/sdsclogo.png">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/vendor/font-awesome.min.css" rel="stylesheet">
    <style>
        :root {
            --accent-blue:    #3b82f6;
            --accent-blue-lt: #dbeafe;
            --accent-teal:    #0d9488;
            --accent-teal-lt: #ccfbf1;
            --accent-orange:  #f59e0b;
            --accent-orange-lt: #fef3c7;
            --accent-red:     #ef4444;
            --accent-red-lt:  #fee2e2;
            --green-500: #22c55e;
            --green-50:  #f0fdf4;
            --green-100: #dcfce7;

            --gray-25:  #fcfcfd;
            --gray-50:  #f8faf9;
            --gray-100: #f0f4f2;
            --gray-200: #e2eae6;
            --gray-300: #c8d6cf;
            --gray-400: #94a8a0;
            --gray-500: #6b7f78;
            --gray-600: #4d5e58;
            --gray-700: #354039;
            --gray-800: #1f2925;
            --gray-900: #111915;

            --border:    #e2eae6;
            --border-lt: #f0f4f2;

            --shadow-xs: 0 1px 2px rgba(0,0,0,.05);
            --shadow-sm: 0 1px 4px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.04);
            --shadow-md: 0 4px 16px rgba(0,0,0,.10), 0 2px 6px rgba(0,0,0,.06);
            --shadow-lg: 0 12px 40px rgba(0,0,0,.13), 0 4px 12px rgba(0,0,0,.06);

            --radius:    14px;
            --radius-sm: 8px;
            --radius-xs: 5px;

            /* legacy aliases used by some components */
            --border-color: #e2eae6;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger:  #ef4444;
            --info:    #3b82f6;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            background: var(--gray-50);
            color: var(--gray-800);
            font-size: 14px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        .main-content {
            margin-left: 280px;
            padding: 28px 30px;
            min-height: 100vh;
            transition: margin-left .3s ease;
        }

        .content-section { margin-bottom: 24px; }

        /* ─── PAGE HEADER ─── */
        .page-header {
            background: linear-gradient(135deg, var(--accent-blue) 0%, #2563eb 55%, #1d4ed8 100%);
            border-radius: var(--radius);
            padding: 28px 32px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 220px; height: 220px;
            border-radius: 50%;
            background: rgba(255,255,255,.05);
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -80px; right: 120px;
            width: 180px; height: 180px;
            border-radius: 50%;
            background: rgba(255,255,255,.03);
        }

        .page-header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .page-header h2 {
            font-family: 'Work Sans', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: #fff;
            margin: 0 0 4px;
            letter-spacing: -.02em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header .subtitle {
            color: rgba(255,255,255,.7);
            font-size: .9rem;
            margin: 0;
        }

        .time-display {
            background: rgba(255,255,255,.15);
            backdrop-filter: blur(10px);
            padding: 12px 18px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,.2);
            color: #fff;
            text-align: right;
        }

        .time-display .h5 {
            font-family: 'Work Sans', sans-serif;
            font-size: .95rem;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .time-display small {
            color: rgba(255,255,255,.7);
            font-size: .78rem;
        }

        /* ─── STATS GRID ─── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px 20px;
            position: relative;
            overflow: hidden;
            transition: transform .25s ease, box-shadow .25s ease;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: var(--radius) var(--radius) 0 0;
        }

        .stat-card.s-blue::before   { background: linear-gradient(90deg, var(--accent-blue), #60a5fa); }
        .stat-card.s-green::before  { background: linear-gradient(90deg, var(--green-500), #4ade80); }
        .stat-card.s-teal::before   { background: linear-gradient(90deg, var(--accent-teal), #14b8a6); }
        .stat-card.s-orange::before { background: linear-gradient(90deg, var(--accent-orange), #fbbf24); }

        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }

        .stat-icon-wrap {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            margin-bottom: 14px;
        }

        .stat-card.s-blue   .stat-icon-wrap { background: var(--accent-blue-lt);   color: var(--accent-blue); }
        .stat-card.s-green  .stat-icon-wrap { background: var(--green-100);         color: #16a34a; }
        .stat-card.s-teal   .stat-icon-wrap { background: var(--accent-teal-lt);   color: var(--accent-teal); }
        .stat-card.s-orange .stat-icon-wrap { background: var(--accent-orange-lt); color: var(--accent-orange); }

        .stat-number {
            font-family: 'Work Sans', sans-serif;
            font-size: 2.6rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 5px;
        }

        .stat-card.s-blue   .stat-number { color: var(--gray-900); }
        .stat-card.s-green  .stat-number { color: #16a34a; }
        .stat-card.s-teal   .stat-number { color: var(--gray-950); }
        .stat-card.s-orange .stat-number { color: var(--gray-800); }

        .stat-label {
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .07em;
            text-transform: uppercase;
            color: var(--gray-400);
        }

        /* ─── CARDS ─── */
        .card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all .3s ease;
        }

        .card:hover { box-shadow: var(--shadow-md); }

        .card-header {
            background: var(--gray-50);
            border-bottom: 1px solid var(--border-lt);
            padding: 16px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header-title {
            font-family: 'Work Sans', sans-serif;
            font-size: .95rem;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 0;
        }

        .card-header-icon {
            width: 30px; height: 30px;
            background: var(--accent-blue-lt);
            color: var(--accent-blue);
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px;
        }

        .card-header-icon.icon-green  { background: var(--green-100);        color: #16a34a; }
        .card-header-icon.icon-orange { background: var(--accent-orange-lt); color: var(--accent-orange); }
        .card-header-icon.icon-red    { background: var(--accent-red-lt);    color: var(--accent-red); }
        .card-header-icon.icon-teal   { background: var(--accent-teal-lt);   color: var(--accent-teal); }

        .card-body { padding: 20px 22px; }

        /* ─── TABLES ─── */
        .table { margin-bottom: 0; }

        .table thead th {
            background: var(--gray-50);
            border-bottom: 2px solid var(--border);
            color: var(--gray-700);
            font-weight: 700;
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            padding: 12px 14px;
        }

        .table tbody td {
            padding: 12px 14px;
            vertical-align: middle;
            color: var(--gray-800);
            font-size: .85rem;
            border-bottom: 1px solid var(--border-lt);
        }

        .table tbody tr { transition: background-color .2s ease; }
        .table tbody tr:hover { background-color: var(--gray-50); }

        /* ─── BADGES ─── */
        .badge {
            padding: 4px 10px;
            font-weight: 600;
            font-size: .72rem;
            border-radius: var(--radius-sm);
        }

        /* ─── VISITOR CARD (active visits list) ─── */
        .active-visitor-item {
            background: #fff;
            border: 1px solid var(--border);
            border-left: 3px solid var(--accent-blue);
            border-radius: var(--radius-sm);
            padding: 14px 16px;
            margin-bottom: 10px;
            transition: transform .2s, box-shadow .2s;
        }

        .active-visitor-item:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
            cursor: pointer;
        }

        .active-visitor-item.overstay {
            border-left-color: var(--accent-red);
            background: #fff8f8;
        }

        /* ─── ACTIVITY FEED ─── */
        .activity-item {
            border-bottom: 1px solid var(--border-lt);
            padding: .875rem 0;
            transition: all .2s ease;
        }

        .activity-item:last-child { border-bottom: none; }
        .activity-item:hover { background: var(--gray-50); border-radius: 6px; }

        /* ─── ALERTS ─── */
        .alert {
            border-radius: var(--radius-sm);
            border: none;
            padding: 13px 18px;
            margin-bottom: 12px;
            font-size: .875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .alert-warning { background: var(--accent-orange-lt); color: #92400e; border-left: 3px solid var(--accent-orange); }
        .alert-danger  { background: var(--accent-red-lt);    color: #991b1b; border-left: 3px solid var(--accent-red); }
        .alert-info    { background: var(--accent-blue-lt);   color: #1e40af; border-left: 3px solid var(--accent-blue); }

        /* ─── QUICK ACTIONS ─── */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 12px;
        }

        .quick-action-btn {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 18px 14px;
            text-align: center;
            text-decoration: none;
            color: var(--gray-700);
            font-family: 'DM Sans', sans-serif;
            font-size: .875rem;
            font-weight: 600;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            transition: all .2s ease;
        }

        .quick-action-btn i {
            font-size: 1.4rem;
            transition: transform .2s ease;
        }

        .quick-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            text-decoration: none;
            color: var(--gray-900);
        }

        .quick-action-btn:hover i { transform: scale(1.1); }

        /* ─── CHARTS ─── */
        .activity-grid  { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .charts-grid    { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .stats-row-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }

        /* ─── PROGRESS bars ─── */
        .progress { border-radius: 8px; background-color: var(--gray-200); height: 8px; }
        .progress-bar { border-radius: 8px; background: var(--accent-blue); }

        /* ─── SECTION TITLES ─── */
        .section-title {
            font-family: 'Work Sans', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title i { color: var(--accent-blue); }

        /* ─── EMPTY STATE ─── */
        .empty-state {
            padding: 3rem 1.5rem;
            text-align: center;
        }

        .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; opacity: .3; }

        /* ─── REFRESH INDICATOR ─── */
        .refresh-indicator {
            position: fixed;
            top: 24px; right: 24px;
            z-index: 1000;
            background: var(--accent-blue);
            color: white;
            padding: .75rem 1.25rem;
            border-radius: 10px;
            display: none;
            box-shadow: var(--shadow-lg);
            font-size: .875rem;
            font-weight: 500;
        }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 1200px) {
            .main-content { margin-left: 0; padding: 1rem; }
            .stats-grid   { gap: 12px; }
            .activity-grid, .charts-grid { gap: 12px; }
        }

        @media (max-width: 992px) {
            .activity-grid, .charts-grid { grid-template-columns: 1fr; }
        }

        @media (max-width: 768px) {
            .page-header h2   { font-size: 1.4rem; }
            .stats-grid       { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .stats-row-grid   { grid-template-columns: 1fr; }
            .quick-actions-grid { grid-template-columns: repeat(2, 1fr); }
            .stat-number      { font-size: 2rem; }
            .time-display     { margin-top: 12px; }
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
            <div class="page-header-inner">
                <div>
                    <h2><i class="fas fa-tachometer-alt"></i>Admin Dashboard</h2>
                    <p class="subtitle">Real-time visitor management overview</p>
                </div>
                <div class="time-display">
                    <div class="h5" id="currentDateTime"><?php echo formatDisplayDateTime(getCurrentDateTime()); ?></div>
                    <small>Last Updated: <span id="lastUpdate">Just now</span></small>
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
            <div class="stat-card s-blue">
                <div class="stat-icon-wrap"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-number"><?php echo number_format($today_stats['total_visits']); ?></div>
                <div class="stat-label">Today's Visits</div>
            </div>
            <div class="stat-card s-teal">
                <div class="stat-icon-wrap"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo number_format($today_stats['current_inside']); ?></div>
                <div class="stat-label">Currently Inside</div>
            </div>
            <div class="stat-card s-green">
                <div class="stat-icon-wrap"><i class="fas fa-chart-line"></i></div>
                <div class="stat-number"><?php echo number_format($week_stats['total_visits']); ?></div>
                <div class="stat-label">This Week</div>
            </div>
            <div class="stat-card s-orange">
                <div class="stat-icon-wrap"><i class="fas fa-chart-bar"></i></div>
                <div class="stat-number"><?php echo number_format($month_stats['total_visits']); ?></div>
                <div class="stat-label">This Month</div>
            </div>
        </div>
        </section>
        
        <!-- Quick Actions -->
        <section class="content-section">
        <div class="row">
            <div class="col-12">
                <h5 class="section-title"><i class="fas fa-bolt"></i>Quick Actions</h5>
                <div class="quick-actions-grid">
                    <a href="admin-visitor-checkin.php" class="quick-action-btn">
                        <i class="fas fa-sign-in-alt" style="color: var(--success);"></i>
                        <span>Check-in Visitor</span>
                    </a>
                    <a href="admin-visitor-checkin.php" class="quick-action-btn">
                        <i class="fas fa-sign-out-alt" style="color: var(--danger);"></i>
                        <span>Check-out Visitor</span>
                    </a>
                    <a href="manage-visitors.php" class="quick-action-btn">
                        <i class="fas fa-users-cog" style="color: var(--primary);"></i>
                        <span>Visitors History</span>
                    </a>
                    <a href="user-management.php" class="quick-action-btn">
                        <i class="fas fa-chart-pie" style="color: var(--gray-600);"></i>
                        <span>User Management</span>
                    </a>
                    <a href="blacklist.php" class="quick-action-btn">
                        <i class="fas fa-ban" style="color: var(--warning);"></i>
                        <span>Blocklist</span>
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
                <div class="card-header">
                    <h5 class="card-header-title">
                        <span class="card-header-icon icon-teal"><i class="fas fa-users"></i></span>
                        Currently Inside
                        <span class="badge bg-primary ms-1"><?php echo count($active_visits); ?></span>
                    </h5>
                    <a href="admin-visitor-checkin.php" class="btn btn-sm" style="background:var(--accent-blue-lt);color:var(--accent-blue);font-weight:600;font-size:.8rem;padding:5px 12px;border-radius:var(--radius-xs);">View All</a>
                </div>
                    <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                        <?php if ($active_visits): ?>
                            <?php foreach ($active_visits as $visit): ?>
                                <div class="active-visitor-item p-3 <?php echo $visit['is_overstay'] ? 'overstay' : ''; ?>"
                                     onclick="window.location.href='admin-visitor-checkin.php?search_term=<?php echo urlencode($visit['visit_pass']); ?>'">
                                    <div class="row align-items-center">
                                        <div class="col-md-5">
                                            <h6 class="mb-1">
                                                <?php echo htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name']); ?>
                                                <?php if ($visit['is_overstay']): ?>
                                                    <span class="badge bg-danger ms-2">Overstay</span>
                                                <?php endif; ?>
                                            </h6>
                                        </div>
                                        <div class="col-md-4">
                                            <p class="mb-0 small">
                                                <strong>Visiting:</strong> <?php echo htmlspecialchars($visit['person_to_visit'] ?: 'Not specified'); ?><br>
                                                <strong>Purpose:</strong> <?php echo htmlspecialchars($visit['purpose'] ?: 'Not specified'); ?>
                                            </p>
                                        </div>
                                        <div class="col-md-3 text-end">
                                            <div class="badge bg-secondary mb-1"><?php echo htmlspecialchars($visit['visit_pass']); ?></div><br>
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
                    <h5 class="card-header-title">
                        <span class="card-header-icon"><i class="fas fa-history"></i></span>
                        Recent Activities
                    </h5>
                </div>
                    <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                        <?php if ($recent_activities): ?>
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item px-3">
                                    <div class="d-flex align-items-start">
                                        <div class="flex-shrink-0 me-2">
                                            <?php
                                            if ($activity['status'] === 'checked_out') {
                                                $act_icon = 'fas fa-sign-out-alt text-danger';
                                                $act_desc = htmlspecialchars($activity['visitor_first'] . ' ' . $activity['visitor_last']) . ' checked out';
                                            } else {
                                                $act_icon = 'fas fa-sign-in-alt text-success';
                                                $act_desc = htmlspecialchars($activity['visitor_first'] . ' ' . $activity['visitor_last']) . ' checked in';
                                                if ($activity['purpose']) $act_desc .= ' &mdash; ' . htmlspecialchars($activity['purpose']);
                                            }
                                            ?>
                                            <i class="<?php echo $act_icon; ?>"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <p class="mb-1 small"><?php echo $act_desc; ?></p>
                                            <small class="text-muted">
                                                <?php if ($activity['user_first'] && $activity['user_last']): ?>
                                                    by <?php echo htmlspecialchars($activity['user_first'] . ' ' . $activity['user_last']); ?> &bull;
                                                <?php endif; ?>
                                                <?php echo formatDisplayDateTime($activity['updated_at']); ?>
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
                        <h5 class="card-header-title">
                            <span class="card-header-icon icon-teal"><i class="fas fa-chart-line"></i></span>
                            Daily Visits (Last 7 Days)
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <canvas id="dailyVisitsChart" height="100"></canvas>
                    </div>
                </div>
            
            <!-- Statistics Summary -->
            <div class="card chart-card h-100">
                    <div class="card-header">
                        <h5 class="card-header-title">
                            <span class="card-header-icon icon-orange"><i class="fas fa-chart-pie"></i></span>
                            Top Visit Purposes
                        </h5>
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

        </div><!-- /.container-fluid -->
    </div><!-- /.main-content -->
    
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
    
    <?php include '../includes/chat-widget.php'; ?>
</body>
</html>