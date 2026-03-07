<?php
/**
 * Guard Visitor Management Interface
 * St. Dominic Savio College - Visitor Management System
 */

require_once '../config/config.php';
require_once '../config/auth.php';
require_once '../config/settings.php';

// Check authentication
$auth->requireLogin('login.php');

$error_message = '';
$success_message = '';

// Handle check-in submission for existing visitors
if ($_POST && isset($_POST['checkin_existing_visitor'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $visitor_id = (int)($_POST['visitor_id'] ?? 0);
    
    if (!validateCSRFToken($csrf_token)) {
        $error_message = 'Security token validation failed.';
    } elseif ($visitor_id <= 0) {
        $error_message = 'Invalid visitor selection.';
    } else {
        try {
            $visitor = $db->fetch("SELECT * FROM visitors WHERE id = ?", [$visitor_id]);
            
            if (!$visitor) {
                $error_message = 'Visitor not found.';
            } else {
                $existing_visit = $db->fetch(
                    "SELECT * FROM visits WHERE visitor_id = ? AND status = 'checked_in'",
                    [$visitor_id]
                );
                
                if ($existing_visit) {
                    $error_message = 'This visitor is already checked in.';
                } else {
                    $blacklisted = $db->fetch(
                        "SELECT * FROM blacklist WHERE 
                         (visitor_id = ? OR phone = ?) AND status = 'active' 
                         AND (is_permanent = 1 OR expiry_date >= CURDATE())",
                        [$visitor_id, $visitor['phone']]
                    );
                    
                    if ($blacklisted) {
                        $error_message = 'This visitor is blacklisted and cannot be checked in.';
                        $auth->logActivity($_SESSION['user_id'], 'BLACKLIST_ATTEMPT', 
                            "Blocked blacklisted visitor: {$visitor['first_name']} {$visitor['last_name']} ({$visitor['phone']})");
                    } else {
                        $expected_duration = DEFAULT_VISIT_DURATION;
                        $expected_checkout = date('Y-m-d H:i:s', strtotime("+$expected_duration minutes"));
                        $visitor_pass = generateVisitorPass();
                        
                        $db->execute(
                            "INSERT INTO visits (visitor_id, visit_pass, check_in_time, expected_checkout_time, 
                             expected_duration, status, checked_in_by) 
                             VALUES (?, ?, NOW(), ?, ?, 'checked_in', ?)",
                            [$visitor_id, $visitor_pass, $expected_checkout, 
                             $expected_duration, $_SESSION['user_id']]
                        );
                        
                        $auth->logActivity($_SESSION['user_id'], 'VISITOR_CHECKIN', 
                            "Checked in visitor: {$visitor['first_name']} {$visitor['last_name']} - Pass: $visitor_pass");
                        
                        $success_message = "Visitor checked in successfully! Visitor Pass: $visitor_pass";
                    }
                }
            }
        } catch (Exception $e) {
            $error_message = 'Error checking in visitor: ' . $e->getMessage();
        }
    }
}

// Handle AJAX requests for registered visitors
if (isset($_GET['action']) && $_GET['action'] === 'get_registered_visitors') {
    header('Content-Type: application/json');
    
    try {
        $registered_visitors = $db->fetchAll("
            SELECT v.*, 
                   (SELECT COUNT(*) FROM visits WHERE visitor_id = v.id AND status = 'checked_in') as currently_checked_in,
                   (SELECT MAX(check_in_time) FROM visits WHERE visitor_id = v.id) as last_visit,
                   (CASE WHEN EXISTS(SELECT 1 FROM visits WHERE visitor_id = v.id AND status = 'checked_in') 
                         THEN 1 ELSE 0 END) as is_currently_checked_in,
                   (CASE WHEN EXISTS(SELECT 1 FROM visits WHERE visitor_id = v.id AND status = 'checked_out' AND DATE(check_in_time) = CURDATE())
                         THEN 1 ELSE 0 END) as checked_out_today
            FROM visitors v 
            ORDER BY v.created_at DESC
        ");
        
        echo json_encode([
            'success' => true,
            'visitors' => $registered_visitors
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error loading registered visitors: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Handle AJAX request for visitor details
if (isset($_GET['action']) && $_GET['action'] === 'get_visitor_details' && isset($_GET['visitor_id'])) {
    header('Content-Type: application/json');
    
    $visitor_id = (int)($_GET['visitor_id'] ?? 0);
    
    if ($visitor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid visitor ID.']);
        exit;
    }
    
    try {
        $visitor = $db->fetch("
            SELECT v.*,
                   vi.id           AS visit_id,
                   vi.visit_pass,
                   vi.check_in_time,
                   vi.check_out_time,
                   vi.expected_checkout_time,
                   vi.department,
                   vi.purpose,
                   vi.person_to_visit,
                   vi.is_group_visit,
                   vi.group_size,
                   vi.group_members,
                   vi.additional_notes,
                   vi.status       AS visit_status,
                   TIMESTAMPDIFF(MINUTE, vi.check_in_time,
                       IFNULL(vi.check_out_time, NOW())) AS duration_minutes,
                   CASE WHEN vi.status = 'checked_in'
                             AND NOW() > vi.expected_checkout_time
                        THEN 1 ELSE 0 END AS is_overdue
            FROM visitors v
            LEFT JOIN visits vi
                ON vi.id = (
                    SELECT id FROM visits
                    WHERE visitor_id = v.id
                    ORDER BY check_in_time DESC
                    LIMIT 1
                )
            WHERE v.id = ?
        ", [$visitor_id]);
        
        if (!$visitor) {
            echo json_encode(['success' => false, 'message' => 'Visitor not found.']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'visitor' => $visitor
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error loading visitor details: ' . $e->getMessage()]);
        exit;
    }
}
if (isset($_GET['action']) && $_GET['action'] === 'checkin_existing' && isset($_GET['visitor_id'])) {
    header('Content-Type: application/json');
    
    $visitor_id = (int)($_GET['visitor_id'] ?? 0);
    $csrf_token = $_GET['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Security token validation failed.']);
        exit;
    }
    
    if ($visitor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid visitor ID.']);
        exit;
    }
    
    try {
        $visitor = $db->fetch("SELECT * FROM visitors WHERE id = ?", [$visitor_id]);
        
        if (!$visitor) {
            echo json_encode(['success' => false, 'message' => 'Visitor not found.']);
            exit;
        }
        
        $existing_visit = $db->fetch(
            "SELECT * FROM visits WHERE visitor_id = ? AND status = 'checked_in'",
            [$visitor_id]
        );
        
        if ($existing_visit) {
            echo json_encode(['success' => false, 'message' => 'This visitor is already checked in.']);
            exit;
        }

        $blacklisted = $db->fetch(
            "SELECT * FROM blacklist WHERE 
             (visitor_id = ? OR phone = ?) AND status = 'active' 
             AND (is_permanent = 1 OR expiry_date >= CURDATE())",
            [$visitor_id, $visitor['phone']]
        );
        
        if ($blacklisted) {
            $auth->logActivity($_SESSION['user_id'], 'BLACKLIST_ATTEMPT', 
                "Blocked blacklisted visitor: {$visitor['first_name']} {$visitor['last_name']} ({$visitor['phone']})");
            echo json_encode(['success' => false, 'message' => 'This visitor is blacklisted and cannot be checked in.']);
            exit;
        }

        // Campus capacity check
        $maxCampusCapacity = (int)getSystemSetting('max_group_size', '0');
        if ($maxCampusCapacity > 0) {
            $occRow = $db->fetch(
                "SELECT COALESCE(SUM(CASE WHEN is_group_visit = 1 THEN COALESCE(NULLIF(group_size, 0), 1) ELSE 1 END), 0) AS total_people
                 FROM visits WHERE status = 'checked_in'"
            );
            $currentPeople = (int)($occRow['total_people'] ?? 0);
            if (($currentPeople + 1) > $maxCampusCapacity) {
                echo json_encode(['success' => false, 'message' => "Campus capacity limit of {$maxCampusCapacity} " . ($maxCampusCapacity === 1 ? 'visitor' : 'visitors') . ' has been reached. Check-ins are paused until someone checks out.']);
                exit;
            }
        }

        $expected_duration = DEFAULT_VISIT_DURATION;
        $expected_checkout = date('Y-m-d H:i:s', strtotime("+$expected_duration minutes"));
        $visitor_pass = generateVisitorPass();
        
        $db->execute(
            "INSERT INTO visits (visitor_id, visit_pass, check_in_time, expected_checkout_time, 
             expected_duration, status, checked_in_by) 
             VALUES (?, ?, NOW(), ?, ?, 'checked_in', ?)",
            [$visitor_id, $visitor_pass, $expected_checkout, 
             $expected_duration, $_SESSION['user_id']]
        );
        
        $auth->logActivity($_SESSION['user_id'], 'VISITOR_CHECKIN', 
            "Checked in visitor: {$visitor['first_name']} {$visitor['last_name']} - Pass: $visitor_pass");
        
        echo json_encode([
            'success' => true,
            'message' => "Visitor {$visitor['first_name']} {$visitor['last_name']} checked in successfully!",
            'visitor_pass' => $visitor_pass
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error checking in visitor: ' . $e->getMessage()]);
        exit;
    }
}

// Handle checkout via AJAX
if (isset($_GET['action']) && $_GET['action'] === 'checkout' && isset($_GET['visit_id'])) {
    header('Content-Type: application/json');
    
    $visit_id = (int)($_GET['visit_id'] ?? 0);
    $csrf_token = $_GET['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'Security token validation failed.']);
        exit;
    }
    
    try {
        $visit = $db->fetch(
            "SELECT v.*, vis.first_name, vis.last_name 
             FROM visits v 
             JOIN visitors vis ON v.visitor_id = vis.id 
             WHERE v.id = ? AND v.status = 'checked_in'",
            [$visit_id]
        );
        
        if (!$visit) {
            echo json_encode(['success' => false, 'message' => 'Visit not found or already checked out.']);
            exit;
        }
        
        $checkin_time = strtotime($visit['check_in_time']);
        $checkout_time = time();
        $actual_duration = round(($checkout_time - $checkin_time) / 60);
        
        $db->execute(
            "UPDATE visits SET status = 'checked_out', check_out_time = NOW(), 
             actual_duration = ?, checked_out_by = ? WHERE id = ?",
            [$actual_duration, $_SESSION['user_id'], $visit_id]
        );
        
        $auth->logActivity($_SESSION['user_id'], 'VISITOR_CHECKOUT', 
            "Checked out visitor: {$visit['first_name']} {$visit['last_name']} - Duration: {$actual_duration} minutes");
        
        echo json_encode([
            'success' => true, 
            'message' => "Visitor {$visit['first_name']} {$visit['last_name']} checked out successfully!",
            'duration' => $actual_duration
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error checking out visitor: ' . $e->getMessage()]);
        exit;
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Management</title>
    <link rel="icon" type="image/png" href="/logbooksystem/assets/images/sdsclogo.png">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/vendor/font-awesome.min.css" rel="stylesheet">
    <style>
        :root {
            --green-950: #071a10;
            --green-900: #0d2b1a;
            --green-800: #163d27;
            --green-700: #1e5235;
            --green-600: #256642;
            --green-500: #2d7a4f;
            --green-400: #3a9962;
            --green-300: #52c47d;
            --green-200: #86dba4;
            --green-100: #d4f4e2;
            --green-50:  #edfaf4;

            --accent-blue:    #3b82f6;
            --accent-blue-lt: #dbeafe;
            --accent-teal:    #0d9488;
            --accent-teal-lt: #ccfbf1;
            --accent-orange:  #f59e0b;
            --accent-orange-lt: #fef3c7;
            --accent-red:     #ef4444;
            --accent-red-lt:  #fee2e2;

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

            --border:   #e2eae6;
            --border-lt:#f0f4f2;

            --shadow-xs: 0 1px 2px rgba(0,0,0,.05);
            --shadow-sm: 0 1px 4px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.04);
            --shadow-md: 0 4px 16px rgba(0,0,0,.10), 0 2px 6px rgba(0,0,0,.06);
            --shadow-lg: 0 12px 40px rgba(0,0,0,.13), 0 4px 12px rgba(0,0,0,.06);

            --radius:    14px;
            --radius-sm: 8px;
            --radius-xs: 5px;
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

        /* ─── LAYOUT ─── */
        .main-content {
            margin-left: 260px;
            padding: 28px 30px;
            min-height: 100vh;
            transition: margin-left .3s ease;
        }

        /* ─── PAGE HEADER ─── */
        .page-header {
            background: linear-gradient(135deg, var(--green-700) 0%, var(--green-600) 55%, var(--green-500) 100%);
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

        .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 20px;
            padding: 7px 16px;
            font-size: .8rem;
            font-weight: 600;
            color: #fff;
            backdrop-filter: blur(4px);
        }

        .live-dot {
            width: 8px; height: 8px;
            background: #4ade80;
            border-radius: 50%;
            box-shadow: 0 0 0 2px rgba(74,222,128,.3);
            animation: livePulse 2s infinite;
        }

        @keyframes livePulse {
            0%, 100% { box-shadow: 0 0 0 2px rgba(74,222,128,.3); }
            50%       { box-shadow: 0 0 0 6px rgba(74,222,128,.08); }
        }

        /* ─── ALERTS ─── */
        .alert {
            border-radius: var(--radius-sm);
            border: none;
            padding: 13px 18px;
            margin-bottom: 20px;
            font-size: .875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger  { background: var(--accent-red-lt);    color: #991b1b; border-left: 3px solid var(--accent-red); }
        .alert-success { background: var(--green-50);          color: var(--green-700); border-left: 3px solid var(--green-400); }

        /* ─── STATS ROW ─── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 22px 20px;
            position: relative;
            overflow: hidden;
            transition: transform .25s ease, box-shadow .25s ease;
            animation: fadeUp .4s ease both;
        }

        .stat-card:nth-child(1) { animation-delay: .05s; }
        .stat-card:nth-child(2) { animation-delay: .10s; }
        .stat-card:nth-child(3) { animation-delay: .15s; }
        .stat-card:nth-child(4) { animation-delay: .20s; }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: var(--radius) var(--radius) 0 0;
        }

        .stat-card.s-green::before  { background: linear-gradient(90deg, var(--green-400), var(--green-300)); }
        .stat-card.s-teal::before   { background: linear-gradient(90deg, var(--accent-teal), #14b8a6); }
        .stat-card.s-blue::before   { background: linear-gradient(90deg, var(--accent-blue), #60a5fa); }
        .stat-card.s-orange::before { background: linear-gradient(90deg, var(--accent-orange), #fbbf24); }

        .stat-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }

        .stat-icon-wrap {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
            margin-bottom: 14px;
        }

        .stat-card.s-green  .stat-icon-wrap { background: var(--green-100);         color: var(--green-600); }
        .stat-card.s-teal   .stat-icon-wrap { background: var(--accent-teal-lt);    color: var(--accent-teal); }
        .stat-card.s-blue   .stat-icon-wrap { background: var(--accent-blue-lt);    color: var(--accent-blue); }
        .stat-card.s-orange .stat-icon-wrap { background: var(--accent-orange-lt);  color: var(--accent-orange); }

        .stat-number {
            font-family: 'Work Sans', sans-serif;
            font-size: 2.6rem;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 5px;
        }

        .stat-card.s-green  .stat-number { color: var(--green-500); }
        .stat-card.s-teal   .stat-number { color: var(--accent-teal); }
        .stat-card.s-blue   .stat-number { color: var(--accent-blue); }
        .stat-card.s-orange .stat-number { color: var(--accent-orange); }

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
            animation: fadeUp .4s ease .25s both;
        }

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
            background: var(--green-100);
            color: var(--green-600);
            border-radius: 7px;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px;
        }

        .card-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 20px 22px;
        }

        .card-body-scroll {
            padding: 20px 22px;
            max-height: 620px;
            overflow-y: auto;
        }

        /* ─── SEARCH ─── */
        .search-wrap {
            position: relative;
            margin-bottom: 16px;
        }

        .search-wrap .fa-search {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 13px;
        }

        .search-input {
            width: 100%;
            padding: 9px 12px 9px 34px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            color: var(--gray-800);
            background: #fff;
            transition: border-color .2s, box-shadow .2s;
            outline: none;
        }

        .search-input:focus {
            border-color: var(--green-400);
            box-shadow: 0 0 0 3px rgba(58,153,98,.12);
        }

        .search-input::placeholder { color: var(--gray-400); }

        /* ─── TABLE HEADER ─── */
        .tbl-head {
            display: grid;
            gap: 12px;
            padding: 10px 14px;
            background: var(--gray-50);
            border: 1px solid var(--border-lt);
            border-radius: var(--radius-sm);
            margin-bottom: 12px;
            font-size: .7rem;
            font-weight: 700;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--gray-400);
        }

        .tbl-head.registered { grid-template-columns: 2fr 1fr 1fr 1fr 90px; }
        .tbl-head.current    { grid-template-columns: 1.5fr 1fr 1.2fr 0.8fr 1fr 1fr 1.2fr; }

        /* ─── VISITOR TABLE ─── */
        .visitor-row {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            margin-bottom: 8px;
            transition: transform .2s, box-shadow .2s;
            display: grid;
            grid-template-columns: 1.5fr 1fr 1.2fr 0.8fr 1fr 1fr 1.2fr;
            gap: 12px;
            padding: 12px 14px;
            align-items: center;
            font-size: .85rem;
        }

        .visitor-row:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .visitor-cell { padding: 0; }

        .visitor-name {
            font-weight: 700;
            color: var(--gray-950);
            font-size: .9rem;
            margin-bottom: 2px;
        }

        .visitor-phone {
            font-size: .8rem;
            color: var(--gray-500);
        }

        .purpose-text {
            font-size: .82rem;
            color: var(--gray-700);
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .group-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: .7rem;
            font-weight: 600;
        }

        .group-yes { background: var(--green-100); color: var(--green-600); }
        .group-no  { background: var(--gray-100);  color: var(--gray-500); }

        .datetime-text {
            font-size: .8rem;
            color: var(--gray-600);
            font-family: 'DM Mono', monospace;
        }

        .action-buttons { display: flex; gap: 6px; align-items: center; }

        /* ─── BUTTONS ─── */
        .btn-refresh {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px; background: #fff; border: 1px solid var(--border);
            border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif;
            font-size: .8rem; font-weight: 600; color: var(--gray-600); cursor: pointer;
            transition: all .2s;
        }
        .btn-refresh:hover { background: var(--gray-100); color: var(--gray-800); }

        .btn-checkin {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px;
            background: linear-gradient(135deg, var(--green-500), var(--green-400));
            border: none; border-radius: var(--radius-sm);
            font-family: 'DM Sans', sans-serif; font-size: .8rem; font-weight: 600;
            color: #fff; cursor: pointer; box-shadow: 0 2px 8px rgba(37,102,66,.28);
            transition: all .2s;
        }
        .btn-checkin:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(37,102,66,.38); }

        .btn-checkout {
            display: inline-flex; align-items: center; justify-content: center; gap: 4px;
            padding: 6px 12px; background: var(--accent-red); border: none;
            border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif;
            font-size: .75rem; font-weight: 600; color: #fff; cursor: pointer; transition: all .2s;
        }
        .btn-checkout:hover { background: #dc2626; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(239,68,68,.35); }

        .btn-view {
            display: inline-flex; align-items: center; justify-content: center; gap: 4px;
            padding: 8px 14px; background: linear-gradient(135deg, #3b82f6, #60a5fa);
            border: none; border-radius: var(--radius-sm);
            font-family: 'DM Sans', sans-serif; font-size: .75rem; font-weight: 600;
            color: #fff; cursor: pointer; transition: all .2s;
            box-shadow: 0 2px 8px rgba(59,130,246,.28); min-width: 70px;
        }
        .btn-view:hover { background: linear-gradient(135deg, #2563eb, #3b82f6); transform: translateY(-1px); box-shadow: 0 4px 14px rgba(59,130,246,.35); }

        .btn-view-outline {
            display: inline-flex; align-items: center; justify-content: center; gap: 4px;
            padding: 6px 12px; background: transparent; border: 1.5px solid #3b82f6;
            border-radius: var(--radius-sm); font-family: 'DM Sans', sans-serif;
            font-size: .7rem; font-weight: 600; color: #3b82f6; cursor: pointer;
            transition: all .2s; min-width: 60px;
        }
        .btn-view-outline:hover { background: #3b82f6; color: #fff; transform: translateY(-1px); box-shadow: 0 2px 8px rgba(59,130,246,.25); }

        /* ─── REGISTERED VISITOR ITEM ─── */
        .rv-name { font-weight: 700; font-size: .88rem; color: var(--gray-900); margin-bottom: 2px; }
        .rv-meta { font-size: .75rem; color: var(--gray-500); line-height: 1.5; }
        .rv-meta i { width: 14px; opacity: .7; }

        /* ─── BADGE STATUS ─── */
        .badge-status {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 20px; font-size: .72rem; font-weight: 700; letter-spacing: .03em;
        }
        .badge-active { background: var(--green-100); color: var(--green-600); }

        /* ─── EMPTY STATES ─── */
        .empty-state { text-align: center; padding: 44px 20px; }
        .empty-icon {
            width: 60px; height: 60px; background: var(--gray-100); border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 14px; font-size: 22px; color: var(--gray-400);
        }
        .empty-title { font-family: 'Work Sans', sans-serif; font-weight: 700; font-size: .95rem; color: var(--gray-700); margin-bottom: 6px; }
        .empty-sub { font-size: .8rem; color: var(--gray-400); max-width: 260px; margin: 0 auto; line-height: 1.6; }

        /* ─── REFRESH TOAST ─── */
        .refresh-toast {
            position: fixed; top: 90px; right: 24px;
            background: var(--green-500); color: #fff;
            padding: 8px 18px; border-radius: 20px; font-size: .82rem; font-weight: 600;
            opacity: 0; transform: translateY(-8px);
            transition: opacity .3s, transform .3s; z-index: 9999; box-shadow: var(--shadow-md);
        }
        .refresh-toast.show { opacity: 1; transform: translateY(0); }

        /* ─── ANIMATIONS ─── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 1200px) {
            .main-content { margin-left: 0; padding: 16px; }
            .stats-grid   { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .stats-grid        { grid-template-columns: 1fr 1fr; }
            .page-header h2    { font-size: 1.4rem; }
            .tbl-head          { display: none; }
        }

        /* ════════════════════════════════════════════════════
           ENHANCED VISITOR MODAL STYLES
        ════════════════════════════════════════════════════ */

        /* Overlay control */
        #visitorModal { display:none !important; }
        #visitorModal.show { display:block !important; position:fixed; top:0; left:0; width:100%; height:100%; z-index:1050; overflow-y:auto; padding:24px 16px; }
        .modal-backdrop { display:none !important; }
        .modal-backdrop.show { display:block !important; position:fixed; top:0; left:0; width:100%; height:100%; z-index:1040; background:rgba(0,0,0,.65); backdrop-filter:blur(6px); }
        body.modal-open { overflow:hidden; padding-right:0 !important; }

        /* Dialog shell */
        #visitorModal .modal-dialog {
            position:relative; margin:0 auto; max-width:700px; pointer-events:none;
            animation:vmSlideUp .35s cubic-bezier(.16,1,.3,1) both;
        }
        @keyframes vmSlideUp {
            from { opacity:0; transform:translateY(24px) scale(.98); }
            to   { opacity:1; transform:translateY(0) scale(1); }
        }
        #visitorModal .modal-content {
            pointer-events:auto; background:#fff;
            border-radius:20px !important; border:none !important;
            box-shadow:0 32px 80px rgba(0,0,0,.4), 0 0 0 1px rgba(255,255,255,.05) !important;
            overflow:hidden; display:flex; flex-direction:column;
            max-height:92vh;
        }

        /* â”€â”€ HEADER â”€â”€ */
        #visitorModal .modal-header {
            background:linear-gradient(135deg,#1a2744 0%,#1e3a5f 100%) !important;
            padding:24px 28px 22px !important;
            border-bottom:none !important;
            position:relative; overflow:hidden;
            flex-direction:column !important; align-items:stretch !important;
        }
        #visitorModal .modal-header::before {
            content:''; position:absolute; top:-40px; right:-40px;
            width:180px; height:180px; border-radius:50%;
            background:rgba(255,255,255,.04); pointer-events:none;
        }
        #visitorModal .modal-header::after {
            content:''; position:absolute; bottom:-20px; left:30%;
            width:120px; height:120px; border-radius:50%;
            background:rgba(255,255,255,.03); pointer-events:none;
        }
        .header-top { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:0; }
        .visitor-label { font-size:10px; font-weight:500; letter-spacing:.12em; text-transform:uppercase; color:rgba(255,255,255,.45); margin-bottom:6px; }
        .visitor-name-information { font-size:1.4rem; font-weight:800; color:#fff; letter-spacing:-.02em; margin:0 0 6px; font-family:'Work Sans',sans-serif; }
        .visitor-meta { display:flex; gap:14px; margin-top:6px; }
        .meta-item { display:flex; align-items:center; gap:5px; font-size:12.5px; color:rgba(255,255,255,.55); }
        .meta-item i { font-size:10px; opacity:.7; }
        .header-right { display:flex; flex-direction:column; align-items:flex-end; gap:10px; position:relative; z-index:1; }
        .close-btn { width:32px; height:32px; border-radius:8px; background:rgba(255,255,255,.1); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,.7); transition:background .15s; }
        .close-btn:hover { background:rgba(255,255,255,.18); }
        .pass-badge { background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.15); border-radius:8px; padding:5px 10px; font-size:11px; color:rgba(255,255,255,.75); display:flex; align-items:center; gap:5px; font-family:'Space Mono',monospace; }
        .pass-num { background:#3b82f6; color:#fff; font-size:11px; font-weight:600; border-radius:4px; padding:1px 6px; font-family:'Space Mono',monospace; }

        /* â”€â”€ STATUS BAR â”€â”€ */
        .status-bar { background:#f0fdf4; border-bottom:1px solid #dcfce7; padding:10px 28px; display:flex; align-items:center; justify-content:space-between; }
        .status-pill { display:flex; align-items:center; gap:7px; font-size:12.5px; font-weight:500; color:#16a34a; }
        .status-pill.overdue { color:#dc2626; }
        .status-pill.registered { color:#2563eb; }
        .status-dot { width:7px; height:7px; border-radius:50%; background:#22c55e; box-shadow:0 0 0 3px rgba(34,197,94,.2); animation:sdot 2s infinite; }
        .status-pill.overdue .status-dot { background:#ef4444; box-shadow:0 0 0 3px rgba(239,68,68,.2); animation-duration:1.2s; }
        .status-pill.registered .status-dot { background:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.2); }
        @keyframes sdot { 0%,100%{box-shadow:0 0 0 3px rgba(34,197,94,.2)} 50%{box-shadow:0 0 0 5px rgba(34,197,94,.08)} }
        .visit-pass-label { font-size:12px; color:#6b7280; font-family:'Space Mono',monospace; }
        .visit-pass-label span { color:#1a2744; font-weight:600; }

        /* â”€â”€ BODY â”€â”€ */
        #visitorModal .modal-body {
            overflow-y:auto !important; padding:24px 28px !important;
            display:flex !important; flex-direction:column !important; gap:20px !important;
            flex:1 !important; background:#fff !important;
        }
        #visitorModal .modal-body::-webkit-scrollbar { width:4px; }
        #visitorModal .modal-body::-webkit-scrollbar-track { background:transparent; }
        #visitorModal .modal-body::-webkit-scrollbar-thumb { background:#e5e7eb; border-radius:4px; }
        .section-label { font-size:10px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:#9ca3af; margin-bottom:10px; }

        /* info grid */
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
        .info-card { background:#f9fafb; border:1px solid #f3f4f6; border-radius:12px; padding:12px 14px; display:flex; align-items:flex-start; gap:10px; transition:border-color .15s; }
        .info-card:hover { border-color:#e5e7eb; }
        .info-icon { width:30px; height:30px; border-radius:8px; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:13px; }
        .icon-blue   { background:#eff6ff; color:#3b82f6; }
        .icon-green  { background:#f0fdf4; color:#22c55e; }
        .icon-amber  { background:#fffbeb; color:#f59e0b; }
        .icon-red    { background:#fef2f2; color:#ef4444; }
        .icon-slate  { background:#f8fafc; color:#64748b; }
        .info-text { min-width:0; }
        .info-label { font-size:10.5px; color:#9ca3af; font-weight:500; margin-bottom:2px; }
        .info-value { font-size:13px; color:#111827; font-weight:500; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

        /* id card */
        .id-wrapper { display:flex; gap:14px; align-items:center; flex:1; }
        .id-thumb { width:72px; height:48px; border-radius:8px; overflow:hidden; border:1px solid #e5e7eb; flex-shrink:0; background:#f3f4f6; display:flex; align-items:center; justify-content:center; }
        .id-thumb img { width:100%; height:100%; object-fit:cover; }
        .id-view-btn { background:#eff6ff; border:none; color:#3b82f6; font-size:11.5px; font-family:'DM Sans',sans-serif; font-weight:500; padding:5px 10px; border-radius:7px; cursor:pointer; white-space:nowrap; transition:background .15s; }
        .id-view-btn:hover { background:#dbeafe; }
        .divider { height:1px; background:#f3f4f6; }

        /* visit info */
        .visit-card { background:linear-gradient(135deg,#f0f7ff 0%,#f5f3ff 100%); border:1px solid #e0e7ff; border-radius:14px; padding:18px; }
        .visit-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px; }
        .visit-field label { font-size:10.5px; color:#6b7280; font-weight:500; display:block; margin-bottom:4px; text-transform:uppercase; letter-spacing:.07em; }
        .visit-field p { font-size:14px; font-weight:600; color:#1a2744; margin:0; }
        .purpose-tag { display:inline-flex; align-items:center; background:#dbeafe; color:#1d4ed8; font-size:12px; font-weight:500; border-radius:20px; padding:3px 11px; }
        .group-tag { display:inline-flex; align-items:center; gap:5px; background:#f3f4f6; color:#374151; font-size:12px; font-weight:500; border-radius:20px; padding:3px 11px; }

        /* time tracking */
        .time-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:12px; }
        .time-tile { border-radius:12px; padding:14px 12px; text-align:center; border:1px solid transparent; }
        .tile-checkin  { background:#f0fdf4; border-color:#dcfce7; }
        .tile-checkout { background:#fff7ed; border-color:#fed7aa; }
        .tile-duration { background:#eff6ff; border-color:#dbeafe; }
        .tile-icon { width:32px; height:32px; border-radius:50%; margin:0 auto 8px; display:flex; align-items:center; justify-content:center; font-size:13px; color:#fff; }
        .tile-checkin  .tile-icon { background:#22c55e; }
        .tile-checkout .tile-icon { background:#f97316; }
        .tile-duration .tile-icon { background:#3b82f6; }
        .tile-label { font-size:9.5px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; margin-bottom:5px; }
        .tile-checkin  .tile-label { color:#16a34a; }
        .tile-checkout .tile-label { color:#ea580c; }
        .tile-duration .tile-label { color:#2563eb; }
        .tile-value { font-size:13px; font-weight:600; color:#111827; font-family:'Space Mono',monospace; line-height:1.3; }
        .tile-sub { font-size:10px; color:#9ca3af; margin-top:1px; font-family:'Space Mono',monospace; }
        .total-time-bar { background:#1a2744; border-radius:10px; padding:12px 16px; display:flex; align-items:center; justify-content:space-between; }
        .total-label { font-size:11px; color:rgba(255,255,255,.5); text-transform:uppercase; letter-spacing:.08em; }
        .total-value { font-size:18px; font-weight:600; color:#fff; font-family:'Space Mono',monospace; }

        /* notes */
        .notes-box { background:#fafafa; border:1px dashed #e5e7eb; border-radius:12px; padding:14px 16px; font-size:13px; color:#9ca3af; font-style:italic; min-height:56px; display:flex; align-items:center; }

        /* â”€â”€ FOOTER â”€â”€ */
        #visitorModal .modal-footer { padding:16px 28px !important; border-top:1px solid #f3f4f6 !important; display:flex !important; align-items:center !important; justify-content:space-between !important; background:#fff !important; border-radius:0 0 20px 20px !important; }
        .realtime-tag { display:flex; align-items:center; gap:6px; font-size:11.5px; color:#9ca3af; }
        .rt-dot { width:6px; height:6px; border-radius:50%; background:#22c55e; animation:rtp 2s infinite; }
        @keyframes rtp { 0%,100%{opacity:1} 50%{opacity:.4} }
        .footer-actions { display:flex; gap:8px; }
        .btn-vm { padding:9px 18px; border-radius:10px; font-size:13px; font-weight:500; font-family:'DM Sans',sans-serif; cursor:pointer; transition:all .15s; border:none; display:flex; align-items:center; gap:6px; }
        .btn-ghost { background:#f3f4f6; color:#374151; }
        .btn-ghost:hover { background:#e5e7eb; }
        .btn-checkout-main { background:linear-gradient(135deg,#ef4444,#dc2626); color:#fff; box-shadow:0 3px 10px rgba(239,68,68,.3); }
        .btn-checkout-main:hover { transform:translateY(-1px); box-shadow:0 6px 16px rgba(239,68,68,.4); }
        #modalIdPhotoWrap img { width:100%; max-height:160px; object-fit:contain; border-radius:8px; border:1px solid #e5e7eb; background:#f9fafb; cursor:zoom-in; transition:transform .2s; }
        #modalIdPhotoWrap img:hover { transform:scale(1.02); }

        @media (max-width:768px) {
            .info-grid { grid-template-columns:1fr; }
            .visit-row { grid-template-columns:1fr; gap:10px; }
            .time-grid { grid-template-columns:1fr 1fr; }
        }

        /* ─── CHECKOUT CONFIRM MODAL ─── */
        #checkoutModal {
            display: none;
            position: fixed; inset: 0; z-index: 1060;
            align-items: center; justify-content: center;
            background: rgba(0,0,0,.55);
            backdrop-filter: blur(4px);
            animation: fadeIn .18s ease;
        }
        #checkoutModal.show { display: flex; }
        @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
        .co-dialog {
            background: #fff;
            border-radius: 18px;
            width: 100%; max-width: 440px;
            box-shadow: 0 24px 60px rgba(0,0,0,.18);
            overflow: hidden;
            animation: coSlide .22s cubic-bezier(.34,1.56,.64,1);
        }
        @keyframes coSlide { from { opacity:0; transform:scale(.88) translateY(20px); } to { opacity:1; transform:none; } }
        .co-header {
            background: linear-gradient(135deg, #dc2626, #ef4444);
            padding: 26px 28px 22px;
            position: relative; overflow: hidden;
        }
        .co-header::before {
            content:''; position:absolute; top:-40px; right:-40px;
            width:140px; height:140px; border-radius:50%;
            background: rgba(255,255,255,.1);
        }
        .co-header-inner { position:relative; z-index:1; display:flex; align-items:center; gap:14px; }
        .co-icon {
            width:48px; height:48px; border-radius:14px;
            background:rgba(255,255,255,.2);
            display:flex; align-items:center; justify-content:center;
            flex-shrink:0;
        }
        .co-icon i { font-size:1.3rem; color:#fff; }
        .co-header-text h5 { margin:0; color:#fff; font-family:'Work Sans',sans-serif; font-size:1.1rem; font-weight:800; }
        .co-header-text p  { margin:4px 0 0; color:rgba(255,255,255,.8); font-size:.8rem; }
        .co-body { padding:24px 28px; }
        .co-visitor-card {
            background: var(--gray-50);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px 18px;
            display: flex; align-items: center; gap: 14px;
            margin-bottom: 18px;
        }
        .co-visitor-icon {
            width:44px; height:44px; border-radius:50%;
            background: var(--accent-red-lt);
            display:flex; align-items:center; justify-content:center;
            flex-shrink:0;
        }
        .co-visitor-icon i { color: var(--accent-red); font-size:1.1rem; }
        .co-visitor-name  { font-weight:800; font-size:.98rem; color:var(--gray-900); margin-bottom:2px; }
        .co-visitor-meta  { font-size:.78rem; color:var(--gray-500); }
        .co-duration-pill {
            display:inline-flex; align-items:center; gap:5px;
            background: var(--accent-orange-lt);
            color: var(--accent-orange);
            border: 1px solid rgba(245,158,11,.25);
            border-radius: 20px;
            padding: 4px 12px;
            font-size:.78rem; font-weight:700;
            margin-bottom:18px;
        }
        .co-warning {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 10px;
            padding: 12px 14px;
            font-size:.82rem; color:#991b1b;
            display:flex; align-items:flex-start; gap:10px;
            line-height:1.5;
        }
        .co-warning i { flex-shrink:0; margin-top:2px; }
        .co-footer {
            padding: 18px 28px 22px;
            display: flex; gap: 10px;
            border-top: 1px solid var(--border);
            background: var(--gray-50);
        }
        .co-btn-cancel {
            flex:1; padding:11px;
            border:1.5px solid var(--border);
            border-radius:9px; background:#fff;
            font-family:'DM Sans',sans-serif; font-weight:600; font-size:.9rem;
            color:var(--gray-600); cursor:pointer; transition:all .2s;
        }
        .co-btn-cancel:hover { background:var(--gray-100); border-color:var(--gray-300); }
        .co-btn-confirm {
            flex:2; padding:11px;
            background:linear-gradient(135deg,#dc2626,#ef4444);
            border:none; border-radius:9px;
            font-family:'DM Sans',sans-serif; font-weight:700; font-size:.9rem;
            color:#fff; cursor:pointer; transition:all .2s;
            box-shadow:0 4px 14px rgba(239,68,68,.3);
            display:flex; align-items:center; justify-content:center; gap:8px;
        }
        .co-btn-confirm:hover { transform:translateY(-1px); box-shadow:0 6px 18px rgba(239,68,68,.45); }
        .co-btn-confirm:disabled { opacity:.65; cursor:not-allowed; transform:none; }
            .modal-body { grid-template-columns: 1fr !important; }
            .vm-time-trio { grid-template-columns: 1fr 1fr; }
            .vm-grid-2 { grid-template-columns: 1fr; }
    </style>
</head>
<body>
    <?php 
    if ($auth->hasRole(ROLE_GUARD)) {
        include '../includes/guard-sidebar.php';
    } else {
        include '../includes/admin-sidebar.php';
    }
    ?>

    <div class="main-content">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="page-header-inner">
                <div>
                    <h2>
                        <i class="fas fa-users"></i>
                        Visitor Management
                    </h2>
                    <p class="subtitle">Manage visitor check-ins and check-outs in real-time</p>
                </div>
                <div class="live-badge">
                    <div class="live-dot"></div>
                    Live Monitoring
                </div>
            </div>
        </div>

        <!-- ALERTS -->
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <!-- STATS GRID -->
        <div class="stats-grid" id="statsRow">
            <div class="stat-card s-green">
                <div class="stat-icon-wrap"><i class="fas fa-sign-in-alt"></i></div>
                <div class="stat-number" id="totalCheckInToday">0</div>
                <div class="stat-label">Today's Check-ins</div>
            </div>
            <div class="stat-card s-teal">
                <div class="stat-icon-wrap"><i class="fas fa-users"></i></div>
                <div class="stat-number" id="currentVisitors">0</div>
                <div class="stat-label">Current Visitors</div>
            </div>
            <div class="stat-card s-blue">
                <div class="stat-icon-wrap"><i class="fas fa-sign-out-alt"></i></div>
                <div class="stat-number" id="totalCheckOutToday">0</div>
                <div class="stat-label">Today's Check-outs</div>
            </div>
            <div class="stat-card s-orange">
                <div class="stat-icon-wrap"><i class="fas fa-clock"></i></div>
                <div class="stat-number" id="overdueVisitors">0</div>
                <div class="stat-label">Overdue Visitors</div>
            </div>
        </div>

        <!-- TWO-COLUMN LAYOUT -->
        <div class="row g-3">

            <!-- REGISTERED VISITORS -->
            <div class="col-lg-5">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-header-title">
                            <span class="card-header-icon"><i class="fas fa-clipboard-list"></i></span>
                            Registered Visitors
                        </h5>
                        <div class="card-header-actions">
                            <button class="btn-refresh" onclick="refreshRegisteredVisitors()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body-scroll">
                        <div class="search-wrap">
                            <i class="fas fa-search"></i>
                            <input type="text" id="visitorSearch" class="search-input"
                                   placeholder="Search by name, phone, or company…">
                        </div>

                        <div class="tbl-head registered d-none" id="registeredVisitorsHeader">
                            <div>Visitor</div>
                            <div>Phone</div>
                            <div>Address</div>
                            <div>Last Visit</div>
                            <div>Action</div>
                        </div>

                        <div id="registeredVisitorsList">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-spinner fa-spin"></i></div>
                                <div class="empty-title">Loading registered visitors…</div>
                                <div class="empty-sub">Please wait while we fetch the data.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CURRENT VISITORS -->
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-header-title">
                            <span class="card-header-icon"><i class="fas fa-user-check"></i></span>
                            Current Visitors
                        </h5>
                        <div class="card-header-actions">
                            <button class="btn-refresh" onclick="refreshVisitors()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body-scroll">
                        <div class="tbl-head current d-none" id="currentVisitorsHeader">
                            <div>Visitor Name</div>
                            <div>Contact Number</div>
                            <div>Purpose to Visit</div>
                            <div>Group Visit</div>
                            <div>Check In Time</div>
                            <div>Check Out Time</div>
                            <div>Action</div>
                        </div>

                        <div id="currentVisitorsList">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-spinner fa-spin"></i></div>
                                <div class="empty-title">Loading current visitors…</div>
                                <div class="empty-sub">Please wait while we fetch the data.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /row -->
    </div><!-- /main-content -->

    <!-- REFRESH TOAST -->
    <div id="refreshToast" class="refresh-toast">
        <i class="fas fa-sync-alt me-1"></i> Updated
    </div>

    <!-- â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
         VISITOR DETAILS MODAL
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• -->
    <div class="modal fade" id="visitorModal" tabindex="-1" aria-labelledby="visitorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">

                <!-- HEADER -->
                <div class="modal-header">
                    <div class="header-top">
                        <div>
                            <p class="visitor-label">Visitor Record</p>
                            <h2 class="visitor-name-information" id="vmProfileName">&mdash;</h2>
                            <div class="visitor-meta" id="vmProfileSub"></div>
                        </div>
                        <div class="header-right">
                            <button type="button" class="close-btn" onclick="hideModal()" aria-label="Close">
                                <i class="fas fa-times"></i>
                            </button>
                            <div class="pass-badge">
                                Visit Pass <span class="pass-num" id="vmPassPill">&mdash;</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STATUS BAR -->
                <div class="status-bar">
                    <div id="modalStatus">&mdash;</div>
                    <span class="visit-pass-label">Pass #<span id="modalVisitorPass">&mdash;</span></span>
                </div>

                <!-- BODY -->
                <div class="modal-body">

                    <!-- Personal Info -->
                    <div>
                        <p class="section-label">Personal Information</p>
                        <div class="info-grid">
                            <div class="info-card">
                                <div class="info-icon icon-blue"><i class="fas fa-user"></i></div>
                                <div class="info-text">
                                    <p class="info-label">Full Name</p>
                                    <p class="info-value" id="modalVisitorName">&mdash;</p>
                                </div>
                            </div>
                            <div class="info-card">
                                <div class="info-icon icon-green"><i class="fas fa-phone"></i></div>
                                <div class="info-text">
                                    <p class="info-label">Phone Number</p>
                                    <p class="info-value" id="modalPhone">&mdash;</p>
                                </div>
                            </div>
                            <div class="info-card" style="grid-column:1/-1;">
                                <div class="info-icon icon-amber"><i class="fas fa-envelope"></i></div>
                                <div class="info-text">
                                    <p class="info-label">Email Address</p>
                                    <p class="info-value" id="modalEmail">&mdash;</p>
                                </div>
                            </div>
                            <div class="info-card" style="grid-column:1/-1;">
                                <div class="info-icon icon-red"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="info-text">
                                    <p class="info-label">Address</p>
                                    <p class="info-value" style="white-space:normal;overflow:visible;text-overflow:unset;" id="modalAddress">&mdash;</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ID Section -->
                    <div>
                        <p class="section-label">Identification</p>
                        <div class="info-card" style="border-radius:12px;">
                            <div class="info-icon icon-slate"><i class="fas fa-id-card"></i></div>
                            <div class="id-wrapper">
                                <div style="flex:1;">
                                    <p class="info-label">ID Type</p>
                                    <p class="info-value" id="modalIdType">&mdash;</p>
                                </div>
                                <div class="id-thumb" id="modalIdPhotoWrap" style="display:none;">
                                    <a id="modalIdPhotoLink" href="#" target="_blank">
                                        <img id="modalIdPhoto" src="" alt="Valid ID">
                                    </a>
                                </div>
                                <button class="id-view-btn" id="modalIdViewBtn" style="display:none;" onclick="document.getElementById('modalIdPhotoLink').click()">
                                    View ID
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <!-- Visit Info -->
                    <div>
                        <p class="section-label">Visit Information</p>
                        <div class="visit-card">
                            <div class="visit-row">
                                <div class="visit-field">
                                    <label>Department</label>
                                    <p id="modalDepartment">&mdash;</p>
                                </div>
                                <div class="visit-field">
                                    <label>Person to Visit</label>
                                    <p id="modalPersonToVisit">&mdash;</p>
                                </div>
                            </div>
                            <div class="visit-row" style="margin-bottom:0;">
                                <div class="visit-field">
                                    <label>Visit Purpose</label>
                                    <div id="modalPurpose">&mdash;</div>
                                </div>
                                <div class="visit-field">
                                    <label>Group Status</label>
                                    <div id="modalGroupStatus">&mdash;</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Group Members -->
                    <div id="groupMembersRow" style="display:none;">
                        <p class="section-label">Group Members</p>
                        <div class="visit-card">
                            <p style="font-size:13.5px;color:#374151;line-height:1.7;margin:0;" id="modalGroupMembers">&mdash;</p>
                        </div>
                    </div>

                    <!-- Time Tracking -->
                    <div>
                        <p class="section-label">Time Tracking</p>
                        <div class="time-grid">
                            <div class="time-tile tile-checkin">
                                <div class="tile-icon"><i class="fas fa-sign-in-alt"></i></div>
                                <p class="tile-label">Check-In</p>
                                <p class="tile-value" id="modalCheckInTime">&mdash;</p>
                                <p class="tile-sub" id="modalCheckInSub">&mdash;</p>
                            </div>
                            <div class="time-tile tile-checkout">
                                <div class="tile-icon"><i class="fas fa-sign-out-alt"></i></div>
                                <p class="tile-label">Check-Out</p>
                                <p class="tile-value" id="modalCheckOutTime">&mdash;</p>
                                <p class="tile-sub" id="modalCheckOutSub">&mdash;</p>
                            </div>
                            <div class="time-tile tile-duration">
                                <div class="tile-icon"><i class="fas fa-hourglass-half"></i></div>
                                <p class="tile-label">Duration</p>
                                <p class="tile-value" id="modalDuration">&mdash;</p>
                                <p class="tile-sub">On campus</p>
                            </div>
                        </div>
                        <div class="total-time-bar" id="vmDurationHero" style="display:none;">
                            <span class="total-label">Total Time on Campus</span>
                            <span class="total-value" id="vmDurationText">&mdash;</span>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <p class="section-label">Additional Notes</p>
                        <div class="notes-box" id="modalNotes">No additional notes provided.</div>
                    </div>

                </div><!-- /modal-body -->

                <!-- FOOTER -->
                <div class="modal-footer">
                    <div class="realtime-tag">
                        <span class="rt-dot"></span>
                        Updated in real-time
                    </div>
                    <div class="footer-actions">
                        <button type="button" class="btn-vm btn-ghost" onclick="hideModal()">
                            <i class="fas fa-times"></i> Close
                        </button>
                        <button type="button" class="btn-vm btn-checkout-main" id="modalCheckoutBtn" style="display:none;">
                            <i class="fas fa-sign-out-alt"></i> Check Out
                        </button>
                    </div>
                </div>

            </div><!-- /modal-content -->
        </div><!-- /modal-dialog -->
    </div><!-- /visitorModal -->

    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>

    <!-- CHECKOUT CONFIRMATION MODAL -->
    <div id="checkoutModal" role="dialog" aria-modal="true" aria-labelledby="coModalTitle">
        <div class="co-dialog">
            <div class="co-header">
                <div class="co-header-inner">
                    <div class="co-icon"><i class="fas fa-sign-out-alt"></i></div>
                    <div class="co-header-text">
                        <h5 id="coModalTitle">Check Out Visitor</h5>
                        <p>This action will end the current visit</p>
                    </div>
                </div>
            </div>
            <div class="co-body">
                <div class="co-visitor-card">
                    <div class="co-visitor-icon"><i class="fas fa-user"></i></div>
                    <div>
                        <div class="co-visitor-name" id="coVisitorName">&mdash;</div>
                        <div class="co-visitor-meta" id="coVisitorPass">&mdash;</div>
                    </div>
                </div>
                <div class="co-duration-pill" id="coDurationPill" style="display:none;">
                    <i class="fas fa-clock"></i>
                    <span id="coDurationText">—</span>
                </div>
                <div class="co-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Once checked out, this visitor <strong>cannot re-enter today</strong>. Please confirm you want to end this visit.</span>
                </div>
            </div>
            <div class="co-footer">
                <button class="co-btn-cancel" id="coCancelBtn"><i class="fas fa-times me-1"></i> Cancel</button>
                <button class="co-btn-confirm" id="coConfirmBtn">
                    <i class="fas fa-sign-out-alt"></i> Confirm Check Out
                </button>
            </div>
        </div>
    </div>
    <script>
        if (typeof jQuery === 'undefined') {
            console.error('jQuery failed to load. Loading fallback...');
            document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
        }
        
        let refreshInterval;
        const csrfToken = '<?php echo $csrf_token; ?>';

        function waitForJQuery(callback) {
            if (typeof jQuery !== 'undefined') {
                callback();
            } else {
                setTimeout(function() { waitForJQuery(callback); }, 100);
            }
        }

        waitForJQuery(function() {
            $(document).ready(function () {
                $('#visitorModal').hide().removeClass('show');
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open');
                
                refreshVisitors();
                refreshRegisteredVisitors();
                startAutoRefresh();

                $('#visitorSearch').on('keyup', function () {
                    const term = $(this).val().toLowerCase();
                    $('.registered-visitor-item').each(function () {
                        $(this).toggle($(this).text().toLowerCase().includes(term));
                    });
                });
            });
        });

        function startAutoRefresh() {
            refreshInterval = setInterval(function () {
                refreshVisitors(true);
                refreshRegisteredVisitors();
            }, 30000);
        }

        /* ── REGISTERED VISITORS ── */
        function refreshRegisteredVisitors() {
            $('#registeredVisitorsHeader').addClass('d-none');
            $('#registeredVisitorsList').html(loadingHTML('registered visitors'));

            $.ajax({
                url: 'guard-visitor-management.php?action=get_registered_visitors',
                method: 'GET', dataType: 'json',
                success: function (data) {
                    data.success
                        ? displayRegisteredVisitors(data.visitors)
                        : $('#registeredVisitorsList').html(errorHTML(data.message));
                },
                error: function () {
                    $('#registeredVisitorsList').html(errorHTML('Failed to load registered visitors. Please refresh.'));
                }
            });
        }

        function displayRegisteredVisitors(visitors) {
            if (!visitors.length) {
                $('#registeredVisitorsHeader').addClass('d-none');
                $('#registeredVisitorsList').html(`
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-user-friends"></i></div>
                        <div class="empty-title">No Registered Visitors</div>
                        <div class="empty-sub">No visitors have registered in the system yet.</div>
                    </div>`);
                return;
            }

            $('#registeredVisitorsHeader').removeClass('d-none');

            let html = '';
            visitors.forEach(function (v) {
                const lastVisit = v.last_visit
                    ? new Date(v.last_visit).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
                    : 'Never';

                const isCheckedIn     = v.is_currently_checked_in == 1;
                const checkedOutToday = v.checked_out_today == 1;

                const actionButtons = isCheckedIn
                    ? `<div style="display:flex;flex-direction:column;gap:6px;">
                           <span class="badge-status badge-active" style="justify-content:center;">
                               <i class="fas fa-circle"></i> Checked In
                           </span>
                           <button class="btn-view-outline" onclick="viewRegisteredVisitorDetails(${v.id})">
                               <i class="fas fa-eye"></i> View
                           </button>
                       </div>`
                    : checkedOutToday
                    ? `<div style="display:flex;flex-direction:column;gap:6px;">
                           <span class="badge-status" style="justify-content:center;background:rgba(239,68,68,.10);color:#dc2626;border:1px solid rgba(239,68,68,.2);">
                               <i class="fas fa-sign-out-alt"></i> Checked Out
                           </span>
                           <button class="btn-view-outline" onclick="viewRegisteredVisitorDetails(${v.id})">
                               <i class="fas fa-eye"></i> View
                           </button>
                       </div>`
                    : `<div style="display:flex;flex-direction:column;gap:6px;">
                           <button class="btn-checkin"
                               onclick="checkInExistingVisitor(${v.id}, '${esc(v.first_name)} ${esc(v.last_name)}', event)">
                               <i class="fas fa-sign-in-alt"></i> Check In
                           </button>
                           <button class="btn-view-outline" onclick="viewRegisteredVisitorDetails(${v.id})">
                               <i class="fas fa-eye"></i> View
                           </button>
                       </div>`;

                html += `
                <div class="registered-visitor-item"
                     style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 90px;gap:12px;
                            align-items:center;padding:12px 14px;background:#fff;
                            border:1px solid var(--border);border-radius:var(--radius-sm);
                            margin-bottom:8px;transition:transform .2s,box-shadow .2s;">
                    <div>
                        <div class="rv-name">${esc(v.first_name)} ${esc(v.last_name)}</div>
                        ${v.email ? `<div class="rv-meta"><i class="fas fa-envelope"></i> ${esc(v.email)}</div>` : ''}
                    </div>
                    <div style="font-size:.82rem;color:var(--gray-700);">${esc(v.phone) || '\u2014'}</div>
                    <div style="font-size:.82rem;color:var(--gray-700);">${esc(v.address) || '\u2014'}</div>
                    <div style="font-size:.78rem;color:var(--gray-500);">${lastVisit}</div>
                    <div>${actionButtons}</div>
                </div>`;
            });

            $('#registeredVisitorsList').html(html);
        }

        function checkInExistingVisitor(visitorId, visitorName, event) {
            if (!confirm(`Check in ${visitorName}?`)) return;

            const btn = $(event.target).closest('button');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Checking in…');

            $.ajax({
                url: `guard-visitor-management.php?action=checkin_existing&visitor_id=${visitorId}&csrf_token=${csrfToken}`,
                method: 'GET', dataType: 'json',
                success: function (res) {
                    if (res.success) {
                        showAlert('success', `${res.message} &nbsp;— Pass: <strong>${res.visitor_pass}</strong>`);
                        refreshRegisteredVisitors();
                        refreshVisitors(true);
                    } else {
                        showAlert('danger', res.message);
                        // Refresh so the button reflects the real server state
                        // (e.g. visitor already checked out — replace Check In with Checked Out badge)
                        refreshRegisteredVisitors();
                    }
                },
                error: function () {
                    showAlert('danger', 'Failed to check in visitor. Please try again.');
                    refreshRegisteredVisitors();
                }
            });
        }

        /* ── CURRENT VISITORS ── */
        function refreshVisitors(silent = false) {
            if (!silent) {
                $('#currentVisitorsHeader').addClass('d-none');
                $('#currentVisitorsList').html(loadingHTML('current visitors'));
            }

            $.ajax({
                url: 'ajax/visitor-data.php',
                method: 'GET', dataType: 'json',
                success: function (data) {
                    if (data.success === false || data.error) {
                        $('#currentVisitorsList').html(errorHTML('Error: ' + (data.error || data.message || 'Unknown error')));
                        return;
                    }
                    updateStats(data.stats);
                    currentVisitorData = data.visitors;
                    displayVisitors(data.visitors);
                    if (silent) showRefreshToast();
                },
                error: function (xhr, status, error) {
                    $('#currentVisitorsList').html(errorHTML('Failed to load visitor data. Please refresh. Error: ' + error));
                }
            });
        }

        function updateStats(stats) {
            $('#totalCheckInToday').text(stats.todayCheckIns);
            $('#currentVisitors').text(stats.currentVisitors);
            $('#totalCheckOutToday').text(stats.todayCheckOuts);
            $('#overdueVisitors').text(stats.overdueVisitors);
        }

        function displayVisitors(visitors) {
            if (!visitors.length) {
                $('#currentVisitorsHeader').addClass('d-none');
                $('#currentVisitorsList').html(`
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-door-open"></i></div>
                        <div class="empty-title">No Active Visitors On Campus</div>
                        <div class="empty-sub">The campus is currently clear. Checked-in visitors will appear here in real-time.</div>
                    </div>`);
                return;
            }

            $('#currentVisitorsHeader').removeClass('d-none');

            let html = '';
            visitors.forEach(function (v) {
                const groupStatus = v.is_group_visit 
                    ? `<span class="group-badge group-yes"><i class="fas fa-users"></i> Yes (${v.group_size})</span>`
                    : `<span class="group-badge group-no"><i class="fas fa-user"></i> No</span>`;

                const checkInTime  = formatFullDateTime(v.check_in_time);
                const checkOutTime = v.check_out_time ? formatFullDateTime(v.check_out_time) : '-';

                html += `
                <div class="visitor-row" id="visitor-${v.id}">
                    <div class="visitor-cell">
                        <div class="visitor-name">${esc(v.first_name)} ${esc(v.last_name)}</div>
                        <div class="visitor-phone">Pass: ${esc(v.visit_pass)}</div>
                    </div>
                    <div class="visitor-cell">
                        <div style="font-weight:600;">${esc(v.phone)}</div>
                        ${v.email ? `<div class="visitor-phone">${esc(v.email)}</div>` : ''}
                    </div>
                    <div class="visitor-cell">
                        <div class="purpose-text" title="${esc(v.purpose || 'Not specified')}">
                            ${esc(v.purpose || 'Not specified')}
                        </div>
                    </div>
                    <div class="visitor-cell">${groupStatus}</div>
                    <div class="visitor-cell"><div class="datetime-text">${checkInTime}</div></div>
                    <div class="visitor-cell"><div class="datetime-text">${checkOutTime}</div></div>
                    <div class="visitor-cell">
                        <div class="action-buttons">
                            <button class="btn-view" onclick="viewVisitorDetails(${v.id})" title="View Details">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="btn-checkout" onclick="checkoutVisitor(${v.id}, '${esc(v.first_name)} ${esc(v.last_name)}', ${v.duration_minutes || 0}, '${esc(v.visit_pass || '')}')" title="Check Out">
                                <i class="fas fa-sign-out-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>`;
            });

            $('#currentVisitorsList').html(html);
        }

        function checkoutVisitor(visitId, visitorName, durationMins, visitPass) {
            // Populate confirmation modal
            document.getElementById('coVisitorName').textContent = visitorName;
            document.getElementById('coVisitorPass').textContent = visitPass ? 'Pass: ' + visitPass : 'Current visit';

            const durPill = document.getElementById('coDurationPill');
            if (durationMins && durationMins > 0) {
                document.getElementById('coDurationText').textContent = 'Visit duration so far: ' + formatDurationLong(durationMins);
                durPill.style.display = 'inline-flex';
            } else {
                durPill.style.display = 'none';
            }

            // Show modal
            const modal = document.getElementById('checkoutModal');
            modal.classList.add('show');

            // Confirm button
            const confirmBtn = document.getElementById('coConfirmBtn');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-sign-out-alt"></i> Confirm Check Out';
            confirmBtn.onclick = function () {
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking out...';

                $.ajax({
                    url: `guard-visitor-management.php?action=checkout&visit_id=${visitId}&csrf_token=${csrfToken}`,
                    method: 'GET', dataType: 'json',
                    success: function (res) {
                        modal.classList.remove('show');
                        if (res.success) {
                            // Immediately refresh registered visitors so the
                            // "Check In" button is replaced before the user can click it
                            refreshRegisteredVisitors();
                            refreshVisitors(true);
                            $(`#visitor-${visitId}`).fadeOut(300, function () {
                                $(this).remove();
                            });
                            showAlert('success', res.message);
                            hideModal();
                        } else {
                            showAlert('danger', res.message);
                        }
                    },
                    error: function () {
                        modal.classList.remove('show');
                        showAlert('danger', 'Failed to check out visitor. Please try again.');
                    }
                });
            };

            // Cancel button + backdrop click
            document.getElementById('coCancelBtn').onclick = function () {
                modal.classList.remove('show');
            };
            modal.onclick = function (e) {
                if (e.target === modal) modal.classList.remove('show');
            };
        }

        /* ── HELPERS ── */
        function formatFullDateTime(dt) {
            return new Date(dt).toLocaleString('en-US', {
                month: 'short', day: 'numeric', year: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            });
        }

        function formatDuration(mins) {
            if (!mins || mins <= 0) return '—';
            if (mins < 60) return `${mins}m`;
            return `${Math.floor(mins / 60)}h ${mins % 60}m`;
        }

        function formatDurationLong(mins) {
            if (!mins || mins <= 0) return '—';
            const h = Math.floor(mins / 60), m = mins % 60;
            if (h === 0) return `${m} min`;
            if (m === 0) return `${h} hour${h > 1 ? 's' : ''}`;
            return `${h} hour${h > 1 ? 's' : ''} ${m} min`;
        }

        function showRefreshToast() {
            $('#refreshToast').addClass('show');
            setTimeout(() => $('#refreshToast').removeClass('show'), 2200);
        }

        /* ── VIEW VISITOR DETAILS ── */
        let currentVisitorData = null;

        function viewRegisteredVisitorDetails(visitorId) {
            $.ajax({
                url: `guard-visitor-management.php?action=get_visitor_details&visitor_id=${visitorId}`,
                method: 'GET', dataType: 'json',
                success: function(response) {
                    if (response.success && response.visitor) {
                        populateModalWithVisitorData(response.visitor, true);
                        showModal();
                    } else {
                        showAlert('danger', 'Failed to load visitor details.');
                    }
                },
                error: function() {
                    showAlert('danger', 'Error loading visitor details. Please try again.');
                }
            });
        }

        function viewVisitorDetails(visitId) {
            if (!currentVisitorData) {
                showAlert('danger', 'Visitor data not available. Please refresh and try again.');
                return;
            }
            const visitor = currentVisitorData.find(v => v.id == visitId);
            if (!visitor) {
                showAlert('danger', 'Visitor not found. Please refresh and try again.');
                return;
            }
            populateModalWithVisitorData(visitor, false);
            showModal();
        }

        function populateModalWithVisitorData(visitor, isRegisteredVisitor = false) {
            const fullName = `${visitor.first_name} ${visitor.last_name}`;

            // ── header ──
            $('#vmProfileName').text(fullName);

            // meta items (phone + address)
            const metaParts = [];
            if (visitor.phone)   metaParts.push(`<span class="meta-item"><i class="fas fa-phone"></i> ${esc(visitor.phone)}</span>`);
            if (visitor.address) metaParts.push(`<span class="meta-item"><i class="fas fa-map-marker-alt"></i> ${esc(visitor.address)}</span>`);
            $('#vmProfileSub').html(metaParts.join('') || '<span class="meta-item">No contact info</span>');

            $('#vmPassPill').text(visitor.visit_pass || '—');

            // ── personal fields ──
            $('#modalVisitorName').text(fullName);
            $('#modalPhone').text(visitor.phone || 'Not provided');
            $('#modalEmail').text(visitor.email || 'Not provided');
            $('#modalAddress').text(visitor.address || 'Not provided');
            $('#modalIdType').text(visitor.id_type || 'Not provided');

            // ID photo
            if (visitor.id_photo_path) {
                const photoSrc = '../' + visitor.id_photo_path;
                $('#modalIdPhoto').attr('src', photoSrc);
                $('#modalIdPhotoLink').attr('href', photoSrc);
                $('#modalIdPhotoWrap').show();
                $('#modalIdViewBtn').show();
            } else {
                $('#modalIdPhotoWrap').hide();
                $('#modalIdViewBtn').hide();
            }

            // ── visit info ──
            $('#modalDepartment').text(visitor.department || 'Not specified');
            $('#modalPersonToVisit').text(visitor.person_to_visit || 'Not specified');
            $('#modalPurpose').html(`<span class="purpose-tag">${esc(visitor.purpose || 'Not specified')}</span>`);

            // group
            if (visitor.is_group_visit == '1' || visitor.is_group_visit === true || visitor.is_group_visit === 1) {
                $('#modalGroupStatus').html(`<span class="group-tag"><i class="fas fa-users"></i> Group — ${visitor.group_size || '?'} people</span>`);
                $('#modalGroupMembers').text(visitor.group_members || 'Members not specified');
                $('#groupMembersRow').show();
            } else {
                $('#modalGroupStatus').html(`<span class="group-tag" style="background:#f1f5f9;color:#64748b;"><i class="fas fa-user"></i> Individual</span>`);
                $('#groupMembersRow').hide();
            }

            $('#modalNotes').text(visitor.additional_notes || 'No additional notes provided');

            // helper: split formatted datetime into date / time parts
            function splitDT(raw) {
                if (!raw) return { date: '—', time: '' };
                const fmt = formatFullDateTime(raw); // e.g. "Jun 14, 2025, 10:30 AM"
                const commaIdx = fmt.lastIndexOf(', ');
                if (commaIdx === -1) return { date: fmt, time: '' };
                return { date: fmt.substring(0, commaIdx), time: fmt.substring(commaIdx + 2) };
            }

            // ── status + time ──
            if (isRegisteredVisitor) {
                $('#vmPassPill').text(visitor.visit_pass || '—');
                $('#modalVisitorPass').text(visitor.visit_pass || '—');
                $('#modalCheckoutBtn').hide();

                if (!visitor.visit_pass) {
                    $('#modalStatus').html(`<div class="status-pill"><span class="status-dot"></span> Registered — No visits yet</div>`);
                    $('#modalCheckInTime').text('No visits yet');
                    $('#modalCheckInSub').text('');
                    $('#modalCheckOutTime').text('—');
                    $('#modalCheckOutSub').text('');
                    $('#modalDuration').text('—');
                    $('#vmDurationHero').hide();
                } else {
                    const statusLabel = visitor.visit_status === 'checked_out' ? 'Last Visit: Checked Out'
                                      : visitor.visit_status === 'checked_in'  ? 'Currently Checked In'
                                      : visitor.visit_status === 'no_show'     ? 'Last Visit: No Show'
                                      : 'Registered';
                    const statusClass = visitor.visit_status === 'checked_in' ? '' : ' registered';
                    $('#modalStatus').html(`<div class="status-pill${statusClass}"><span class="status-dot"></span> ${statusLabel}</div>`);

                    const ci = splitDT(visitor.check_in_time);
                    $('#modalCheckInTime').text(ci.date);
                    $('#modalCheckInSub').text(ci.time);

                    const co = splitDT(visitor.check_out_time);
                    $('#modalCheckOutTime').text(co.date);
                    $('#modalCheckOutSub').text(co.time);

                    $('#modalDuration').text(visitor.duration_minutes > 0 ? formatDuration(visitor.duration_minutes) : '—');
                    if (visitor.duration_minutes > 0) {
                        $('#vmDurationText').text(formatDurationLong(visitor.duration_minutes));
                        $('#vmDurationHero').show();
                    } else {
                        $('#vmDurationHero').hide();
                    }
                }
            } else {
                // active visit
                $('#modalVisitorPass').text(visitor.visit_pass || '—');
                $('#vmPassPill').text(visitor.visit_pass || '—');

                if (visitor.is_overdue) {
                    $('#modalStatus').html(`<div class="status-pill overdue"><span class="status-dot"></span> Overdue</div>`);
                } else {
                    $('#modalStatus').html(`<div class="status-pill"><span class="status-dot"></span> Active Visit</div>`);
                }

                const ci = splitDT(visitor.check_in_time);
                $('#modalCheckInTime').text(ci.date);
                $('#modalCheckInSub').text(ci.time);

                if (visitor.check_out_time) {
                    const co = splitDT(visitor.check_out_time);
                    $('#modalCheckOutTime').text(co.date);
                    $('#modalCheckOutSub').text(co.time);
                } else {
                    $('#modalCheckOutTime').text('Still on campus');
                    $('#modalCheckOutSub').text('');
                }

                $('#modalDuration').text(formatDuration(visitor.duration_minutes));

                if (visitor.duration_minutes > 0) {
                    $('#vmDurationText').text(formatDurationLong(visitor.duration_minutes));
                    $('#vmDurationHero').show();
                } else {
                    $('#vmDurationHero').hide();
                }

                $('#modalCheckoutBtn').show().off('click').on('click', function() {
                    checkoutVisitor(visitor.id, fullName, visitor.duration_minutes, visitor.visit_pass);
                });
            }
        }

        function showModal() {
            $('#visitorModal').hide().removeClass('show');
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');

            const modal = document.getElementById('visitorModal');
            if (modal) {
                modal.style.display = 'block';
                modal.classList.add('show', 'fade');
                modal.setAttribute('aria-modal', 'true');
                modal.setAttribute('role', 'dialog');
                modal.removeAttribute('aria-hidden');

                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                backdrop.style.zIndex = '1040';
                document.body.appendChild(backdrop);
                document.body.classList.add('modal-open');

                const dialog = modal.querySelector('.modal-dialog');
                if (dialog) {
                    dialog.style.margin = '1.75rem auto';
                }

                backdrop.onclick = function() { hideModal(); };
            } else {
                showAlert('danger', 'Error: Modal not found. Please refresh the page.');
            }
        }

        function hideModal() {
            const modal = document.getElementById('visitorModal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('show', 'fade');
                modal.setAttribute('aria-hidden', 'true');
                modal.removeAttribute('aria-modal');
                modal.removeAttribute('role');
            }
            document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
            document.body.classList.remove('modal-open');
            $('#visitorModal').hide().removeClass('show');
        }

        function showAlert(type, message) {
            const icon = type === 'success' ? 'check-circle' : 'exclamation-triangle';
            const html = `
                <div class="alert alert-${type} alert-dismissible fade show">
                    <i class="fas fa-${icon}"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
            $('.main-content').prepend(html);
            setTimeout(() => $('.alert.fade').remove(), 5000);
        }

        function loadingHTML(label) {
            return `
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-spinner fa-spin"></i></div>
                    <div class="empty-title">Loading ${label}…</div>
                    <div class="empty-sub">Please wait while we fetch the data.</div>
                </div>`;
        }

        function errorHTML(msg) {
            return `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ${msg}</div>`;
        }

        function esc(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
        }

        $(document).ready(function() {
            $('#visitorModal').hide().removeClass('show');
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');

            $(document).on('click', '[data-bs-dismiss="modal"], .btn-close', function(e) {
                e.preventDefault();
                hideModal();
            });

            $(document).on('click', '#visitorModal', function(e) {
                if (e.target === this) hideModal();
            });

            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && $('#visitorModal').hasClass('show')) hideModal();
            });
        });
    </script>
    
    <!-- Chat Widget -->
    <?php include '../includes/chat-widget.php'; ?>
</body>
</html>