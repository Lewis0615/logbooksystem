<?php
/**
 * Admin Visitor & Blocklist Management
 * St. Dominic Savio College - Visitor Management System
 * View registered visitors and manage the blocklist (add, deactivate entries)
 */

require_once '../config/config.php';
require_once '../config/auth.php';

// Check authentication - Admin only
$auth->requireLogin('login.php');

// Handle AJAX request for registered visitors
if (isset($_GET['action']) && $_GET['action'] === 'get_registered_visitors') {
    header('Content-Type: application/json');
    
    try {
        $visitors = $db->fetchAll("
            SELECT v.*, 
                   COUNT(vi.id) as total_visits,
                   MAX(vi.check_in_time) as last_visit,
                   SUM(CASE WHEN vi.status = 'checked_in' THEN 1 ELSE 0 END) as is_currently_checked_in
            FROM visitors v
            LEFT JOIN visits vi ON v.id = vi.visitor_id
            GROUP BY v.id
            ORDER BY v.created_at DESC
        ");
        
        echo json_encode([
            'success' => true,
            'visitors' => $visitors
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

// Handle AJAX request for current (checked-in) visitors
if (isset($_GET['action']) && $_GET['action'] === 'get_current_visitors') {
    header('Content-Type: application/json');
    try {
        $visitors = $db->fetchAll("
            SELECT
                vi.id, vi.visit_pass, vi.check_in_time, vi.check_out_time,
                vi.expected_checkout_time, vi.department, vi.is_group_visit,
                vi.group_size, vi.group_members, vi.additional_notes,
                vi.person_to_visit, vi.purpose,
                vis.id as visitor_id,
                vis.first_name, vis.last_name, vis.phone, vis.email,
                vis.address, vis.company_organization, vis.id_type,
                TIMESTAMPDIFF(MINUTE, vi.check_in_time, NOW()) as duration_minutes,
                CASE WHEN NOW() > vi.expected_checkout_time THEN 1 ELSE 0 END as is_overdue
            FROM visits vi
            JOIN visitors vis ON vi.visitor_id = vis.id
            WHERE vi.status = 'checked_in'
            ORDER BY vi.check_in_time ASC
        ");
        echo json_encode(['success' => true, 'visitors' => $visitors]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX request for checked-out visitors
if (isset($_GET['action']) && $_GET['action'] === 'get_checked_out_visitors') {
    header('Content-Type: application/json');
    try {
        $limit = max(1, min(200, (int)($_GET['limit'] ?? 50)));
        $visitors = $db->fetchAll("
            SELECT
                vi.id, vi.visit_pass, vi.check_in_time, vi.check_out_time,
                vi.department, vi.is_group_visit, vi.group_size,
                vi.person_to_visit, vi.purpose,
                vis.id as visitor_id,
                vis.first_name, vis.last_name, vis.phone,
                TIMESTAMPDIFF(MINUTE, vi.check_in_time, vi.check_out_time) as visit_duration_minutes
            FROM visits vi
            JOIN visitors vis ON vi.visitor_id = vis.id
            WHERE vi.status = 'checked_out'
            ORDER BY vi.check_out_time DESC
            LIMIT ?
        ", [$limit]);
        echo json_encode(['success' => true, 'visitors' => $visitors]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Handle AJAX action: add_to_blacklist
if (isset($_POST['action']) && $_POST['action'] === 'add_to_blacklist') {
    header('Content-Type: application/json');

    $visitor_id   = (int)($_POST['visitor_id']   ?? 0);
    $first_name   = trim($_POST['first_name']    ?? '');
    $last_name    = trim($_POST['last_name']     ?? '');
    $phone        = trim($_POST['phone']         ?? '');
    $email        = trim($_POST['email']         ?? '');
    $id_number    = trim($_POST['id_number']     ?? '');
    $reason       = trim($_POST['reason']        ?? '');
    $severity     = in_array(trim($_POST['severity'] ?? ''), ['low','medium','high']) ? trim($_POST['severity']) : 'medium';
    $is_permanent = isset($_POST['is_permanent']) ? 1 : 0;
    $expiry_date  = ($is_permanent ? null : (trim($_POST['expiry_date'] ?? '') ?: null));
    $user_id      = $_SESSION['user_id'];

    if (!$first_name || !$last_name || !$reason) {
        echo json_encode(['success' => false, 'message' => 'First name, last name, and reason are required.']);
        exit;
    }

    try {
        $existing = $db->fetch(
            "SELECT id FROM blacklist WHERE (visitor_id = ? OR (phone != '' AND phone = ?)) AND status = 'active' LIMIT 1",
            [$visitor_id, $phone]
        );
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'This visitor already has an active blocklist entry.']);
            exit;
        }

        $db->execute(
            "INSERT INTO blacklist (visitor_id, first_name, last_name, phone, email, id_number, reason, severity, is_permanent, expiry_date, status, reported_by, approved_by, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, NOW(), NOW())",
            [$visitor_id, $first_name, $last_name, $phone, $email, $id_number, $reason, $severity, $is_permanent, $expiry_date, $user_id, $user_id, $user_id]
        );

        $auth->logActivity($user_id, 'BLACKLIST_ADD',
            "Admin added to blocklist: {$first_name} {$last_name} ({$phone}) — {$reason}");
        echo json_encode(['success' => true, 'message' => "{$first_name} {$last_name} has been added to the blocklist."]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle AJAX action: update_blacklist_status (deactivate / reactivate)
if (isset($_POST['action']) && $_POST['action'] === 'update_blacklist_status') {
    header('Content-Type: application/json');

    $blacklist_id = (int)($_POST['blacklist_id'] ?? 0);
    $new_status   = ($_POST['new_status'] ?? '') === 'active' ? 'active' : 'inactive';
    $user_id      = $_SESSION['user_id'];

    if ($blacklist_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid blocklist entry ID.']);
        exit;
    }

    try {
        $db->execute("UPDATE blacklist SET status = ?, updated_at = NOW() WHERE id = ?", [$new_status, $blacklist_id]);
        $label = $new_status === 'active' ? 'reactivated' : 'deactivated';
        $auth->logActivity($user_id, 'BLACKLIST_UPDATE', "Admin {$label} blocklist entry ID: {$blacklist_id}");
        echo json_encode(['success' => true, 'message' => "Blocklist entry has been {$label}."]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle AJAX action: get_blacklist_entries
if (isset($_GET['action']) && $_GET['action'] === 'get_blacklist_entries') {
    header('Content-Type: application/json');
    try {
        $entries = $db->fetchAll("
            SELECT b.*,
                   CASE WHEN b.is_permanent = 1 THEN 'Permanent'
                        WHEN b.expiry_date IS NULL OR b.expiry_date >= CURDATE() THEN 'Active'
                        ELSE 'Expired' END AS expiry_label,
                   CASE WHEN b.expiry_date < CURDATE() AND b.is_permanent = 0 THEN 1 ELSE 0 END AS is_expired
            FROM blacklist b
            ORDER BY b.created_at DESC
            LIMIT 100
        ");
        echo json_encode(['success' => true, 'entries' => $entries]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error loading blocklist: ' . $e->getMessage()]);
        exit;
    }
}

$csrf_token = generateCSRFToken();

// Pre-load data inline — no AJAX needed for first render
$initial_registered_visitors = [];
$initial_current_visitors     = [];
try {
    $initial_registered_visitors = $db->fetchAll("
        SELECT v.*,
               COUNT(vi.id) as total_visits,
               MAX(vi.check_in_time) as last_visit,
               SUM(CASE WHEN vi.status = 'checked_in' THEN 1 ELSE 0 END) as is_currently_checked_in
        FROM visitors v
        LEFT JOIN visits vi ON v.id = vi.visitor_id
        GROUP BY v.id
        ORDER BY v.created_at DESC
    ");
} catch (Exception $e) { error_log('Inline registered visitors error: ' . $e->getMessage()); }
try {
    $initial_current_visitors = $db->fetchAll("
        SELECT
            vi.id, vi.visit_pass, vi.check_in_time, vi.check_out_time,
            vi.expected_checkout_time, vi.department, vi.is_group_visit,
            vi.group_size, vi.group_members, vi.additional_notes,
            vi.person_to_visit, vi.purpose,
            vis.id as visitor_id,
            vis.first_name, vis.last_name, vis.phone, vis.email,
            vis.address, vis.company_organization, vis.id_type,
            TIMESTAMPDIFF(MINUTE, vi.check_in_time, NOW()) as duration_minutes,
            CASE WHEN NOW() > vi.expected_checkout_time THEN 1 ELSE 0 END as is_overdue
        FROM visits vi
        JOIN visitors vis ON vi.visitor_id = vis.id
        WHERE vi.status = 'checked_in'
        ORDER BY vi.check_in_time ASC
    ");
} catch (Exception $e) { error_log('Inline current visitors error: ' . $e->getMessage()); }
$initial_checked_out_visitors = [];
try {
    $initial_checked_out_visitors = $db->fetchAll("
        SELECT
            vi.id, vi.visit_pass, vi.check_in_time, vi.check_out_time,
            vi.department, vi.is_group_visit, vi.group_size,
            vi.person_to_visit, vi.purpose,
            vis.id as visitor_id,
            vis.first_name, vis.last_name, vis.phone,
            TIMESTAMPDIFF(MINUTE, vi.check_in_time, vi.check_out_time) as visit_duration_minutes
        FROM visits vi
        JOIN visitors vis ON vi.visitor_id = vis.id
        WHERE vi.status = 'checked_out'
        ORDER BY vi.check_out_time DESC
        LIMIT 50
    ");
} catch (Exception $e) { error_log('Inline checked-out visitors error: ' . $e->getMessage()); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor &amp; Blocklist Management - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
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

        /* ─── ANIMATIONS ─── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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

        .admin-badge {
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

        /* ─── CARDS ─── */
        .card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            animation: fadeUp .5s ease both;
            height: 100%;
        }

        .col-lg-5 .card { animation-delay: .25s; }
        .col-lg-7 .card { animation-delay: .30s; }

        .card-header {
            background: var(--gray-50);
            border-bottom: 1px solid var(--border-lt);
            padding: 16px 20px;
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
            background: var(--accent-blue-lt);
            color: var(--accent-blue);
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
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59,130,246,.12);
        }

        .search-input::placeholder { color: var(--gray-400); }

        /* ─── REGISTERED VISITOR ITEM ─── */
        .registered-visitor-item:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .rv-name {
            font-weight: 700;
            font-size: .88rem;
            color: var(--gray-900);
            margin-bottom: 2px;
        }

        .rv-meta {
            font-size: .75rem;
            color: var(--gray-500);
            line-height: 1.5;
        }

        .rv-meta i { width: 14px; opacity: .7; }

        /* ─── TABLE HEADERS ─── */
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
        .tbl-head.current { grid-template-columns: 1.6fr 1fr 1.2fr 0.7fr 1fr 0.75fr 0.9fr; }
        .tbl-head.checkout { grid-template-columns: 1.6fr 1fr 1.2fr 0.7fr 1fr 1fr 1fr; }

        .checkout-row {
            display: grid;
            grid-template-columns: 1.6fr 1fr 1.2fr 0.7fr 1fr 1fr 1fr;
            gap: 12px;
            align-items: center;
            padding: 12px 14px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            margin-bottom: 6px;
            transition: transform .15s, box-shadow .15s;
        }
        .checkout-row:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .visitor-row {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            margin-bottom: 8px;
            transition: transform .2s, box-shadow .2s;
            display: grid;
            grid-template-columns: 1.6fr 1fr 1.2fr 0.7fr 1fr 0.75fr 0.9fr;
            gap: 12px;
            padding: 12px 14px;
            align-items: center;
            font-size: .85rem;
            cursor: pointer;
        }

        .visitor-row:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }
        .overdue-row {
            border-left: 3px solid var(--accent-red) !important;
            background: #fff9f9 !important;
        }

        .visitor-cell {
            padding: 0;
        }

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

        .group-yes {
            background: var(--green-100);
            color: var(--green-600);
        }

        .group-no {
            background: var(--gray-100);
            color: var(--gray-500);
        }

        .datetime-text {
            font-size: .8rem;
            color: var(--gray-600);
            font-family: 'DM Mono', monospace;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            align-items: center;
        }

        /* ─── BUTTONS ─── */
        .btn-refresh {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: 'DM Sans', sans-serif;
            font-size: .8rem;
            font-weight: 600;
            color: var(--gray-600);
            cursor: pointer;
            transition: all .2s;
        }

        .btn-refresh:hover {
            background: var(--gray-100);
            color: var(--gray-800);
        }

        .btn-view {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 8px 14px;
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
            border: none;
            border-radius: var(--radius-sm);
            font-family: 'DM Sans', sans-serif;
            font-size: .75rem;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            transition: all .2s;
            box-shadow: 0 2px 8px rgba(59,130,246,.28);
            min-width: 70px;
        }

        .btn-view:hover {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(59,130,246,.35);
        }

        .btn-view-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 6px 12px;
            background: transparent;
            border: 1px solid var(--accent-blue);
            border-radius: var(--radius-sm);
            font-family: 'DM Sans', sans-serif;
            font-size: .75rem;
            font-weight: 600;
            color: var(--accent-blue);
            cursor: pointer;
            transition: all .2s;
        }

        .btn-view-outline:hover {
            background: var(--accent-blue-lt);
        }

        /* ─── STATUS BADGES ─── */
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 11px;
            border-radius: 14px;
            font-size: .75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-active {
            background: var(--green-100);
            color: var(--green-600);
        }

        .badge-active .fa-circle {
            font-size: 6px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: .5; }
        }

        /* ─── EMPTY STATE ─── */
        .empty-state {
            text-align: center;
            padding: 60px 24px;
        }

        .empty-icon {
            width: 84px;
            height: 84px;
            margin: 0 auto 20px;
            background: var(--gray-100);
            color: var(--gray-400);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
        }

        .empty-title {
            font-family: 'Syne', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--gray-700);
            margin-bottom: 8px;
        }

        .empty-sub {
            font-size: .9rem;
            color: var(--gray-500);
            max-width: 420px;
            margin: 0 auto;
        }

        /* ─── REFRESH TOAST ─── */
        .refresh-toast {
            position: fixed;
            bottom: 24px;
            right: 280px;
            background: var(--accent-blue);
            color: #fff;
            padding: 8px 18px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: .8rem;
            font-weight: 600;
            opacity: 0;
            transform: translateY(12px);
            transition: all .3s ease;
            z-index: 9999;
            box-shadow: 0 4px 20px rgba(59,130,246,.25);
        }

        .refresh-toast.show {
            opacity: 1;
            transform: translateY(0);
        }

        /* ─── MODAL STYLES ─── */
        #visitorModal { display: none !important; }
        #visitorModal.show {
            display: block !important;
            position: fixed; top: 0; left: 0;
            width: 100%; height: 100%;
            z-index: 1050; overflow-y: auto; padding: 24px 16px;
        }
        .modal-backdrop { display: none !important; }
        .modal-backdrop.show {
            display: block !important; position: fixed; top: 0; left: 0;
            width: 100%; height: 100%; z-index: 1040;
            background: rgba(7,26,16,.72); backdrop-filter: blur(5px);
        }
        body.modal-open { overflow: hidden; padding-right: 0 !important; }
        .modal-dialog {
            position: relative; margin: 0 auto; max-width: 880px;
            pointer-events: none;
        }
        .modal-content {
            pointer-events: auto; background: #fff;
            border-radius: 20px !important;
            box-shadow: 0 24px 80px rgba(0,0,0,.28) !important;
            border: none !important; overflow: hidden;
        }
        .modal-header {
            padding: 0 !important; border-bottom: none !important;
            background: linear-gradient(135deg, #071a10 0%, #163d27 45%, #256642 100%) !important;
            flex-direction: column !important; align-items: stretch !important;
            position: relative; overflow: hidden;
        }
        .modal-header::before {
            content: ''; position: absolute; top: -80px; right: -80px;
            width: 300px; height: 300px; border-radius: 50%;
            background: radial-gradient(circle, rgba(52,196,125,.16) 0%, transparent 70%);
            animation: blobFloat 8s ease-in-out infinite; pointer-events: none;
        }
        .modal-header::after {
            content: ''; position: absolute; bottom: -100px; left: -60px;
            width: 260px; height: 260px; border-radius: 50%;
            background: radial-gradient(circle, rgba(58,153,98,.1) 0%, transparent 70%);
            animation: blobFloat 11s ease-in-out infinite reverse; pointer-events: none;
        }
        @keyframes blobFloat {
            0%,100% { transform: translate(0,0) scale(1); }
            33%      { transform: translate(15px,-10px) scale(1.05); }
            66%      { transform: translate(-10px,8px) scale(.96); }
        }
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
        .vm-profile-strip {
            margin: 0 24px 0;
            background: rgba(255,255,255,.07); border: 1px solid rgba(255,255,255,.12);
            border-bottom: none; border-radius: 14px 14px 0 0;
            padding: 18px 22px; display: flex; align-items: center; gap: 16px;
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
        .vm-status-strip {
            margin: 0 24px; padding: 10px 22px;
            background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1);
            border-top: none; border-radius: 0 0 10px 10px;
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
        @keyframes spulse { 0%,100% { opacity:1; } 50% { opacity:.5; } }
        .vm-pass-display {
            margin-left: auto; font-family: 'DM Mono', monospace;
            font-size: .78rem; color: rgba(255,255,255,.4); font-weight: 500;
        }
        .vm-pass-display span { color: rgba(255,255,255,.75); font-weight: 600; margin-left: 5px; }
        .modal-body {
            padding: 22px 24px !important; background: var(--gray-50) !important;
            display: grid !important; grid-template-columns: 1fr 1.55fr !important; gap: 18px !important;
        }
        .vm-fields-col { display: flex; flex-direction: column; gap: 8px; }
        .vm-section-label {
            font-size: .63rem; font-weight: 700; letter-spacing: .1em;
            text-transform: uppercase; color: var(--gray-400);
            padding-bottom: 6px; margin-bottom: 2px; border-bottom: 1px solid var(--border-lt);
        }
        .vm-field {
            background: #fff; border: 1px solid var(--border); border-radius: 10px;
            padding: 11px 14px; display: flex; align-items: flex-start; gap: 11px;
            transition: border-color .2s, box-shadow .2s, transform .15s;
        }
        .vm-field:hover { border-color: var(--green-300); box-shadow: 0 2px 10px rgba(58,153,98,.1); transform: translateX(2px); }
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
        .vm-info-col { display: flex; flex-direction: column; gap: 14px; }
        .vm-card {
            background: #fff; border: 1px solid var(--border); border-radius: 12px;
            overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,.05); transition: box-shadow .2s;
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
            font-family: 'Syne', sans-serif; font-size: .9rem; font-weight: 700; color: #fff; margin: 0 0 2px;
        }
        .vm-card-head p { font-size: .72rem; color: rgba(255,255,255,.7); margin: 0; }
        .vm-card-body { padding: 16px 18px; }
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
        .vm-time-trio { display: grid; grid-template-columns: repeat(3,1fr); gap: 10px; }
        .vm-time-tile {
            border-radius: 11px; border: 1.5px solid; padding: 16px 10px 14px; text-align: center;
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
            font-size: .62rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; margin-bottom: 5px;
        }
        .tt-green .vm-time-lbl { color: var(--green-600); }
        .tt-red   .vm-time-lbl { color: #dc2626; }
        .tt-blue  .vm-time-lbl { color: #2563eb; }
        .vm-time-val {
            font-family: 'DM Mono', monospace; font-size: .82rem; font-weight: 600;
            color: var(--gray-900); line-height: 1.4;
        }
        .vm-duration-hero {
            background: linear-gradient(135deg, var(--green-700), var(--green-500));
            border-radius: 10px; padding: 12px 18px;
            display: flex; align-items: center; justify-content: space-between; margin-top: 12px;
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
        .btn-vm-blocklist {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 22px; border-radius: 9px;
            background: linear-gradient(135deg, #dc2626, #991b1b);
            border: none; font-family: 'DM Sans', sans-serif;
            font-weight: 700; font-size: .85rem; color: #fff;
            cursor: pointer; transition: all .2s; letter-spacing: .01em;
            box-shadow: 0 3px 10px rgba(220,38,38,.35);
        }
        .btn-vm-blocklist:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(220,38,38,.45); }
        /* ─── BLOCKLIST PANEL ─── */
        .tbl-head.blocklist { grid-template-columns: 1.4fr 0.9fr 1.6fr 0.75fr 0.7fr 0.85fr 72px; }
        .bl-row {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            margin-bottom: 8px;
            display: grid;
            grid-template-columns: 1.4fr 0.9fr 1.6fr 0.75fr 0.7fr 0.85fr 72px;
            gap: 10px;
            padding: 11px 14px;
            align-items: center;
            font-size: .84rem;
            transition: transform .2s, box-shadow .2s;
        }
        .bl-row:hover { transform: translateY(-1px); box-shadow: var(--shadow-sm); }
        .bl-row.inactive { opacity: .55; }
        .bl-name { font-weight: 700; font-size: .88rem; color: var(--gray-900); margin-bottom: 2px; }
        .bl-meta { font-size: .74rem; color: var(--gray-500); }
        .sev-badge {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: .7rem; font-weight: 700; letter-spacing: .06em;
            text-transform: uppercase; padding: 3px 9px; border-radius: 12px;
        }
        .sev-low    { background: var(--accent-teal-lt);   color: var(--accent-teal); }
        .sev-medium { background: var(--accent-orange-lt); color: var(--accent-orange); }
        .sev-high   { background: var(--accent-red-lt);    color: var(--accent-red); }
        .bl-status-badge {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: .7rem; font-weight: 700; padding: 3px 9px; border-radius: 12px;
        }
        .bl-active   { background: var(--green-100); color: var(--green-700); }
        .bl-inactive { background: var(--gray-100);  color: var(--gray-500); }
        /* ─── ACTION BUTTONS ─── */
        .btn-blacklist {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 10px; border-radius: 6px;
            background: var(--accent-red-lt); border: 1px solid #fecaca;
            color: var(--accent-red); font-size: .75rem; font-weight: 700;
            cursor: pointer; transition: all .2s; white-space: nowrap;
        }
        .btn-blacklist:hover { background: var(--accent-red); color: #fff; border-color: var(--accent-red); }
        .btn-deactivate {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 5px 8px; border-radius: 6px;
            background: var(--accent-red-lt); border: 1px solid #fecaca;
            color: var(--accent-red); font-size: .73rem; font-weight: 600;
            cursor: pointer; transition: all .2s;
        }
        .btn-deactivate:hover { background: var(--accent-red); color: #fff; }
        .btn-activate {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 5px 8px; border-radius: 6px;
            background: var(--green-100); border: 1px solid var(--green-200);
            color: var(--green-700); font-size: .73rem; font-weight: 600;
            cursor: pointer; transition: all .2s;
        }
        .btn-activate:hover { background: var(--green-500); color: #fff; }
        /* ─── ADD BLACKLIST MODAL ─── */
        .bl-modal-header {
            background: linear-gradient(135deg, #7f1d1d 0%, #991b1b 50%, #dc2626 100%);
            padding: 22px 26px; border-radius: 16px 16px 0 0; position: relative; overflow: hidden;
        }
        .bl-modal-header h5 {
            font-family: 'Syne', sans-serif; font-weight: 800; font-size: 1.1rem;
            color: #fff; margin: 0; letter-spacing: -.01em;
        }
        .bl-modal-header p { color: rgba(255,255,255,.7); font-size: .82rem; margin: 4px 0 0; }
        .form-label-sm { font-size: .75rem; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: var(--gray-500); margin-bottom: 5px; }
        .form-control-sm-custom {
            width: 100%; padding: 8px 12px; border: 1px solid var(--border); border-radius: 8px;
            font-family: 'DM Sans', sans-serif; font-size: .88rem; color: var(--gray-800);
            background: #fff; transition: border-color .2s, box-shadow .2s; outline: none;
        }
        .form-control-sm-custom:focus { border-color: var(--accent-red); box-shadow: 0 0 0 3px rgba(239,68,68,.12); }
        @media (max-width: 768px) {
            .modal-body { grid-template-columns: 1fr !important; }
            .vm-time-trio { grid-template-columns: 1fr 1fr; }
            .vm-grid-2 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include '../includes/admin-sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        
        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="page-header-inner">
                <div>
                    <h2>
                        <i class="fas fa-users-slash"></i>
                        Visitor Check In/Out
                    </h2>
                    <p class="subtitle">Monitor on-campus visitors and manage the blocklist</p>
                </div>
                <div class="admin-badge">
                    <i class="fas fa-shield-alt"></i>
                    Admin Control
                </div>
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

            <!-- CURRENT VISITORS (real-time monitor) -->
            <div class="col-lg-7">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-header-title">
                            <span class="card-header-icon" style="background:var(--green-100);color:var(--green-600);"><i class="fas fa-user-check"></i></span>
                            Currently On-Campus
                            <span id="currentVisitorCount" class="ms-2" style="font-family:'DM Mono',monospace;font-size:.75rem;font-weight:600;background:var(--green-100);color:var(--green-700);padding:2px 9px;border-radius:20px;"></span>
                        </h5>
                        <div class="card-header-actions">
                            <span style="font-size:.72rem;color:var(--gray-400);margin-right:6px;">Auto-refreshes every 30s</span>
                            <button class="btn-refresh" onclick="refreshCurrentVisitors()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body-scroll">
                        <div class="tbl-head current d-none" id="currentVisitorsHeader">
                            <div>Visitor Name</div>
                            <div>Contact</div>
                            <div>Purpose</div>
                            <div>Group</div>
                            <div>Check-In</div>
                            <div>Duration</div>
                            <div>Status</div>
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

        <!-- CHECKED-OUT VISITORS TABLE -->
        <div class="row g-3 mt-1">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-header-title">
                            <span class="card-header-icon" style="background:var(--accent-orange-lt);color:var(--accent-orange);"><i class="fas fa-sign-out-alt"></i></span>
                            Checked-Out Visitors
                            <span id="checkedOutCount" class="ms-2" style="font-family:'DM Mono',monospace;font-size:.75rem;font-weight:600;background:var(--accent-orange-lt);color:var(--accent-orange);padding:2px 9px;border-radius:20px;"></span>
                        </h5>
                        <div class="card-header-actions">
                            <span style="font-size:.72rem;color:var(--gray-400);margin-right:6px;">Most recent 50 check-outs</span>
                            <button class="btn-refresh" onclick="refreshCheckedOutVisitors()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body-scroll" style="max-height:420px;">
                        <div class="tbl-head checkout d-none" id="checkedOutHeader">
                            <div>Visitor Name</div>
                            <div>Contact</div>
                            <div>Purpose</div>
                            <div>Group</div>
                            <div>Check-In</div>
                            <div>Check-Out</div>
                            <div>Duration</div>
                        </div>
                        <div id="checkedOutList">
                            <div class="empty-state">
                                <div class="empty-icon"><i class="fas fa-spinner fa-spin"></i></div>
                                <div class="empty-title">Loading checked-out visitors…</div>
                                <div class="empty-sub">Please wait while we fetch the data.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /checkout row -->
    </div><!-- /main-content -->

    <!-- REFRESH TOAST -->
    <div id="refreshToast" class="refresh-toast">
        <i class="fas fa-sync-alt me-1"></i> Updated
    </div>

    <!-- ADD TO BLOCKLIST MODAL -->
    <div class="modal fade" id="blacklistModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;">

                <div class="bl-modal-header">
                    <h5><i class="fas fa-ban me-2"></i>Add Visitor to Blocklist</h5>
                    <p>Complete the form below to add this visitor to the blocklist.</p>
                    <button type="button" class="vm-close-btn" style="position:absolute;top:18px;right:18px;" onclick="hideBlacklistModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div class="modal-body" style="display:block!important;padding:24px!important;background:#fff!important;">
                    <form id="blacklistForm">
                        <input type="hidden" id="blVisitorId" name="visitor_id" value="">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-sm">First Name *</label>
                                <input type="text" id="blFirstName" name="first_name" class="form-control-sm-custom" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-sm">Last Name *</label>
                                <input type="text" id="blLastName" name="last_name" class="form-control-sm-custom" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-sm">Phone Number</label>
                                <input type="text" id="blPhone" name="phone" class="form-control-sm-custom">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-sm">Email Address</label>
                                <input type="email" id="blEmail" name="email" class="form-control-sm-custom">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-sm">ID Number</label>
                                <input type="text" id="blIdNumber" name="id_number" class="form-control-sm-custom" placeholder="Optional">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-sm">Severity *</label>
                                <select id="blSeverity" name="severity" class="form-control-sm-custom" required>
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label-sm">Reason for Blacklisting *</label>
                                <textarea id="blReason" name="reason" class="form-control-sm-custom" rows="3"
                                          placeholder="Describe the reason for adding to blocklist…" required></textarea>
                            </div>
                            <div class="col-md-6">
                                <div style="display:flex;align-items:center;gap:10px;margin-top:4px;">
                                    <input type="checkbox" id="blIsPermanent" name="is_permanent"
                                           style="width:16px;height:16px;accent-color:var(--accent-red);cursor:pointer;"
                                           onchange="toggleExpiryField()">
                                    <label for="blIsPermanent" class="form-label-sm" style="margin:0;cursor:pointer;">Permanent Blacklist</label>
                                </div>
                            </div>
                            <div class="col-md-6" id="expiryDateWrap">
                                <label class="form-label-sm">Expiry Date</label>
                                <input type="date" id="blExpiryDate" name="expiry_date" class="form-control-sm-custom">
                            </div>
                        </div>
                    </form>
                </div>

                <div class="modal-footer" style="border-top:1px solid var(--border);padding:14px 24px;display:flex;justify-content:flex-end;gap:8px;">
                    <button type="button" class="btn-vm-close" onclick="hideBlacklistModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="button" class="btn-vm-blocklist" onclick="submitBlacklist()">
                        <i class="fas fa-ban"></i> Add to Blocklist
                    </button>
                </div>

            </div>
        </div>
    </div>

    <!-- Visitor Details Modal -->
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
                        <button type="button" class="vm-close-btn" onclick="hideModal()" aria-label="Close">
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
                            <button type="button" class="btn-vm-close" onclick="hideModal()">
                                <i class="fas fa-times"></i> Close
                            </button>
                            <button type="button" class="btn-vm-blocklist" id="modalBlocklistBtn" style="display:none;">
                                <i class="fas fa-ban"></i> Add to Blocklist
                            </button>
                        </div>
                    </div>
                </div><!-- /modal-footer -->

            </div><!-- /modal-content -->
        </div><!-- /modal-dialog -->
    </div><!-- /visitorModal -->

    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        if (typeof jQuery === 'undefined') {
            console.error('jQuery failed to load locally. Loading from CDN...');
            document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
        }

        let refreshInterval;
        const csrfToken = '<?php echo $csrf_token; ?>';
        let currentViewedVisitorId = null;
        let currentVisitorsData    = {};
        const initialRegisteredVisitors  = <?php echo json_encode($initial_registered_visitors  ?: []); ?>;
        const initialCurrentVisitors     = <?php echo json_encode($initial_current_visitors      ?: []); ?>;
        const initialCheckedOutVisitors  = <?php echo json_encode($initial_checked_out_visitors  ?: []); ?>;

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

                // Render initial data inline — no AJAX needed for first load
                displayRegisteredVisitors(initialRegisteredVisitors);
                displayCurrentVisitors(initialCurrentVisitors);
                displayCheckedOutVisitors(initialCheckedOutVisitors);
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
                refreshRegisteredVisitors();
                refreshCurrentVisitors(true);
                refreshCheckedOutVisitors(true);
            }, 30000);
        }

        /* ── REGISTERED VISITORS ── */
        function refreshRegisteredVisitors() {
            $('#registeredVisitorsList').html(loadingHTML('registered visitors'));

            $.ajax({
                url: 'admin-visitor-checkin.php?action=get_registered_visitors',
                method: 'GET',
                dataType: 'json',
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

                const actionButtons = `<div style="display:flex;flex-direction:column;gap:6px;">
                    <button class="btn-view-outline" onclick="viewRegisteredVisitorDetails(${v.id})">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button class="btn-blacklist"
                        onclick="openBlacklistModal(${v.id}, '${esc(v.first_name)}', '${esc(v.last_name)}', '${esc(v.phone)}', '${esc(v.email || '')}')">
                        <i class="fas fa-ban"></i> Blocklist
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

        /* ── CURRENT VISITORS MONITOR ── */
        function refreshCurrentVisitors(silent = false) {
            if (!silent) {
                $('#currentVisitorsHeader').addClass('d-none');
                $('#currentVisitorsList').html(loadingHTML('current visitors'));
            }
            $.ajax({
                url: 'admin-visitor-checkin.php?action=get_current_visitors',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        displayCurrentVisitors(data.visitors);
                        if (silent) showRefreshToast();
                    } else {
                        $('#currentVisitorsList').html(errorHTML(data.message));
                    }
                },
                error: function() {
                    $('#currentVisitorsList').html(errorHTML('Failed to load current visitors. Please refresh.'));
                }
            });
        }

        function displayCurrentVisitors(visitors) {
            const count = visitors ? visitors.length : 0;
            $('#currentVisitorCount').text(count ? count + ' on-campus' : '');

            // build lookup map
            currentVisitorsData = {};
            if (visitors) visitors.forEach(function(v) { currentVisitorsData[v.id] = v; });

            if (!count) {
                $('#currentVisitorsHeader').addClass('d-none');
                $('#currentVisitorsList').html(`
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-door-open"></i></div>
                        <div class="empty-title">Campus is Clear</div>
                        <div class="empty-sub">No visitors are currently checked in.</div>
                    </div>`);
                return;
            }

            $('#currentVisitorsHeader').removeClass('d-none');

            let html = '';
            visitors.forEach(function(v) {
                const groupBadge = v.is_group_visit
                    ? `<span class="group-badge group-yes"><i class="fas fa-users"></i> ${v.group_size || '?'}</span>`
                    : `<span class="group-badge group-no"><i class="fas fa-user"></i> Solo</span>`;

                const duration = formatDuration(v.duration_minutes);
                const checkInFmt = new Date(v.check_in_time).toLocaleString('en-US', {
                    month: 'short', day: 'numeric',
                    hour: 'numeric', minute: '2-digit', hour12: true
                });

                const statusBadge = v.is_overdue
                    ? `<span style="display:inline-flex;align-items:center;gap:5px;font-size:.72rem;font-weight:700;padding:3px 9px;border-radius:12px;background:var(--accent-red-lt);color:var(--accent-red);">
                           <i class="fas fa-exclamation-circle"></i> Overdue
                       </span>`
                    : `<span style="display:inline-flex;align-items:center;gap:5px;font-size:.72rem;font-weight:700;padding:3px 9px;border-radius:12px;background:var(--green-100);color:var(--green-700);">
                           <i class="fas fa-circle" style="font-size:.45rem;"></i> Active
                       </span>`;

                html += `
                <div class="visitor-row${v.is_overdue ? ' overdue-row' : ''}" onclick="viewCurrentVisitorDetails(${v.id})">
                    <div class="visitor-cell">
                        <div class="visitor-name">${esc(v.first_name)} ${esc(v.last_name)}</div>
                        <div class="visitor-phone" style="font-family:'DM Mono',monospace;">${esc(v.visit_pass)}</div>
                    </div>
                    <div class="visitor-cell">
                        <div style="font-weight:600;font-size:.84rem;">${esc(v.phone) || '—'}</div>
                        ${v.email ? `<div class="visitor-phone">${esc(v.email)}</div>` : ''}
                    </div>
                    <div class="visitor-cell">
                        <div class="purpose-text" title="${esc(v.purpose || 'Not specified')}">${esc(v.purpose || '—')}</div>
                        ${v.person_to_visit ? `<div class="visitor-phone"><i class="fas fa-user-tie" style="opacity:.5;"></i> ${esc(v.person_to_visit)}</div>` : ''}
                    </div>
                    <div class="visitor-cell">${groupBadge}</div>
                    <div class="visitor-cell">
                        <div class="datetime-text">${checkInFmt}</div>
                    </div>
                    <div class="visitor-cell">
                        <div style="font-family:'DM Mono',monospace;font-size:.82rem;font-weight:600;color:${v.is_overdue ? 'var(--accent-red)' : 'var(--gray-800)'};">${duration}</div>
                    </div>
                    <div class="visitor-cell">${statusBadge}</div>
                </div>`;
            });

            $('#currentVisitorsList').html(html);
        }

        /* ── CHECKED-OUT VISITORS ── */
        function refreshCheckedOutVisitors(silent = false) {
            if (!silent) {
                $('#checkedOutHeader').addClass('d-none');
                $('#checkedOutList').html(loadingHTML('checked-out visitors'));
            }
            $.ajax({
                url: 'admin-visitor-checkin.php?action=get_checked_out_visitors&limit=50',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    if (data.success) {
                        displayCheckedOutVisitors(data.visitors);
                    } else {
                        $('#checkedOutList').html(errorHTML(data.message));
                    }
                },
                error: function() {
                    $('#checkedOutList').html(errorHTML('Failed to load checked-out visitors. Please refresh.'));
                }
            });
        }

        function displayCheckedOutVisitors(visitors) {
            const count = visitors ? visitors.length : 0;
            $('#checkedOutCount').text(count ? count + ' record' + (count !== 1 ? 's' : '') : '');

            if (!count) {
                $('#checkedOutHeader').addClass('d-none');
                $('#checkedOutList').html(`
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-door-open"></i></div>
                        <div class="empty-title">No Check-Outs Yet</div>
                        <div class="empty-sub">Visitors who have checked out will appear here.</div>
                    </div>`);
                return;
            }

            $('#checkedOutHeader').removeClass('d-none');

            let html = '';
            visitors.forEach(function(v) {
                const groupBadge = v.is_group_visit
                    ? `<span class="group-badge group-yes"><i class="fas fa-users"></i> ${v.group_size || '?'}</span>`
                    : `<span class="group-badge group-no"><i class="fas fa-user"></i> Solo</span>`;

                const checkInFmt  = new Date(v.check_in_time).toLocaleString('en-US', {
                    month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true
                });
                const checkOutFmt = v.check_out_time
                    ? new Date(v.check_out_time).toLocaleString('en-US', {
                        month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true
                      })
                    : '—';
                const duration = v.visit_duration_minutes != null
                    ? formatDuration(v.visit_duration_minutes)
                    : '—';

                html += `
                <div class="checkout-row">
                    <div class="visitor-cell">
                        <div class="visitor-name">${esc(v.first_name)} ${esc(v.last_name)}</div>
                        <div class="visitor-phone" style="font-family:'DM Mono',monospace;">${esc(v.visit_pass)}</div>
                    </div>
                    <div class="visitor-cell">
                        <div style="font-weight:600;font-size:.84rem;">${esc(v.phone) || '—'}</div>
                    </div>
                    <div class="visitor-cell">
                        <div class="purpose-text" title="${esc(v.purpose || 'Not specified')}">${esc(v.purpose || '—')}</div>
                        ${v.person_to_visit ? `<div class="visitor-phone"><i class="fas fa-user-tie" style="opacity:.5;"></i> ${esc(v.person_to_visit)}</div>` : ''}
                    </div>
                    <div class="visitor-cell">${groupBadge}</div>
                    <div class="visitor-cell">
                        <div class="datetime-text">${checkInFmt}</div>
                    </div>
                    <div class="visitor-cell">
                        <div class="datetime-text">${checkOutFmt}</div>
                    </div>
                    <div class="visitor-cell">
                        <div style="font-family:'DM Mono',monospace;font-size:.82rem;font-weight:600;color:var(--gray-600);">${duration}</div>
                    </div>
                </div>`;
            });

            $('#checkedOutList').html(html);
        }

        function viewCurrentVisitorDetails(visitId) {
            const v = currentVisitorsData[visitId];
            if (!v) { showAlert('danger', 'Visitor data not found. Please refresh.'); return; }
            populateModalWithVisitorData(v, true);
            showModal();
        }

        function openBlacklistModal(visitorId, firstName, lastName, phone, email) {
            $('#blVisitorId').val(visitorId);
            $('#blFirstName').val(firstName);
            $('#blLastName').val(lastName);
            $('#blPhone').val(phone);
            $('#blEmail').val(email);
            $('#blIdNumber').val('');
            $('#blReason').val('');
            $('#blSeverity').val('medium');
            $('#blIsPermanent').prop('checked', false);
            $('#blExpiryDate').val('');
            $('#expiryDateWrap').show();

            const modal = document.getElementById('blacklistModal');
            modal.style.display = 'block';
            modal.classList.add('show', 'fade');
            modal.setAttribute('aria-modal', 'true');
            modal.setAttribute('role', 'dialog');
            modal.removeAttribute('aria-hidden');

            if (!document.querySelector('.modal-backdrop')) {
                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                backdrop.style.zIndex = '1040';
                document.body.appendChild(backdrop);
                backdrop.onclick = hideBlacklistModal;
            }
            document.body.classList.add('modal-open');
        }

        function hideBlacklistModal() {
            const modal = document.getElementById('blacklistModal');
            if (modal) {
                modal.style.display = 'none';
                modal.classList.remove('show', 'fade');
                modal.setAttribute('aria-hidden', 'true');
                modal.removeAttribute('aria-modal');
                modal.removeAttribute('role');
            }
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');
        }

        function toggleExpiryField() {
            $('#expiryDateWrap').toggle(!$('#blIsPermanent').is(':checked'));
        }

        function submitBlacklist() {
            const form = document.getElementById('blacklistForm');
            if (!form.reportValidity()) return;

            const btn = $('#blacklistModal .btn-vm-blocklist');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Adding…');

            $.ajax({
                url: 'admin-visitor-checkin.php',
                method: 'POST',
                dataType: 'json',
                data: $('#blacklistForm').serialize() + '&action=add_to_blacklist',
                success: function(res) {
                    btn.prop('disabled', false).html('<i class="fas fa-ban"></i> Add to Blocklist');
                    if (res.success) {
                        hideBlacklistModal();
                        showAlert('success', res.message);
                    } else {
                        showAlert('danger', res.message);
                    }
                },
                error: function() {
                    btn.prop('disabled', false).html('<i class="fas fa-ban"></i> Add to Blocklist');
                    showAlert('danger', 'Failed to add to blocklist. Please try again.');
                }
            });
        }

        /* ── MODAL FUNCTIONS ── */
        function viewRegisteredVisitorDetails(visitorId) {
            $.ajax({
                url: `admin-visitor-checkin.php?action=get_visitor_details&visitor_id=${visitorId}`,
                method: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.visitor) {
                        populateModalWithVisitorData(response.visitor);
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

        function populateModalWithVisitorData(visitor, isCurrentVisit = false) {
            const fullName = `${visitor.first_name} ${visitor.last_name}`;
            const initials = (visitor.first_name?.[0] || '') + (visitor.last_name?.[0] || '');

            // ── profile strip ──
            $('#vmProfileName').text(fullName);

            // build meta chips
            const chips = [];
            if (visitor.phone)   chips.push(`<span class="vm-profile-chip"><i class="fas fa-phone"></i> ${esc(visitor.phone)}</span>`);
            if (visitor.address) chips.push(`<span class="vm-profile-chip"><i class="fas fa-map-marker-alt"></i> ${esc(visitor.address)}</span>`);
            $('#vmProfileSub').html(chips.join('') || '<span class="vm-profile-chip">No contact info</span>');

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

            // ── blocklist footer button (use visitor_id for current visits, id for registered) ──
            const realVisitorId = isCurrentVisit ? visitor.visitor_id : visitor.id;
            currentViewedVisitorId = realVisitorId;
            $('#modalBlocklistBtn').show().off('click').on('click', function() {
                hideModal();
                openBlacklistModal(realVisitorId, visitor.first_name, visitor.last_name, visitor.phone || '', visitor.email || '');
            });

            // ── status + time ──
            if (isCurrentVisit) {
                $('#vmPassPill').text(visitor.visit_pass || '—');
                $('#modalVisitorPass').text(visitor.visit_pass || '—');

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

                if (visitor.duration_minutes > 0) {
                    $('#vmDurationText').text(formatDurationLong(visitor.duration_minutes));
                    $('#vmDurationHero').show();
                } else {
                    $('#vmDurationHero').hide();
                }
            } else {
                // registered visitor — show last visit data if one exists
                $('#vmPassPill').text(visitor.visit_pass || '—');
                $('#modalVisitorPass').text(visitor.visit_pass || '—');

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
                
                backdrop.onclick = function() {
                    hideModal();
                };
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
            
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(backdrop => backdrop.remove());
            
            document.body.classList.remove('modal-open');
        }

        /* ── HELPERS ── */
        function formatFullDateTime(dt) {
            return new Date(dt).toLocaleString('en-US', {
                month: 'short', day: 'numeric', year: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            });
        }

        function formatDuration(mins) {
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
            return `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> ${msg}
                </div>`;
        }

        function esc(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#visitorModal').hasClass('show')) {
                hideModal();
            }
        });
    </script>
    
    <!-- Chat Widget -->
    <?php include '../includes/chat-widget.php'; ?>
</body>
</html>
