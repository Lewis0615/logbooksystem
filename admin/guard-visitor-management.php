<?php
/**
 * Guard Visitor Management Interface
 * St. Dominic Savio College - Visitor Management System
 */

require_once '../config/config.php';
require_once '../config/auth.php';

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
                    // Block re-entry after check-out on the same day
                    $checked_out_today = $db->fetch(
                        "SELECT id FROM visits WHERE visitor_id = ? AND status = 'checked_out' AND DATE(check_in_time) = CURDATE()",
                        [$visitor_id]
                    );
                    if ($checked_out_today) {
                        $error_message = 'This visitor has already checked out today and cannot check in again.';
                    } else
                    {
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
                    } // end same-day re-entry check
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

        // Block re-entry after check-out on the same day
        $checked_out_today = $db->fetch(
            "SELECT id FROM visits WHERE visitor_id = ? AND status = 'checked_out' AND DATE(check_in_time) = CURDATE()",
            [$visitor_id]
        );
        if ($checked_out_today) {
            echo json_encode(['success' => false, 'message' => 'This visitor has already checked out today and cannot check in again.']);
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
    <title>Visitor Management - <?php echo APP_NAME; ?></title>
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
            font-family: 'Syne', sans-serif;
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
            font-family: 'Syne', sans-serif;
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
            font-family: 'Syne', sans-serif;
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
            color: var(--gray-900);
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
        .empty-title { font-family: 'Syne', sans-serif; font-weight: 700; font-size: .95rem; color: var(--gray-700); margin-bottom: 6px; }
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
        #visitorModal { display: none !important; }
        #visitorModal.show {
            display: block !important; position: fixed; top: 0; left: 0;
            width: 100%; height: 100%; z-index: 1050;
            overflow-y: auto; padding: 32px 16px;
        }
        .modal-backdrop { display: none !important; }
        .modal-backdrop.show {
            display: block !important; position: fixed; top: 0; left: 0;
            width: 100%; height: 100%; z-index: 1040;
            background: rgba(4, 12, 7, 0.80);
            backdrop-filter: blur(6px);
        }
        body.modal-open { overflow: hidden; padding-right: 0 !important; }

        /* Dialog shell */
        .modal-dialog {
            position: relative; margin: 0 auto; max-width: 900px;
            pointer-events: none;
            animation: vmSlideUp .4s cubic-bezier(.22,.68,0,1.15) both;
        }
        @keyframes vmSlideUp {
            from { opacity:0; transform: translateY(28px) scale(.96); }
            to   { opacity:1; transform: translateY(0) scale(1); }
        }

        .modal-content {
            pointer-events: auto;
            background: #fff !important;
            border-radius: 20px !important;
            box-shadow: 0 32px 100px rgba(0,0,0,.5), 0 8px 24px rgba(0,0,0,.25) !important;
            border: none !important;
            overflow: hidden;
            display: flex; flex-direction: column;
        }

        /* ── HERO HEADER ── */
        .modal-header {
            padding: 0 !important;
            border-bottom: none !important;
            background: linear-gradient(135deg, #071a10 0%, #163d27 45%, #256642 100%) !important;
            flex-direction: column !important;
            align-items: stretch !important;
            position: relative;
            overflow: hidden;
        }

        /* animated mesh blobs */
        .modal-header::before {
            content: '';
            position: absolute; top: -80px; right: -80px;
            width: 300px; height: 300px; border-radius: 50%;
            background: radial-gradient(circle, rgba(52,196,125,.16) 0%, transparent 70%);
            animation: blobFloat 8s ease-in-out infinite;
            pointer-events: none;
        }
        .modal-header::after {
            content: '';
            position: absolute; bottom: -100px; left: -60px;
            width: 260px; height: 260px; border-radius: 50%;
            background: radial-gradient(circle, rgba(58,153,98,.1) 0%, transparent 70%);
            animation: blobFloat 11s ease-in-out infinite reverse;
            pointer-events: none;
        }
        @keyframes blobFloat {
            0%,100% { transform: translate(0,0) scale(1); }
            33%      { transform: translate(15px,-10px) scale(1.05); }
            66%      { transform: translate(-10px,8px) scale(.96); }
        }

        /* top bar */
        .vm-topbar {
            display: flex; align-items: center; justify-content: space-between;
            padding: 20px 24px 12px; position: relative; z-index: 2;
        }
        .vm-topbar-left { display: flex; align-items: center; gap: 12px; }
        .vm-topbar-badge {
            display: inline-flex; align-items: center; gap: 7px;
            background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18);
            border-radius: 8px; padding: 7px 14px;
            font-size: .72rem; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: rgba(255,255,255,.8);
        }
        .vm-topbar-badge i { color: #86dba4; font-size: .65rem; }
        .vm-topbar-title {
            font-family: 'Syne', sans-serif; font-size: 1.05rem; font-weight: 800;
            color: #fff; letter-spacing: -.01em;
        }
        .vm-close-btn {
            width: 32px; height: 32px;
            background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15);
            border-radius: 8px; color: rgba(255,255,255,.7); cursor: pointer;
            display: flex; align-items: center; justify-content: center; font-size: 13px;
            transition: all .2s; flex-shrink: 0; z-index: 2;
        }
        .vm-close-btn:hover { background: rgba(255,255,255,.2); color: #fff; transform: rotate(90deg); }

        /* profile strip */
        .vm-profile-strip {
            margin: 0 24px 0;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.12);
            border-bottom: none; border-radius: 14px 14px 0 0;
            padding: 18px 22px;
            display: flex; align-items: center; gap: 16px;
            position: relative; z-index: 2;
        }
        .vm-avatar {
            width: 58px; height: 58px; border-radius: 16px; flex-shrink: 0;
            background: linear-gradient(135deg, #3a9962, #52c47d);
            display: flex; align-items: center; justify-content: center;
            font-family: 'Syne', sans-serif; font-size: 1.3rem; font-weight: 800; color: #fff;
            border: 2px solid rgba(255,255,255,.25);
            box-shadow: 0 6px 20px rgba(0,0,0,.3), inset 0 1px 0 rgba(255,255,255,.2);
            letter-spacing: -.02em;
        }
        .vm-profile-info { flex: 1; min-width: 0; }
        .vm-profile-name {
            font-family: 'Syne', sans-serif; font-size: 1.1rem; font-weight: 800;
            color: #fff; letter-spacing: -.01em; margin-bottom: 4px;
        }
        .vm-profile-meta { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .vm-profile-chip {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: .73rem; color: rgba(255,255,255,.6); font-weight: 500;
        }
        .vm-profile-chip i { font-size: .65rem; opacity: .8; }
        .vm-pass-pill {
            margin-left: auto; flex-shrink: 0;
            background: rgba(255,255,255,.12); border: 1.5px solid rgba(255,255,255,.22);
            border-radius: 20px; padding: 6px 16px;
            font-family: 'DM Mono', monospace; font-size: .78rem; font-weight: 500;
            color: #86dba4; letter-spacing: .06em; white-space: nowrap;
        }

        /* status strip */
        .vm-status-strip {
            margin: 0 24px; padding: 10px 22px;
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.1); border-top: none;
            border-radius: 0 0 10px 10px;
            display: flex; align-items: center; gap: 16px;
            position: relative; z-index: 2; margin-bottom: 0;
        }
        .vm-status-label {
            font-size: .65rem; font-weight: 700; letter-spacing: .1em;
            text-transform: uppercase; color: rgba(255,255,255,.4);
        }
        .vm-status-pill {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 12px; border-radius: 20px; font-size: .75rem; font-weight: 700;
        }
        .vm-status-pill.spl-active  { background: rgba(52,196,125,.2); color: #86dba4; border: 1px solid rgba(52,196,125,.3); }
        .vm-status-pill.spl-overdue { background: rgba(239,68,68,.2); color: #fca5a5; border: 1px solid rgba(239,68,68,.3); }
        .vm-status-pill.spl-registered { background: rgba(59,130,246,.2); color: #93c5fd; border: 1px solid rgba(59,130,246,.3); }
        .vm-status-pulse { width: 7px; height: 7px; border-radius: 50%; }
        .spl-active  .vm-status-pulse { background: #4ade80; animation: spulse 2s infinite; }
        .spl-overdue .vm-status-pulse { background: #ef4444; animation: spulse 1.2s infinite; }
        .spl-registered .vm-status-pulse { background: #60a5fa; animation: spulse 2s infinite; }
        @keyframes spulse {
            0%,100% { opacity:1; } 50% { opacity:.5; }
        }
        .vm-pass-display {
            margin-left: auto; font-family: 'DM Mono', monospace;
            font-size: .78rem; color: rgba(255,255,255,.4); font-weight: 500;
        }
        .vm-pass-display span { color: rgba(255,255,255,.75); font-weight: 600; margin-left: 5px; }

        /* ── MODAL BODY ── */
        .modal-body {
            padding: 22px 24px !important;
            background: var(--gray-50) !important;
            display: grid !important;
            grid-template-columns: 1fr 1.55fr !important;
            gap: 18px !important;
        }

        /* personal info fields */
        .vm-fields-col { display: flex; flex-direction: column; gap: 8px; }
        .vm-section-label {
            font-size: .63rem; font-weight: 700; letter-spacing: .1em;
            text-transform: uppercase; color: var(--gray-400);
            padding-bottom: 6px; margin-bottom: 2px;
            border-bottom: 1px solid var(--border-lt);
        }
        .vm-field {
            background: #fff; border: 1px solid var(--border);
            border-radius: 10px; padding: 11px 14px;
            display: flex; align-items: flex-start; gap: 11px;
            transition: border-color .2s, box-shadow .2s, transform .15s;
        }
        .vm-field:hover {
            border-color: var(--green-300);
            box-shadow: 0 2px 10px rgba(58,153,98,.1);
            transform: translateX(2px);
        }
        .vm-field-ico {
            width: 32px; height: 32px; border-radius: 9px; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 12px;
        }
        .ico-g { background: var(--green-100);        color: var(--green-600); }
        .ico-b { background: var(--accent-blue-lt);   color: var(--accent-blue); }
        .ico-t { background: var(--accent-teal-lt);   color: var(--accent-teal); }
        .ico-o { background: var(--accent-orange-lt); color: var(--accent-orange); }
        .ico-r { background: var(--accent-red-lt);    color: var(--accent-red); }
        .ico-x { background: var(--gray-100);         color: var(--gray-600); }

        .vm-field-lbl {
            font-size: .62rem; font-weight: 700; letter-spacing: .07em;
            text-transform: uppercase; color: var(--gray-400); margin-bottom: 3px;
        }
        .vm-field-val { font-size: .88rem; font-weight: 600; color: var(--gray-800); line-height: 1.4; }
        .vm-field-val.vm-muted { color: var(--gray-400); font-style: italic; font-weight: 400; }

        /* right: visit info */
        .vm-info-col { display: flex; flex-direction: column; gap: 14px; }

        /* section cards */
        .vm-card {
            background: #fff; border: 1px solid var(--border);
            border-radius: 12px; overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,.05);
            transition: box-shadow .2s;
        }
        .vm-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.09); }
        .vm-card-head {
            padding: 13px 18px; display: flex; align-items: center; gap: 11px;
            position: relative; overflow: hidden;
        }
        .vm-card-head::after {
            content: ''; position: absolute; right: -14px; top: -14px;
            width: 70px; height: 70px; border-radius: 50%;
            background: rgba(255,255,255,.1); pointer-events: none;
        }
        .vm-card-head.ch-blue   { background: linear-gradient(135deg, #2563eb, #3b82f6); }
        .vm-card-head.ch-teal   { background: linear-gradient(135deg, #0f766e, #0d9488); }
        .vm-card-head.ch-orange { background: linear-gradient(135deg, #d97706, #f59e0b); }
        .vm-card-head.ch-green  { background: linear-gradient(135deg, #1e5235, #2d7a4f); }
        .vm-card-head-ico {
            width: 34px; height: 34px; border-radius: 9px;
            background: rgba(255,255,255,.2); display: flex; align-items: center;
            justify-content: center; font-size: 14px; color: #fff; flex-shrink: 0;
        }
        .vm-card-head h6 {
            font-family: 'Syne', sans-serif; font-size: .9rem; font-weight: 700;
            color: #fff; margin: 0 0 2px;
        }
        .vm-card-head p { font-size: .72rem; color: rgba(255,255,255,.7); margin: 0; }

        .vm-card-body { padding: 16px 18px; }

        /* inset boxes */
        .vm-inset {
            background: var(--gray-50); border: 1px solid var(--border-lt);
            border-radius: 8px; padding: 11px 14px; transition: background .2s;
        }
        .vm-inset:hover { background: #f0f7f4; }
        .vm-inset-lbl {
            font-size: .62rem; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: var(--gray-400);
            display: flex; align-items: center; gap: 5px; margin-bottom: 5px;
        }
        .vm-inset-val { font-size: .88rem; font-weight: 600; color: var(--gray-800); line-height: 1.5; }
        .vm-inset-val.vm-muted { color: var(--gray-400); font-style: italic; font-weight: 400; }

        .vm-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

        /* Time trio */
        .vm-time-trio { display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; }
        .vm-time-tile {
            border-radius: 11px; border: 1.5px solid;
            padding: 16px 10px 14px; text-align: center;
            transition: transform .2s, box-shadow .2s;
        }
        .vm-time-tile:hover { transform: translateY(-3px); box-shadow: 0 6px 18px rgba(0,0,0,.1); }
        .vm-time-tile.tt-green { background: linear-gradient(155deg, #edfaf4, #fff); border-color: var(--green-200); }
        .vm-time-tile.tt-red   { background: linear-gradient(155deg, #fee2e2, #fff); border-color: #fecaca; }
        .vm-time-tile.tt-blue  { background: linear-gradient(155deg, #dbeafe, #fff); border-color: #bfdbfe; }
        .vm-time-circle {
            width: 42px; height: 42px; border-radius: 50%; margin: 0 auto 10px;
            display: flex; align-items: center; justify-content: center; font-size: 15px; color: #fff;
        }
        .tt-green .vm-time-circle { background: linear-gradient(135deg, var(--green-500), var(--green-400)); box-shadow: 0 4px 12px rgba(45,122,79,.3); }
        .tt-red   .vm-time-circle { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 4px 12px rgba(239,68,68,.3); }
        .tt-blue  .vm-time-circle { background: linear-gradient(135deg, #3b82f6, #2563eb); box-shadow: 0 4px 12px rgba(59,130,246,.3); }
        .vm-time-lbl {
            font-size: .62rem; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; margin-bottom: 5px;
        }
        .tt-green .vm-time-lbl { color: var(--green-600); }
        .tt-red   .vm-time-lbl { color: #dc2626; }
        .tt-blue  .vm-time-lbl { color: #2563eb; }
        .vm-time-val {
            font-family: 'DM Mono', monospace; font-size: .82rem; font-weight: 600;
            color: var(--gray-900); line-height: 1.4;
        }

        /* Duration hero bar */
        .vm-duration-hero {
            background: linear-gradient(135deg, var(--green-700), var(--green-500));
            border-radius: 10px; padding: 12px 18px;
            display: flex; align-items: center; justify-content: space-between;
            margin-top: 12px;
        }
        .vm-duration-label {
            font-size: .68rem; font-weight: 700; letter-spacing: .08em;
            text-transform: uppercase; color: rgba(255,255,255,.6); margin-bottom: 3px;
        }
        .vm-duration-value {
            font-family: 'Syne', sans-serif; font-size: 1.45rem; font-weight: 800;
            color: #fff; letter-spacing: -.02em;
        }
        .vm-duration-ico {
            width: 44px; height: 44px; border-radius: 12px;
            background: rgba(255,255,255,.15); display: flex; align-items: center;
            justify-content: center; font-size: 18px; color: rgba(255,255,255,.9);
        }

        /* ── MODAL FOOTER ── */
        .modal-footer {
            border-top: 1px solid var(--border) !important;
            background: #fff !important;
            padding: 14px 24px !important;
            border-radius: 0 0 20px 20px !important;
        }
        .vm-footer-inner {
            display: flex; align-items: center; justify-content: space-between;
            width: 100%; gap: 12px; flex-wrap: wrap;
        }
        .vm-footer-pulse {
            display: flex; align-items: center; gap: 7px;
            font-size: .75rem; color: var(--gray-400); font-weight: 500;
        }
        .vm-realtime-dot {
            width: 7px; height: 7px; border-radius: 50%; background: #22c55e;
            animation: rtp 2s ease-in-out infinite;
        }
        @keyframes rtp { 0%,100% { opacity:1; transform:scale(1); } 50% { opacity:.4; transform:scale(.65); } }
        .vm-footer-btns { display: flex; gap: 8px; }
        .btn-vm-close {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 20px; border-radius: 9px;
            background: var(--gray-100); border: 1px solid var(--border);
            font-family: 'DM Sans', sans-serif; font-weight: 600; font-size: .85rem;
            color: var(--gray-600); cursor: pointer; transition: all .2s;
        }
        .btn-vm-close:hover { background: var(--gray-200); color: var(--gray-900); }
        .btn-vm-checkout {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 22px; border-radius: 9px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border: none; font-family: 'DM Sans', sans-serif;
            font-weight: 700; font-size: .85rem; color: #fff;
            cursor: pointer; transition: all .2s; letter-spacing: .01em;
            box-shadow: 0 3px 10px rgba(239,68,68,.35);
        }
        .btn-vm-checkout:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(239,68,68,.45); }

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
        .co-header-text h5 { margin:0; color:#fff; font-family:'Syne',sans-serif; font-size:1.1rem; font-weight:800; }
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

    <!-- ═══════════════════════════════════════════
         ENHANCED VISITOR DETAILS MODAL
         All data-binding IDs are identical to original.
         Only the surrounding visual HTML is redesigned.
    ═══════════════════════════════════════════ -->
    <div class="modal fade" id="visitorModal" tabindex="-1" aria-labelledby="visitorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">

                <!-- ══ HERO HEADER ══ -->
                <div class="modal-header">

                    <!-- top bar -->
                    <div class="vm-topbar">
                        <div class="vm-topbar-left">
                            <div class="vm-topbar-badge">
                                <i class="fas fa-circle" style="font-size:.45rem;"></i>
                                Visitor Record
                            </div>
                            <div class="vm-topbar-title" id="visitorModalLabel">Visitor Information</div>
                        </div>
                        <button type="button" class="vm-close-btn" data-bs-dismiss="modal" aria-label="Close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <!-- profile strip -->
                    <div class="vm-profile-strip">
                        <div class="vm-profile-info">
                            <div class="vm-profile-name" id="vmProfileName">—</div>
                            <div class="vm-profile-meta" id="vmProfileSub">—</div>
                        </div>
                        <div class="vm-pass-pill" id="vmPassPill">—</div>
                    </div>

                    <!-- status strip -->
                    <div class="vm-status-strip">
                        <div class="vm-status-label">Status</div>
                        <div id="modalStatus">—</div>
                        <div class="vm-pass-display">
                            Visit Pass <span id="modalVisitorPass">—</span>
                        </div>
                    </div>

                </div><!-- /modal-header -->

                <!-- ══ BODY ══ -->
                <div class="modal-body">

                    <!-- LEFT: personal info fields -->
                    <div class="vm-fields-col">
                        <div class="vm-section-label">Personal Information</div>

                        <div class="vm-field">
                            <div class="vm-field-ico ico-g"><i class="fas fa-user"></i></div>
                            <div>
                                <div class="vm-field-lbl">Full Name</div>
                                <div class="vm-field-val" id="modalVisitorName">—</div>
                            </div>
                        </div>

                        <div class="vm-field">
                            <div class="vm-field-ico ico-b"><i class="fas fa-phone"></i></div>
                            <div>
                                <div class="vm-field-lbl">Phone Number</div>
                                <div class="vm-field-val" id="modalPhone">—</div>
                            </div>
                        </div>

                        <div class="vm-field">
                            <div class="vm-field-ico ico-t"><i class="fas fa-envelope"></i></div>
                            <div>
                                <div class="vm-field-lbl">Email Address</div>
                                <div class="vm-field-val" id="modalEmail">—</div>
                            </div>
                        </div>

                        <div class="vm-field">
                            <div class="vm-field-ico ico-r"><i class="fas fa-map-marker-alt"></i></div>
                            <div>
                                <div class="vm-field-lbl">Address</div>
                                <div class="vm-field-val" id="modalAddress">—</div>
                            </div>
                        </div>

                        <div class="vm-field">
                            <div class="vm-field-ico ico-x"><i class="fas fa-id-card"></i></div>
                            <div>
                                <div class="vm-field-lbl">ID Type</div>
                                <div class="vm-field-val" id="modalIdType">—</div>
                            </div>
                        </div>

                        <!-- ID Photo -->
                        <div id="modalIdPhotoWrap" style="display:none;margin-top:10px;">
                            <div class="vm-field-lbl" style="margin-bottom:6px;"><i class="fas fa-image me-1" style="color:var(--accent-blue);"></i>Uploaded Valid ID</div>
                            <a id="modalIdPhotoLink" href="#" target="_blank">
                                <img id="modalIdPhoto" src="" alt="Valid ID"
                                    style="width:100%;max-height:180px;object-fit:contain;border-radius:8px;border:1px solid var(--border);background:var(--gray-50);cursor:zoom-in;">
                            </a>
                            <div style="font-size:.72rem;color:var(--gray-400);margin-top:4px;"><i class="fas fa-external-link-alt me-1"></i>Click to open full size</div>
                        </div>
                    </div><!-- /left col -->

                    <!-- RIGHT: visit info -->
                    <div class="vm-info-col">

                        <!-- Visit Information -->
                        <div class="vm-card">
                            <div class="vm-card-head ch-blue">
                                <div class="vm-card-head-ico"><i class="fas fa-clipboard-list"></i></div>
                                <div>
                                    <h6>Visit Information</h6>
                                    <p>Purpose and visit details</p>
                                </div>
                            </div>
                            <div class="vm-card-body">
                                <div class="vm-grid-2" style="margin-bottom:10px;">
                                    <div class="vm-inset">
                                        <div class="vm-inset-lbl">
                                            <i class="fas fa-sitemap" style="color:var(--green-500)"></i> Department
                                        </div>
                                        <div class="vm-inset-val" id="modalDepartment">—</div>
                                    </div>
                                    <div class="vm-inset">
                                        <div class="vm-inset-lbl">
                                            <i class="fas fa-user-tie" style="color:var(--accent-blue)"></i> Person to Visit
                                        </div>
                                        <div class="vm-inset-val" id="modalPersonToVisit">—</div>
                                    </div>
                                </div>
                                <div class="vm-inset" style="margin-bottom:10px;">
                                    <div class="vm-inset-lbl">
                                        <i class="fas fa-bullseye" style="color:var(--accent-teal)"></i> Visit Purpose
                                    </div>
                                    <div class="vm-inset-val" id="modalPurpose">—</div>
                                </div>
                                <div class="vm-inset" style="background:var(--green-50);border-color:var(--green-100);">
                                    <div class="vm-inset-lbl">
                                        <i class="fas fa-users" style="color:var(--green-600)"></i> Group Visit Status
                                    </div>
                                    <div class="vm-inset-val" id="modalGroupStatus">—</div>
                                </div>
                            </div>
                        </div>

                        <!-- Group Members — hidden by JS -->
                        <div class="vm-card" id="groupMembersRow" style="display:none;">
                            <div class="vm-card-head ch-green">
                                <div class="vm-card-head-ico"><i class="fas fa-users"></i></div>
                                <h6>Group Members</h6>
                            </div>
                            <div class="vm-card-body">
                                <div class="vm-inset">
                                    <p style="font-size:.87rem;color:var(--gray-700);line-height:1.7;margin:0;"
                                       id="modalGroupMembers">—</p>
                                </div>
                            </div>
                        </div>

                        <!-- Time Tracking -->
                        <div class="vm-card">
                            <div class="vm-card-head ch-teal">
                                <div class="vm-card-head-ico"><i class="fas fa-clock"></i></div>
                                <div>
                                    <h6>Time Tracking Dashboard</h6>
                                    <p>Check-in, check-out &amp; duration</p>
                                </div>
                            </div>
                            <div class="vm-card-body">
                                <div class="vm-time-trio">
                                    <div class="vm-time-tile tt-green">
                                        <div class="vm-time-circle"><i class="fas fa-sign-in-alt"></i></div>
                                        <div class="vm-time-lbl">Check-in</div>
                                        <div class="vm-time-val" id="modalCheckInTime">—</div>
                                    </div>
                                    <div class="vm-time-tile tt-red">
                                        <div class="vm-time-circle"><i class="fas fa-sign-out-alt"></i></div>
                                        <div class="vm-time-lbl">Check-out</div>
                                        <div class="vm-time-val" id="modalCheckOutTime">—</div>
                                    </div>
                                    <div class="vm-time-tile tt-blue">
                                        <div class="vm-time-circle"><i class="fas fa-hourglass-half"></i></div>
                                        <div class="vm-time-lbl">Duration</div>
                                        <div class="vm-time-val" id="modalDuration">—</div>
                                    </div>
                                </div>
                                <div class="vm-duration-hero" id="vmDurationHero" style="display:none;">
                                    <div>
                                        <div class="vm-duration-label">Total Time on Campus</div>
                                        <div class="vm-duration-value" id="vmDurationText">—</div>
                                    </div>
                                    <div class="vm-duration-ico"><i class="fas fa-hourglass-end"></i></div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Notes -->
                        <div class="vm-card">
                            <div class="vm-card-head ch-orange">
                                <div class="vm-card-head-ico"><i class="fas fa-sticky-note"></i></div>
                                <h6>Additional Notes</h6>
                            </div>
                            <div class="vm-card-body">
                                <div class="vm-inset" style="min-height:46px;">
                                    <p style="font-size:.87rem;color:var(--gray-400);font-style:italic;line-height:1.7;margin:0;"
                                       id="modalNotes">No additional notes provided</p>
                                </div>
                            </div>
                        </div>

                    </div><!-- /right col -->
                </div><!-- /modal-body -->

                <!-- ══ FOOTER ══ -->
                <div class="modal-footer">
                    <div class="vm-footer-inner">
                        <div class="vm-footer-pulse">
                            <div class="vm-realtime-dot"></div>
                            Visitor information is updated in real-time
                        </div>
                        <div class="vm-footer-btns">
                            <button type="button" class="btn-vm-close" data-bs-dismiss="modal">
                                <i class="fas fa-times"></i> Close
                            </button>
                            <button type="button" class="btn-vm-checkout" id="modalCheckoutBtn">
                                <i class="fas fa-sign-out-alt"></i> Check Out Visitor
                            </button>
                        </div>
                    </div>
                </div><!-- /modal-footer -->

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

                const isCheckedIn      = v.is_currently_checked_in == 1;
                const checkedOutToday  = v.checked_out_today == 1;

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
                           <span class="badge-status" style="justify-content:center;background:rgba(239,68,68,.12);color:#ef4444;border:1px solid rgba(239,68,68,.2);">
                               <i class="fas fa-ban"></i> Checked Out
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
                        btn.prop('disabled', false).html('<i class="fas fa-sign-in-alt"></i> Check In');
                    }
                },
                error: function () {
                    showAlert('danger', 'Failed to check in visitor. Please try again.');
                    btn.prop('disabled', false).html('<i class="fas fa-sign-in-alt"></i> Check In');
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
                            $(`#visitor-${visitId}`).fadeOut(300, function () {
                                $(this).remove();
                                refreshVisitors(true);
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
            const initials = (visitor.first_name?.[0] || '') + (visitor.last_name?.[0] || '');

            // ── profile strip ──
            $('#vmProfileName').text(fullName);

            // build meta chips
            const chips = [];
            if (visitor.phone) chips.push(`<span class="vm-profile-chip"><i class="fas fa-phone"></i> ${esc(visitor.phone)}</span>`);
            if (visitor.address) chips.push(`<span class="vm-profile-chip"><i class="fas fa-map-marker-alt"></i> ${esc(visitor.address)}</span>`);
            $('#vmProfileSub').html(chips.join('') || '<span class="vm-profile-chip">No contact info</span>');

            $('#vmPassPill').text(visitor.visit_pass || '—');

            // ── personal fields ──
            $('#modalVisitorName').text(fullName);
            $('#modalPhone').text(visitor.phone || 'Not provided').toggleClass('vm-muted', !visitor.phone);
            $('#modalEmail').text(visitor.email || 'Not provided').toggleClass('vm-muted', !visitor.email);
            $('#modalAddress').text(visitor.address || 'Not provided').toggleClass('vm-muted', !visitor.address);
            $('#modalIdType').text(visitor.id_type || 'Not provided').toggleClass('vm-muted', !visitor.id_type);

            // ID photo
            if (visitor.id_photo_path) {
                const photoSrc = '../' + visitor.id_photo_path;
                $('#modalIdPhoto').attr('src', photoSrc);
                $('#modalIdPhotoLink').attr('href', photoSrc);
                $('#modalIdPhotoWrap').show();
            } else {
                $('#modalIdPhotoWrap').hide();
            }

            // ── visit info ──
            $('#modalDepartment').text(visitor.department || 'Not specified').toggleClass('vm-muted', !visitor.department);
            $('#modalPersonToVisit').text(visitor.person_to_visit || 'Not specified').toggleClass('vm-muted', !visitor.person_to_visit);
            $('#modalPurpose').text(visitor.purpose || 'Not specified').toggleClass('vm-muted', !visitor.purpose);

            // group
            if (visitor.is_group_visit == '1' || visitor.is_group_visit === true || visitor.is_group_visit === 1) {
                $('#modalGroupStatus').html(`
                    <span style="display:inline-flex;align-items:center;gap:6px;font-weight:600;color:var(--green-600);">
                        <i class="fas fa-users"></i> Group Visit — ${visitor.group_size || '?'} people
                    </span>`);
                $('#modalGroupMembers').text(visitor.group_members || 'Members not specified');
                $('#groupMembersRow').show();
            } else {
                $('#modalGroupStatus').html(`
                    <span style="display:inline-flex;align-items:center;gap:6px;color:var(--gray-500);">
                        <i class="fas fa-user"></i> Individual Visit
                    </span>`);
                $('#groupMembersRow').hide();
            }

            $('#modalNotes').text(visitor.additional_notes || 'No additional notes provided');

            // ── status + time ──
            if (isRegisteredVisitor) {
                // registered visitor — show last visit data if one exists
                $('#vmPassPill').text(visitor.visit_pass || '—');
                $('#modalVisitorPass').text(visitor.visit_pass || '—');
                $('#modalCheckoutBtn').hide();

                if (!visitor.visit_pass) {
                    // no visits at all
                    $('#modalStatus').html(`
                        <div class="vm-status-pill spl-registered">
                            <div class="vm-status-pulse"></div>
                            Registered — No visits yet
                        </div>`);
                    $('#modalCheckInTime').text('No visits yet');
                    $('#modalCheckOutTime').text('—');
                    $('#modalDuration').text('—');
                    $('#vmDurationHero').hide();
                } else {
                    const statusLabel = visitor.visit_status === 'checked_out' ? 'Last Visit: Checked Out'
                                      : visitor.visit_status === 'checked_in'  ? 'Currently Checked In'
                                      : visitor.visit_status === 'no_show'     ? 'Last Visit: No Show'
                                      : 'Registered';
                    const statusClass = visitor.visit_status === 'checked_in' ? 'spl-active' : 'spl-registered';
                    $('#modalStatus').html(`
                        <div class="vm-status-pill ${statusClass}">
                            <div class="vm-status-pulse"></div>
                            ${statusLabel}
                        </div>`);
                    $('#modalCheckInTime').html(visitor.check_in_time
                        ? formatFullDateTime(visitor.check_in_time).replace(', ', '<br>')
                        : '—');
                    $('#modalCheckOutTime').html(visitor.check_out_time
                        ? formatFullDateTime(visitor.check_out_time).replace(', ', '<br>')
                        : '—');
                    $('#modalDuration').text(visitor.duration_minutes > 0
                        ? formatDuration(visitor.duration_minutes) : '—');
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
                    $('#modalStatus').html(`
                        <div class="vm-status-pill spl-overdue">
                            <div class="vm-status-pulse"></div>
                            Overdue
                        </div>`);
                } else {
                    $('#modalStatus').html(`
                        <div class="vm-status-pill spl-active">
                            <div class="vm-status-pulse"></div>
                            Active Visit
                        </div>`);
                }

                $('#modalCheckInTime').html(formatFullDateTime(visitor.check_in_time).replace(', ', '<br>'));
                $('#modalCheckOutTime').html(visitor.check_out_time
                    ? formatFullDateTime(visitor.check_out_time).replace(', ', '<br>')
                    : 'Still<br>checked in');
                $('#modalDuration').text(formatDuration(visitor.duration_minutes));

                // duration hero
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
                    dialog.style.maxWidth = '900px';
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