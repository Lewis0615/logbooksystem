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
    <link rel="stylesheet" href="../assets/css/guard-visitor-management.css">

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