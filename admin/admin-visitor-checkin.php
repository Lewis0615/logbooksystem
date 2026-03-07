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
                vis.address, vis.id_type,
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
            vis.address, vis.id_type,
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
    <title>Visitor Check In/Out</title>
    <link rel="icon" type="image/png" href="/logbooksystem/assets/images/sdsclogo.png">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/vendor/font-awesome.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin-visitor-checkin.css">

</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>

    <div class="main-content">
        
        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="page-header-inner">
                <div>
                    <h2><i class="fas fa-users-slash"></i> Visitor Check In/Out</h2>
                    <p class="subtitle">Monitor on-campus visitors and manage the blocklist</p>
                </div>
                <div class="admin-badge"><i class="fas fa-shield-alt"></i> Admin Control</div>
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
                            <input type="text" id="visitorSearch" class="search-input" placeholder="Search by name, phone, or company…">
                        </div>
                        <div class="tbl-head registered d-none" id="registeredVisitorsHeader">
                            <div>Visitor</div><div>Phone</div><div>Address</div><div>Last Visit</div><div>Action</div>
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
                            <span class="card-header-icon" style="background:var(--green-100);color:var(--green-600);"><i class="fas fa-user-check"></i></span>
                            Currently On-Campus
                            <span id="currentVisitorCount" class="ms-2" style="font-family:'Space Mono',monospace;font-size:.75rem;font-weight:600;background:var(--green-100);color:var(--green-700);padding:2px 9px;border-radius:20px;"></span>
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
                            <div>Visitor Name</div><div>Contact</div><div>Purpose</div><div>Group</div><div>Check-In</div><div>Duration</div><div>Status</div>
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
                            <span id="checkedOutCount" class="ms-2" style="font-family:'Space Mono',monospace;font-size:.75rem;font-weight:600;background:var(--accent-orange-lt);color:var(--accent-orange);padding:2px 9px;border-radius:20px;"></span>
                        </h5>
                        <div class="card-header-actions">
                            <span style="font-size:.72rem;color:var(--gray-400);margin-right:6px;">Most recent 50 check-outs</span>
                            <button class="btn-refresh" onclick="refreshCheckedOutVisitors()"><i class="fas fa-sync-alt"></i> Refresh</button>
                        </div>
                    </div>
                    <div class="card-body-scroll" style="max-height:420px;">
                        <div class="tbl-head checkout d-none" id="checkedOutHeader">
                            <div>Visitor Name</div><div>Contact</div><div>Purpose</div><div>Group</div><div>Check-In</div><div>Check-Out</div><div>Duration</div>
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
        </div>
    </div><!-- /main-content -->

    <!-- REFRESH TOAST -->
    <div id="refreshToast" class="refresh-toast"><i class="fas fa-sync-alt me-1"></i> Updated</div>

    <!-- ADD TO BLOCKLIST MODAL -->
    <div class="modal fade" id="blacklistModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;">
                <div class="bl-modal-header">
                    <h5><i class="fas fa-ban me-2"></i>Add Visitor to Blocklist</h5>
                    <p>Complete the form below to add this visitor to the blocklist.</p>
                    <button type="button" class="vm-close-btn" style="position:absolute;top:18px;right:18px;" onclick="hideBlacklistModal()"><i class="fas fa-times"></i></button>
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
                                <textarea id="blReason" name="reason" class="form-control-sm-custom" rows="3" placeholder="Describe the reason for adding to blocklist…" required></textarea>
                            </div>
                            <div class="col-md-6">
                                <div style="display:flex;align-items:center;gap:10px;margin-top:4px;">
                                    <input type="checkbox" id="blIsPermanent" name="is_permanent" style="width:16px;height:16px;accent-color:var(--accent-red);cursor:pointer;" onchange="toggleExpiryField()">
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
                    <button type="button" class="btn-vm btn-ghost" onclick="hideBlacklistModal()"><i class="fas fa-times"></i> Cancel</button>
                    <button type="button" class="btn-vm btn-danger" onclick="submitBlacklist()"><i class="fas fa-ban"></i> Add to Blocklist</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════
         VISITOR DETAILS MODAL
    ══════════════════════════════════════════ -->
    <div class="modal fade" id="visitorModal" tabindex="-1" aria-labelledby="visitorModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">

                <!-- HEADER -->
                <div class="modal-header">
                    <div class="header-top">
                        <div>
                            <p class="visitor-label">Visitor Record</p>
                            <h2 class="visitor-name-information" id="vmProfileName">—</h2>
                            <div class="visitor-meta" id="vmProfileSub"></div>
                        </div>
                        <div class="header-right">
                            <button type="button" class="close-btn" onclick="hideModal()" aria-label="Close">
                                <i class="fas fa-times"></i>
                            </button>
                            <div class="pass-badge">
                                Visit Pass <span class="pass-num" id="vmPassPill">—</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STATUS BAR -->
                <div class="status-bar">
                    <div id="modalStatus">—</div>
                    <span class="visit-pass-label">Pass #<span id="modalVisitorPass">—</span></span>
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
                                    <p class="info-value" id="modalVisitorName">—</p>
                                </div>
                            </div>
                            <div class="info-card">
                                <div class="info-icon icon-green"><i class="fas fa-phone"></i></div>
                                <div class="info-text">
                                    <p class="info-label">Phone Number</p>
                                    <p class="info-value" id="modalPhone">—</p>
                                </div>
                            </div>
                            <div class="info-card" style="grid-column:1/-1;">
                                <div class="info-icon icon-amber"><i class="fas fa-envelope"></i></div>
                                <div class="info-text">
                                    <p class="info-label">Email Address</p>
                                    <p class="info-value" id="modalEmail">—</p>
                                </div>
                            </div>
                            <div class="info-card" style="grid-column:1/-1;">
                                <div class="info-icon icon-red"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="info-text">
                                    <p class="info-label">Address</p>
                                    <p class="info-value" style="white-space:normal;overflow:visible;text-overflow:unset;" id="modalAddress">—</p>
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
                                    <p class="info-value" id="modalIdType">—</p>
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
                                    <p id="modalDepartment">—</p>
                                </div>
                                <div class="visit-field">
                                    <label>Person to Visit</label>
                                    <p id="modalPersonToVisit">—</p>
                                </div>
                            </div>
                            <div class="visit-row" style="margin-bottom:0;">
                                <div class="visit-field">
                                    <label>Visit Purpose</label>
                                    <div id="modalPurpose">—</div>
                                </div>
                                <div class="visit-field">
                                    <label>Group Status</label>
                                    <div id="modalGroupStatus">—</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Group Members -->
                    <div id="groupMembersRow" style="display:none;">
                        <p class="section-label">Group Members</p>
                        <div class="visit-card">
                            <p style="font-size:13.5px;color:#374151;line-height:1.7;margin:0;" id="modalGroupMembers">—</p>
                        </div>
                    </div>

                    <!-- Time Tracking -->
                    <div>
                        <p class="section-label">Time Tracking</p>
                        <div class="time-grid">
                            <div class="time-tile tile-checkin">
                                <div class="tile-icon"><i class="fas fa-sign-in-alt"></i></div>
                                <p class="tile-label">Check-In</p>
                                <p class="tile-value" id="modalCheckInTime">—</p>
                                <p class="tile-sub" id="modalCheckInSub">—</p>
                            </div>
                            <div class="time-tile tile-checkout">
                                <div class="tile-icon"><i class="fas fa-sign-out-alt"></i></div>
                                <p class="tile-label">Check-Out</p>
                                <p class="tile-value" id="modalCheckOutTime">—</p>
                                <p class="tile-sub" id="modalCheckOutSub">—</p>
                            </div>
                            <div class="time-tile tile-duration">
                                <div class="tile-icon"><i class="fas fa-hourglass-half"></i></div>
                                <p class="tile-label">Duration</p>
                                <p class="tile-value" id="modalDuration">—</p>
                                <p class="tile-sub">On campus</p>
                            </div>
                        </div>
                        <div class="total-time-bar" id="vmDurationHero" style="display:none;">
                            <span class="total-label">Total Time on Campus</span>
                            <span class="total-value" id="vmDurationText">—</span>
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
                        <button type="button" class="btn-vm btn-danger" id="modalBlocklistBtn" style="display:none;">
                            <i class="fas fa-ban"></i> Add to Blocklist
                        </button>
                    </div>
                </div>

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
        const initialRegisteredVisitors = <?php echo json_encode($initial_registered_visitors ?: []); ?>;
        const initialCurrentVisitors    = <?php echo json_encode($initial_current_visitors     ?: []); ?>;
        const initialCheckedOutVisitors = <?php echo json_encode($initial_checked_out_visitors ?: []); ?>;

        function waitForJQuery(callback) {
            if (typeof jQuery !== 'undefined') { callback(); }
            else { setTimeout(function() { waitForJQuery(callback); }, 100); }
        }

        waitForJQuery(function() {
            $(document).ready(function () {
                $('#visitorModal').hide().removeClass('show');
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open');

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
                method: 'GET', dataType: 'json',
                success: function(data) {
                    data.success ? displayRegisteredVisitors(data.visitors) : $('#registeredVisitorsList').html(errorHTML(data.message));
                },
                error: function() { $('#registeredVisitorsList').html(errorHTML('Failed to load registered visitors. Please refresh.')); }
            });
        }

        function displayRegisteredVisitors(visitors) {
            if (!visitors.length) {
                $('#registeredVisitorsHeader').addClass('d-none');
                $('#registeredVisitorsList').html(`<div class="empty-state"><div class="empty-icon"><i class="fas fa-user-friends"></i></div><div class="empty-title">No Registered Visitors</div><div class="empty-sub">No visitors have registered in the system yet.</div></div>`);
                return;
            }
            $('#registeredVisitorsHeader').removeClass('d-none');
            let html = '';
            visitors.forEach(function(v) {
                const lastVisit = v.last_visit ? new Date(v.last_visit).toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}) : 'Never';
                const actionButtons = `<div style="display:flex;flex-direction:column;gap:6px;">
                    <button class="btn-view-outline" onclick="viewRegisteredVisitorDetails(${v.id})"><i class="fas fa-eye"></i> View</button>
                    <button class="btn-blacklist" onclick="openBlacklistModal(${v.id},'${esc(v.first_name)}','${esc(v.last_name)}','${esc(v.phone)}','${esc(v.email||'')}')"><i class="fas fa-ban"></i> Blocklist</button>
                </div>`;
                html += `<div class="registered-visitor-item" style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr 90px;gap:12px;align-items:center;padding:12px 14px;background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm);margin-bottom:8px;transition:transform .2s,box-shadow .2s;">
                    <div><div class="rv-name">${esc(v.first_name)} ${esc(v.last_name)}</div>${v.email?`<div class="rv-meta"><i class="fas fa-envelope"></i> ${esc(v.email)}</div>`:''}</div>
                    <div style="font-size:.82rem;color:var(--gray-700);">${esc(v.phone)||'\u2014'}</div>
                    <div style="font-size:.82rem;color:var(--gray-700);">${esc(v.address)||'\u2014'}</div>
                    <div style="font-size:.78rem;color:var(--gray-500);">${lastVisit}</div>
                    <div>${actionButtons}</div>
                </div>`;
            });
            $('#registeredVisitorsList').html(html);
        }

        /* ── CURRENT VISITORS ── */
        function refreshCurrentVisitors(silent = false) {
            if (!silent) { $('#currentVisitorsHeader').addClass('d-none'); $('#currentVisitorsList').html(loadingHTML('current visitors')); }
            $.ajax({
                url: 'admin-visitor-checkin.php?action=get_current_visitors',
                method: 'GET', dataType: 'json',
                success: function(data) {
                    if (data.success) { displayCurrentVisitors(data.visitors); if (silent) showRefreshToast(); }
                    else { $('#currentVisitorsList').html(errorHTML(data.message)); }
                },
                error: function() { $('#currentVisitorsList').html(errorHTML('Failed to load current visitors. Please refresh.')); }
            });
        }

        function displayCurrentVisitors(visitors) {
            const count = visitors ? visitors.length : 0;
            // Accurate people count: sum group_size for group visits, 1 for solo
            let totalPeople = 0;
            if (visitors) visitors.forEach(function(v) {
                const isGroup = v.is_group_visit == 1 || v.is_group_visit === true || v.is_group_visit === '1';
                totalPeople += isGroup ? (parseInt(v.group_size) || 1) : 1;
            });
            $('#currentVisitorCount').text(totalPeople > 0 ? totalPeople + ' on-campus' : '');
            currentVisitorsData = {};
            if (visitors) visitors.forEach(function(v) { currentVisitorsData[v.id] = v; });
            if (!count) {
                $('#currentVisitorsHeader').addClass('d-none');
                $('#currentVisitorsList').html(`<div class="empty-state"><div class="empty-icon"><i class="fas fa-door-open"></i></div><div class="empty-title">Campus is Clear</div><div class="empty-sub">No visitors are currently checked in.</div></div>`);
                return;
            }
            $('#currentVisitorsHeader').removeClass('d-none');
            let html = '';
            const now = new Date();
            visitors.forEach(function(v) {
                const isGroup = v.is_group_visit == 1 || v.is_group_visit === true || v.is_group_visit === '1';
                const groupBadge = isGroup
                    ? `<span class="group-badge group-yes"><i class="fas fa-users"></i> ${v.group_size||'?'}</span>`
                    : `<span class="group-badge group-no"><i class="fas fa-user"></i> Solo</span>`;
                // Compute duration client-side so it's accurate at render time
                const checkInMs = new Date(v.check_in_time);
                const liveMins = Math.max(0, Math.floor((now - checkInMs) / 60000));
                const duration = formatDuration(liveMins);
                // Compute overdue client-side
                const isOverdue = v.expected_checkout_time ? (now > new Date(v.expected_checkout_time)) : false;
                const checkInFmt = checkInMs.toLocaleString('en-US',{month:'short',day:'numeric',hour:'numeric',minute:'2-digit',hour12:true});
                const statusBadge = isOverdue
                    ? `<span class="live-status" data-expected="${esc(v.expected_checkout_time||'')}" style="display:inline-flex;align-items:center;gap:5px;font-size:.72rem;font-weight:700;padding:3px 9px;border-radius:12px;background:var(--accent-red-lt);color:var(--accent-red);"><i class="fas fa-exclamation-circle"></i> Overdue</span>`
                    : `<span class="live-status" data-expected="${esc(v.expected_checkout_time||'')}" style="display:inline-flex;align-items:center;gap:5px;font-size:.72rem;font-weight:700;padding:3px 9px;border-radius:12px;background:var(--green-100);color:var(--green-700);"><i class="fas fa-circle" style="font-size:.45rem;"></i> Active</span>`;
                html += `<div class="visitor-row${isOverdue?' overdue-row':''}" onclick="viewCurrentVisitorDetails(${v.id})">
                    <div class="visitor-cell"><div class="visitor-name">${esc(v.first_name)} ${esc(v.last_name)}</div><div class="visitor-phone" style="font-family:'Space Mono',monospace;">${esc(v.visit_pass)}</div></div>
                    <div class="visitor-cell"><div style="font-weight:600;font-size:.84rem;">${esc(v.phone)||'—'}</div>${v.email?`<div class="visitor-phone">${esc(v.email)}</div>`:''}</div>
                    <div class="visitor-cell"><div class="purpose-text" title="${esc(v.purpose||'Not specified')}">${esc(v.purpose||'—')}</div>${v.person_to_visit?`<div class="visitor-phone"><i class="fas fa-user-tie" style="opacity:.5;"></i> ${esc(v.person_to_visit)}</div>`:''}</div>
                    <div class="visitor-cell">${groupBadge}</div>
                    <div class="visitor-cell"><div class="datetime-text">${checkInFmt}</div></div>
                    <div class="visitor-cell"><div class="live-duration" data-checkin="${esc(v.check_in_time)}" style="font-family:'Space Mono',monospace;font-size:.82rem;font-weight:600;color:${isOverdue?'var(--accent-red)':'var(--gray-800)'};">${duration}</div></div>
                    <div class="visitor-cell">${statusBadge}</div>
                </div>`;
            });
            $('#currentVisitorsList').html(html);
        }

        // Live-update durations and overdue statuses every 60 seconds
        setInterval(function() {
            const now = new Date();
            $('.live-duration[data-checkin]').each(function() {
                const mins = Math.max(0, Math.floor((now - new Date($(this).data('checkin'))) / 60000));
                $(this).text(formatDuration(mins));
            });
            $('.live-status[data-expected]').each(function() {
                const exp = $(this).data('expected');
                if (!exp) return;
                const isOverdue = now > new Date(exp);
                const $row = $(this).closest('.visitor-row');
                const $dur = $row.find('.live-duration');
                if (isOverdue && !$row.hasClass('overdue-row')) {
                    $row.addClass('overdue-row');
                    $dur.css('color', 'var(--accent-red)');
                    $(this).css({'background':'var(--accent-red-lt)','color':'var(--accent-red)'});
                    $(this).html('<i class="fas fa-exclamation-circle"></i> Overdue');
                } else if (!isOverdue) {
                    $dur.css('color', 'var(--gray-800)');
                }
            });
        }, 60000);

        /* ── CHECKED-OUT VISITORS ── */
        function refreshCheckedOutVisitors(silent = false) {
            if (!silent) { $('#checkedOutHeader').addClass('d-none'); $('#checkedOutList').html(loadingHTML('checked-out visitors')); }
            $.ajax({
                url: 'admin-visitor-checkin.php?action=get_checked_out_visitors&limit=50',
                method: 'GET', dataType: 'json',
                success: function(data) {
                    if (data.success) { displayCheckedOutVisitors(data.visitors); }
                    else { $('#checkedOutList').html(errorHTML(data.message)); }
                },
                error: function() { $('#checkedOutList').html(errorHTML('Failed to load checked-out visitors. Please refresh.')); }
            });
        }

        function displayCheckedOutVisitors(visitors) {
            const count = visitors ? visitors.length : 0;
            $('#checkedOutCount').text(count ? count+' record'+(count!==1?'s':'') : '');
            if (!count) {
                $('#checkedOutHeader').addClass('d-none');
                $('#checkedOutList').html(`<div class="empty-state"><div class="empty-icon"><i class="fas fa-door-open"></i></div><div class="empty-title">No Check-Outs Yet</div><div class="empty-sub">Visitors who have checked out will appear here.</div></div>`);
                return;
            }
            $('#checkedOutHeader').removeClass('d-none');
            let html = '';
            visitors.forEach(function(v) {
                const groupBadge = v.is_group_visit ? `<span class="group-badge group-yes"><i class="fas fa-users"></i> ${v.group_size||'?'}</span>` : `<span class="group-badge group-no"><i class="fas fa-user"></i> Solo</span>`;
                const checkInFmt  = new Date(v.check_in_time).toLocaleString('en-US',{month:'short',day:'numeric',hour:'numeric',minute:'2-digit',hour12:true});
                const checkOutFmt = v.check_out_time ? new Date(v.check_out_time).toLocaleString('en-US',{month:'short',day:'numeric',hour:'numeric',minute:'2-digit',hour12:true}) : '—';
                const duration = v.visit_duration_minutes != null ? formatDuration(v.visit_duration_minutes) : '—';
                html += `<div class="checkout-row">
                    <div class="visitor-cell"><div class="visitor-name">${esc(v.first_name)} ${esc(v.last_name)}</div><div class="visitor-phone" style="font-family:'Space Mono',monospace;">${esc(v.visit_pass)}</div></div>
                    <div class="visitor-cell"><div style="font-weight:600;font-size:.84rem;">${esc(v.phone)||'—'}</div></div>
                    <div class="visitor-cell"><div class="purpose-text" title="${esc(v.purpose||'Not specified')}">${esc(v.purpose||'—')}</div>${v.person_to_visit?`<div class="visitor-phone"><i class="fas fa-user-tie" style="opacity:.5;"></i> ${esc(v.person_to_visit)}</div>`:''}</div>
                    <div class="visitor-cell">${groupBadge}</div>
                    <div class="visitor-cell"><div class="datetime-text">${checkInFmt}</div></div>
                    <div class="visitor-cell"><div class="datetime-text">${checkOutFmt}</div></div>
                    <div class="visitor-cell"><div style="font-family:'Space Mono',monospace;font-size:.82rem;font-weight:600;color:var(--gray-600);">${duration}</div></div>
                </div>`;
            });
            $('#checkedOutList').html(html);
        }

        function viewCurrentVisitorDetails(visitId) {
            const v = currentVisitorsData[visitId];
            if (!v) { showAlert('danger','Visitor data not found. Please refresh.'); return; }
            populateModalWithVisitorData(v, true);
            showModal();
        }

        function openBlacklistModal(visitorId, firstName, lastName, phone, email) {
            $('#blVisitorId').val(visitorId); $('#blFirstName').val(firstName); $('#blLastName').val(lastName);
            $('#blPhone').val(phone); $('#blEmail').val(email); $('#blIdNumber').val(''); $('#blReason').val('');
            $('#blSeverity').val('medium'); $('#blIsPermanent').prop('checked', false); $('#blExpiryDate').val(''); $('#expiryDateWrap').show();
            const modal = document.getElementById('blacklistModal');
            modal.style.display = 'block'; modal.classList.add('show','fade');
            modal.setAttribute('aria-modal','true'); modal.setAttribute('role','dialog'); modal.removeAttribute('aria-hidden');
            if (!document.querySelector('.modal-backdrop')) {
                const backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show'; backdrop.style.zIndex = '1040';
                document.body.appendChild(backdrop); backdrop.onclick = hideBlacklistModal;
            }
            document.body.classList.add('modal-open');
        }

        function hideBlacklistModal() {
            const modal = document.getElementById('blacklistModal');
            if (modal) { modal.style.display='none'; modal.classList.remove('show','fade'); modal.setAttribute('aria-hidden','true'); modal.removeAttribute('aria-modal'); modal.removeAttribute('role'); }
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');
        }

        function toggleExpiryField() { $('#expiryDateWrap').toggle(!$('#blIsPermanent').is(':checked')); }

        function submitBlacklist() {
            const form = document.getElementById('blacklistForm');
            if (!form.reportValidity()) return;
            const btn = $('#blacklistModal .btn-vm.btn-danger');
            btn.prop('disabled',true).html('<i class="fas fa-spinner fa-spin"></i> Adding…');
            $.ajax({
                url:'admin-visitor-checkin.php', method:'POST', dataType:'json',
                data: $('#blacklistForm').serialize()+'&action=add_to_blacklist',
                success: function(res) {
                    btn.prop('disabled',false).html('<i class="fas fa-ban"></i> Add to Blocklist');
                    if (res.success) { hideBlacklistModal(); showAlert('success',res.message); }
                    else { showAlert('danger',res.message); }
                },
                error: function() { btn.prop('disabled',false).html('<i class="fas fa-ban"></i> Add to Blocklist'); showAlert('danger','Failed to add to blocklist. Please try again.'); }
            });
        }

        /* ── MODAL FUNCTIONS ── */
        function viewRegisteredVisitorDetails(visitorId) {
            $.ajax({
                url: `admin-visitor-checkin.php?action=get_visitor_details&visitor_id=${visitorId}`,
                method:'GET', dataType:'json',
                success: function(response) {
                    if (response.success && response.visitor) { populateModalWithVisitorData(response.visitor); showModal(); }
                    else { showAlert('danger','Failed to load visitor details.'); }
                },
                error: function() { showAlert('danger','Error loading visitor details. Please try again.'); }
            });
        }

        function populateModalWithVisitorData(visitor, isCurrentVisit = false) {
            const fullName = `${visitor.first_name} ${visitor.last_name}`;

            // Header
            $('#vmProfileName').text(fullName);

            const metaItems = [];
            if (visitor.phone)   metaItems.push(`<span class="meta-item"><i class="fas fa-phone"></i> ${esc(visitor.phone)}</span>`);
            if (visitor.address) metaItems.push(`<span class="meta-item"><i class="fas fa-map-marker-alt"></i> ${esc(visitor.address)}</span>`);
            $('#vmProfileSub').html(metaItems.join(''));

            // Pass badge
            $('#vmPassPill').text(visitor.visit_pass||'—');
            $('#modalVisitorPass').text(visitor.visit_pass||'—');

            // Personal info fields
            $('#modalVisitorName').text(fullName);
            $('#modalPhone').text(visitor.phone||'Not provided');
            $('#modalEmail').text(visitor.email||'Not provided');
            $('#modalAddress').text(visitor.address||'Not provided');
            $('#modalIdType').text(visitor.id_type||'Not provided');

            // ID photo
            if (visitor.id_photo_path) {
                const photoSrc = '../'+visitor.id_photo_path;
                $('#modalIdPhoto').attr('src', photoSrc);
                $('#modalIdPhotoLink').attr('href', photoSrc);
                $('#modalIdPhotoWrap').show();
                $('#modalIdViewBtn').show();
            } else {
                $('#modalIdPhotoWrap').hide();
                $('#modalIdViewBtn').hide();
            }

            // Visit info
            $('#modalDepartment').text(visitor.department||'Not specified');
            $('#modalPersonToVisit').text(visitor.person_to_visit||'Not specified');

            if (visitor.purpose) {
                $('#modalPurpose').html(`<span class="purpose-tag">${esc(visitor.purpose)}</span>`);
            } else {
                $('#modalPurpose').text('Not specified');
            }

            if (visitor.is_group_visit=='1'||visitor.is_group_visit===true||visitor.is_group_visit===1) {
                $('#modalGroupStatus').html(`<span class="group-tag"><i class="fas fa-users"></i> Group — ${visitor.group_size||'?'} people</span>`);
                $('#modalGroupMembers').text(visitor.group_members||'Members not specified');
                $('#groupMembersRow').show();
            } else {
                $('#modalGroupStatus').html(`<span class="group-tag"><i class="fas fa-user"></i> Individual Visit</span>`);
                $('#groupMembersRow').hide();
            }

            // Notes
            $('#modalNotes').text(visitor.additional_notes||'No additional notes provided.');

            // Blocklist button
            const realVisitorId = isCurrentVisit ? visitor.visitor_id : visitor.id;
            currentViewedVisitorId = realVisitorId;
            $('#modalBlocklistBtn').show().off('click').on('click', function() {
                hideModal();
                openBlacklistModal(realVisitorId, visitor.first_name, visitor.last_name, visitor.phone||'', visitor.email||'');
            });

            // Status + time tracking
            if (isCurrentVisit) {
                if (visitor.is_overdue) {
                    $('#modalStatus').html(`<div class="status-pill overdue"><span class="status-dot"></span> Overdue</div>`);
                } else {
                    $('#modalStatus').html(`<div class="status-pill"><span class="status-dot"></span> Currently Checked In</div>`);
                }
                const cinDate = new Date(visitor.check_in_time);
                $('#modalCheckInTime').text(cinDate.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}));
                $('#modalCheckInSub').text(cinDate.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true}));
                if (visitor.check_out_time) {
                    const coutDate = new Date(visitor.check_out_time);
                    $('#modalCheckOutTime').text(coutDate.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}));
                    $('#modalCheckOutSub').text(coutDate.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true}));
                } else {
                    $('#modalCheckOutTime').text('—'); $('#modalCheckOutSub').text('Pending');
                }
                $('#modalDuration').text(formatDuration(visitor.duration_minutes));
                if (visitor.duration_minutes>0) { $('#vmDurationText').text(formatDurationLong(visitor.duration_minutes)); $('#vmDurationHero').show(); }
                else { $('#vmDurationHero').hide(); }
            } else {
                if (!visitor.visit_pass) {
                    $('#modalStatus').html(`<div class="status-pill registered"><span class="status-dot"></span> Registered — No visits yet</div>`);
                    $('#modalCheckInTime').text('No visits yet'); $('#modalCheckInSub').text('—');
                    $('#modalCheckOutTime').text('—'); $('#modalCheckOutSub').text('—');
                    $('#modalDuration').text('—'); $('#vmDurationHero').hide();
                } else {
                    const statusLabel = visitor.visit_status==='checked_out' ? 'Last Visit: Checked Out'
                        : visitor.visit_status==='checked_in' ? 'Currently Checked In'
                        : visitor.visit_status==='no_show' ? 'Last Visit: No Show' : 'Registered';
                    const pillClass = visitor.visit_status==='checked_in' ? '' : 'registered';
                    $('#modalStatus').html(`<div class="status-pill ${pillClass}"><span class="status-dot"></span> ${statusLabel}</div>`);
                    if (visitor.check_in_time) {
                        const cinDate = new Date(visitor.check_in_time);
                        $('#modalCheckInTime').text(cinDate.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}));
                        $('#modalCheckInSub').text(cinDate.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true}));
                    } else { $('#modalCheckInTime').text('—'); $('#modalCheckInSub').text('—'); }
                    if (visitor.check_out_time) {
                        const coutDate = new Date(visitor.check_out_time);
                        $('#modalCheckOutTime').text(coutDate.toLocaleDateString('en-US',{month:'short',day:'numeric',year:'numeric'}));
                        $('#modalCheckOutSub').text(coutDate.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true}));
                    } else { $('#modalCheckOutTime').text('—'); $('#modalCheckOutSub').text('—'); }
                    $('#modalDuration').text(visitor.duration_minutes>0 ? formatDuration(visitor.duration_minutes) : '—');
                    if (visitor.duration_minutes>0) { $('#vmDurationText').text(formatDurationLong(visitor.duration_minutes)); $('#vmDurationHero').show(); }
                    else { $('#vmDurationHero').hide(); }
                }
            }
        }

        function showModal() {
            $('#visitorModal').hide().removeClass('show');
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
            const modal = document.getElementById('visitorModal');
            if (modal) {
                modal.style.display='block'; modal.classList.add('show','fade');
                modal.setAttribute('aria-modal','true'); modal.setAttribute('role','dialog'); modal.removeAttribute('aria-hidden');
                const backdrop = document.createElement('div');
                backdrop.className='modal-backdrop fade show'; backdrop.style.zIndex='1040';
                document.body.appendChild(backdrop);
                document.body.classList.add('modal-open');
                backdrop.onclick = function() { hideModal(); };
            }
        }

        function hideModal() {
            const modal = document.getElementById('visitorModal');
            if (modal) { modal.style.display='none'; modal.classList.remove('show','fade'); modal.setAttribute('aria-hidden','true'); modal.removeAttribute('aria-modal'); modal.removeAttribute('role'); }
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
            document.body.classList.remove('modal-open');
        }

        /* ── HELPERS ── */
        function formatFullDateTime(dt) { return new Date(dt).toLocaleString('en-US',{month:'short',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit',hour12:true}); }
        function formatDuration(mins) { if(mins<60) return `${mins}m`; return `${Math.floor(mins/60)}h ${mins%60}m`; }
        function formatDurationLong(mins) {
            if(!mins||mins<=0) return '—';
            const h=Math.floor(mins/60),m=mins%60;
            if(h===0) return `${m} min`;
            if(m===0) return `${h} hour${h>1?'s':''}`;
            return `${h} hour${h>1?'s':''} ${m} min`;
        }
        function showRefreshToast() { $('#refreshToast').addClass('show'); setTimeout(()=>$('#refreshToast').removeClass('show'),2200); }
        function showAlert(type, message) {
            const icon = type==='success'?'check-circle':'exclamation-triangle';
            const html = `<div class="alert alert-${type} alert-dismissible fade show"><i class="fas fa-${icon}"></i>${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
            $('.main-content').prepend(html);
            setTimeout(()=>$('.alert.fade').remove(),5000);
        }
        function loadingHTML(label) { return `<div class="empty-state"><div class="empty-icon"><i class="fas fa-spinner fa-spin"></i></div><div class="empty-title">Loading ${label}…</div><div class="empty-sub">Please wait while we fetch the data.</div></div>`; }
        function errorHTML(msg) { return `<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> ${msg}</div>`; }
        function esc(str) { if(!str) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;'); }

        $(document).on('keydown', function(e) {
            if (e.key==='Escape'&&$('#visitorModal').hasClass('show')) { hideModal(); }
        });
    </script>
    
    <?php include '../includes/chat-widget.php'; ?>
</body>
</html>