<?php
/**
 * Blacklist Management Module
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
$success_message = '';

// Handle form submissions
if ($_POST) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $error_message = 'Security token validation failed.';
    } elseif (isset($_POST['add_blacklist'])) {
        // Add new blacklist entry
        $visitor_id = (int)($_POST['visitor_id'] ?? 0);
        $first_name = sanitizeInput($_POST['first_name'] ?? '');
        $last_name = sanitizeInput($_POST['last_name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $id_number = sanitizeInput($_POST['id_number'] ?? '');
        $reason = sanitizeInput($_POST['reason'] ?? '');
        $severity = sanitizeInput($_POST['severity'] ?? 'medium');
        $is_permanent = isset($_POST['is_permanent']) ? 1 : 0;
        $expiry_date = !$is_permanent ? ($_POST['expiry_date'] ?? null) : null;
        
        if ((empty($first_name) || empty($last_name)) && empty($phone) && empty($email)) {
            $error_message = 'Please provide at least name and phone, or email address.';
        } elseif (empty($reason)) {
            $error_message = 'Reason for blacklisting is required.';
        } else {
            try {
                // Get visitor info if visitor_id is provided
                if ($visitor_id > 0) {
                    $visitor = $db->fetch("SELECT * FROM visitors WHERE id = ?", [$visitor_id]);
                    if ($visitor) {
                        $first_name = $visitor['first_name'];
                        $last_name = $visitor['last_name'];
                        $phone = $visitor['phone'];
                        $email = $visitor['email'];
                        $id_number = $visitor['id_number'];
                    }
                }
                
                // Check if already blacklisted
                $existing = $db->fetch(
                    "SELECT id FROM blacklist WHERE 
                     (visitor_id = ? AND visitor_id > 0) OR 
                     (phone = ? AND phone != '') OR 
                     (first_name = ? AND last_name = ?) 
                     AND status = 'active'",
                    [$visitor_id, $phone, $first_name, $last_name]
                );
                
                if ($existing) {
                    $error_message = 'This person is already blacklisted.';
                } else {
                    // Add to blacklist
                    $db->execute(
                        "INSERT INTO blacklist (visitor_id, first_name, last_name, phone, email, 
                         id_number, reason, severity, is_permanent, expiry_date, status,
                         reported_by, approved_by, created_by, created_at, updated_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, ?, NOW(), NOW())",
                        [
                            $visitor_id > 0 ? $visitor_id : null,
                            $first_name, $last_name, $phone, $email, $id_number,
                            $reason, $severity, $is_permanent, $expiry_date,
                            $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']
                        ]
                    );
                    
                    $auth->logActivity(
                        $_SESSION['user_id'],
                        'BLACKLIST_ADD',
                        "Added to blacklist: $first_name $last_name",
                        ['phone' => $phone, 'reason' => $reason]
                    );
                    
                    $success_message = "$first_name $last_name has been added to the blacklist.";
                }
            } catch (Exception $e) {
                $error_message = 'Error adding to blacklist: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_blacklist'])) {
        // Update blacklist entry
        $blacklist_id = (int)($_POST['blacklist_id'] ?? 0);
        $status = sanitizeInput($_POST['status'] ?? 'active');
        $reason = sanitizeInput($_POST['reason'] ?? '');
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        if ($blacklist_id <= 0) {
            $error_message = 'Invalid blacklist entry.';
        } elseif (empty($reason)) {
            $error_message = 'Reason is required.';
        } else {
            try {
                $updated = $db->execute(
                    "UPDATE blacklist SET reason = ?, status = ?, notes = ?, updated_at = NOW(), updated_by = ?
                     WHERE id = ?",
                    [$reason, $status, $notes, $_SESSION['user_id'], $blacklist_id]
                );
                
                if ($updated) {
                    $action = $status === 'active' ? 'updated' : 'deactivated';
                    $success_message = "Blacklist entry has been $action.";
                    
                    $auth->logActivity(
                        $_SESSION['user_id'],
                        'BLACKLIST_UPDATE',
                        "$action blacklist entry ID: $blacklist_id",
                        ['status' => $status]
                    );
                } else {
                    $error_message = 'Failed to update blacklist entry.';
                }
            } catch (Exception $e) {
                $error_message = 'Error updating blacklist: ' . $e->getMessage();
            }
        }
    }
}

// Get blacklist entries
try {
    $blacklist_entries = $db->fetchAll("
        SELECT b.*, 
               CONCAT(u_reported.first_name, ' ', u_reported.last_name) as reported_by_name,
               CONCAT(u_approved.first_name, ' ', u_approved.last_name) as approved_by_name,
               CASE 
                   WHEN b.is_permanent = 1 THEN 'Permanent'
                   WHEN b.expiry_date IS NULL THEN 'No Expiry Set'
                   WHEN b.expiry_date < CURDATE() THEN 'Expired'
                   ELSE CONCAT('Expires ', DATE_FORMAT(b.expiry_date, '%M %d, %Y'))
               END as expiry_status
        FROM blacklist b
        LEFT JOIN users u_reported ON b.reported_by = u_reported.id
        LEFT JOIN users u_approved ON b.approved_by = u_approved.id
        ORDER BY b.created_at DESC
    ");
    
    // Get active blacklist count
    $active_count = $db->fetch(
        "SELECT COUNT(*) as count FROM blacklist 
         WHERE status = 'active' AND 
               (is_permanent = 1 OR expiry_date IS NULL OR expiry_date >= CURDATE())"
    )['count'];
    
    // Get expired count
    $expired_count = $db->fetch(
        "SELECT COUNT(*) as count FROM blacklist 
         WHERE status = 'active' AND is_permanent = 0 AND expiry_date < CURDATE()"
    )['count'];
    
} catch (Exception $e) {
    $error_message = 'Error loading blacklist data: ' . $e->getMessage();
    $blacklist_entries = [];
    $active_count = 0;
    $expired_count = 0;
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blocklist Management</title>
    <link rel="icon" type="image/png" href="/logbooksystem/assets/images/sdsclogo.png">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/vendor/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/datatables.min.css" rel="stylesheet">
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

        .main-content {
            margin-left: 260px;
            padding: 28px 30px;
            min-height: 100vh;
            transition: margin-left .3s ease;
        }

        .content-section {
            margin-bottom: 24px;
        }

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



        .card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all .3s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            background: var(--gray-50);
            border-bottom: 1px solid var(--border-lt);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header h5 {
            font-family: 'Work Sans', sans-serif;
            font-size: .95rem;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 0;
        }

        .card-body {
            padding: 20px 22px;
        }

        .table {
            margin-bottom: 0;
        }

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

        .table tbody tr {
            transition: background-color .2s ease;
        }

        .table tbody tr:hover {
            background-color: var(--gray-50);
        }

        .badge {
            padding: 5px 11px;
            font-weight: 600;
            font-size: .75rem;
            border-radius: var(--radius-sm);
        }

        .severity-high { border-left: 4px solid var(--accent-red); }
        .severity-medium { border-left: 4px solid var(--accent-orange); }
        .severity-low { border-left: 4px solid var(--accent-blue); }
        .status-active { background: rgba(239, 68, 68, 0.05) !important; }
        .status-inactive { background: var(--gray-50) !important; opacity: 0.7; }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent-blue), #60a5fa);
            border: none;
            border-radius: var(--radius-sm);
            padding: 9px 18px;
            font-weight: 600;
            font-size: .875rem;
            color: #fff;
            box-shadow: 0 2px 8px rgba(59,130,246,.28);
            transition: all .2s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(59,130,246,.38);
        }

        .btn-light {
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: var(--radius-sm);
            padding: 9px 18px;
            font-weight: 600;
            font-size: .875rem;
            color: #fff;
            backdrop-filter: blur(4px);
            transition: all .2s ease;
        }

        .btn-light:hover {
            background: rgba(255,255,255,.25);
            color: #fff;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: .8rem;
            border-radius: var(--radius-xs);
        }

        .btn-outline-primary {
            color: var(--accent-blue);
            border-color: var(--accent-blue);
        }

        .btn-outline-primary:hover {
            background: var(--accent-blue);
            border-color: var(--accent-blue);
            color: white;
        }

        .btn-outline-success {
            color: var(--green-500);
            border-color: var(--green-500);
        }

        .btn-outline-success:hover {
            background: var(--green-500);
            border-color: var(--green-500);
            color: white;
        }

        .btn-outline-warning {
            color: var(--accent-orange);
            border-color: var(--accent-orange);
        }

        .btn-outline-warning:hover {
            background: var(--accent-orange);
            border-color: var(--accent-orange);
            color: white;
        }

        .btn-secondary {
            background: var(--gray-500);
            border: none;
            color: white;
            border-radius: var(--radius-sm);
        }

        .btn-secondary:hover {
            background: var(--gray-600);
        }

        .btn-danger {
            background: var(--accent-red);
            border: none;
            color: white;
            border-radius: var(--radius-sm);
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-warning {
            background: var(--accent-orange);
            border: none;
            color: white;
            border-radius: var(--radius-sm);
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .form-control, .form-select {
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            padding: 9px 12px;
            transition: all .2s ease;
            font-size: .875rem;
            font-family: 'DM Sans', sans-serif;
            color: var(--gray-800);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59,130,246,.12);
            outline: none;
        }

        .form-label {
            color: var(--gray-700);
            font-weight: 600;
            font-size: .875rem;
            margin-bottom: 8px;
        }

        .modal-content {
            border: none;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            border-bottom: 1px solid var(--border);
            padding: 16px 20px;
            border-radius: var(--radius) var(--radius) 0 0;
        }

        .modal-header.bg-danger {
            background: var(--accent-red) !important;
            color: white;
        }

        .modal-header.bg-warning {
            background: var(--accent-orange) !important;
            color: white;
        }

        .modal-title {
            font-family: 'Work Sans', sans-serif;
            font-weight: 600;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            border-top: 1px solid var(--border);
            padding: 14px 20px;
            background: var(--gray-50);
        }

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

        .alert-danger {
            background: var(--accent-red-lt);
            color: #991b1b;
            border-left: 3px solid var(--accent-red);
        }

        .alert-success {
            background: #ecfdf5;
            color: #166534;
            border-left: 3px solid var(--green-500);
        }

        h5, h6 {
            font-family: 'Work Sans', sans-serif;
            font-weight: 700;
        }

        .empty-state {
            text-align: center;
            padding: 48px 16px;
        }

        .empty-state i {
            color: var(--accent-blue);
            margin-bottom: 16px;
        }

        .empty-state h5 {
            color: var(--gray-600);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--gray-500);
        }

        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .stats-grid {
                gap: 1rem;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem;
            }

            .page-header h2 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .stats-number {
                font-size: 1.875rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <div class="page-header-inner">
                <div>
                    <h2>
                        <i class="fas fa-ban"></i>
                        Blocklist Management
                    </h2>
                    <p class="subtitle">Manage visitor access restrictions and security alerts</p>
                </div>
                <div>
                    <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addBlacklistModal">
                        <i class="fas fa-plus me-2"></i>Add to Blocklist
                    </button>
                </div>
            </div>
        </div>
        
        <div class="container-fluid">
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i><?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Blacklist Entries -->
        <section class="content-section">
        <div class="card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-shield-alt"></i>Blocklist Entries
                    <span class="badge bg-danger ms-2"><?php echo count($blacklist_entries); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($blacklist_entries): ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="blacklistTable">
                            <thead>
                                <tr>
                                    <th>Person</th>
                                    <th>Contact</th>
                                    <th>Reason</th>
                                    <th>Severity</th>
                                    <th>Status</th>
                                    <th>Expiry</th>
                                    <th>Added By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($blacklist_entries as $entry): ?>
                                    <tr class="severity-<?php echo $entry['severity']; ?> <?php echo $entry['status'] === 'active' ? 'status-active' : 'status-inactive'; ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($entry['first_name'] . ' ' . $entry['last_name']); ?></strong>
                                                    <?php if ($entry['id_number']): ?>
                                                        <br><small class="text-muted"><i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($entry['id_number']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($entry['phone']): ?>
                                                <div class="mb-1"><i class="fas fa-phone me-1 text-muted"></i><small><?php echo htmlspecialchars($entry['phone']); ?></small></div>
                                            <?php endif; ?>
                                            <?php if ($entry['email']): ?>
                                                <div><i class="fas fa-envelope me-1 text-muted"></i><small><?php echo htmlspecialchars($entry['email']); ?></small></div>
                                            <?php endif; ?>
                                            <?php if (!$entry['phone'] && !$entry['email']): ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="text-wrap" style="max-width: 200px;">
                                                <?php echo htmlspecialchars($entry['reason']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $entry['severity'] === 'high' ? 'danger' : 
                                                    ($entry['severity'] === 'medium' ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo ucfirst($entry['severity']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $entry['status'] === 'active' ? 'danger' : 'secondary'; ?>">
                                                <?php echo ucfirst($entry['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="<?php echo strpos($entry['expiry_status'], 'Expired') !== false ? 'text-warning' : 'text-muted'; ?>">
                                                <?php echo $entry['expiry_status']; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($entry['reported_by_name']); ?><br>
                                                <?php echo formatDisplayDate($entry['created_at']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" 
                                                        onclick="editBlacklistEntry(<?php echo htmlspecialchars(json_encode($entry)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($entry['status'] === 'active'): ?>
                                                    <button class="btn btn-outline-success" 
                                                            onclick="updateBlacklistStatus(<?php echo $entry['id']; ?>, 'inactive')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-warning" 
                                                            onclick="updateBlacklistStatus(<?php echo $entry['id']; ?>, 'active')">
                                                        <i class="fas fa-undo"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-shield-alt fa-3x"></i>
                        <h5>No Blacklist Entries</h5>
                        <p class="text-muted">No visitors are currently blacklisted.</p>
                        <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addBlacklistModal">
                            <i class="fas fa-plus me-2"></i>Add First Entry
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        </section>
        </div>
    </div>
    
    <!-- Add to Blacklist Modal -->
    <div class="modal fade" id="addBlacklistModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-ban me-2"></i>Add to Blacklist
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="visitor_id" id="selected_visitor_id">
                    
                    <div class="modal-body">
                        <!-- Visitor Search -->
                        <div class="mb-3">
                            <label class="form-label">Search Existing Visitor (Optional)</label>
                            <input type="text" class="form-control" id="visitor_search" 
                                   placeholder="Search by name, phone, or email...">
                            <div id="visitor_results" class="mt-2"></div>
                        </div>
                        
                        <hr>
                        
                        <!-- Manual Entry -->
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" id="first_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" id="last_name" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" name="phone" id="phone">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" name="email" id="email">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">ID Number</label>
                            <input type="text" class="form-control" name="id_number" id="id_number">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason for Blacklisting *</label>
                            <textarea class="form-control" name="reason" rows="3" 
                                      placeholder="Provide detailed reason..." required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Severity Level</label>
                                <select class="form-select" name="severity">
                                    <option value="low">Low - Minor Issues</option>
                                    <option value="medium" selected>Medium - Significant Concerns</option>
                                    <option value="high">High - Security Threat</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Expiry Date</label>
                                <input type="date" class="form-control" name="expiry_date" id="expiry_date">
                            </div>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_permanent" 
                                   id="is_permanent" onchange="toggleExpiry()">
                            <label class="form-check-label" for="is_permanent">
                                <strong>Permanent Blacklist</strong> - No expiration date
                            </label>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_blacklist" class="btn btn-danger">
                            <i class="fas fa-ban me-2"></i>Add to Blacklist
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Blacklist Modal -->
    <div class="modal fade" id="editBlacklistModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Blacklist Entry
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editBlacklistForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="blacklist_id" id="edit_blacklist_id">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="edit_status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea class="form-control" name="reason" id="edit_reason" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Additional Notes</label>
                            <textarea class="form-control" name="notes" id="edit_notes" rows="2" 
                                      placeholder="Internal notes or updates..."></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_blacklist" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>Update Entry
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/datatables.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable only if table exists
            if ($('#blacklistTable').length) {
                $('#blacklistTable').DataTable({
                    order: [[6, 'desc']], // Sort by date added
                    pageLength: 25,
                    responsive: true,
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search blacklist..."
                    }
                });
            }
            
            // Visitor search
            let searchTimeout;
            $('#visitor_search').on('input', function() {
                const query = $(this).val().trim();
                
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    if (query.length >= 2) {
                        searchVisitors(query);
                    } else {
                        $('#visitor_results').empty();
                    }
                }, 300);
            });
        });
        
        function toggleExpiry() {
            const isPermanent = document.getElementById('is_permanent').checked;
            document.getElementById('expiry_date').disabled = isPermanent;
            if (isPermanent) {
                document.getElementById('expiry_date').value = '';
            }
        }
        
        function searchVisitors(query) {
            $.ajax({
                url: '../ajax/admin-ajax.php',
                type: 'POST',
                data: {
                    action: 'search_visitors',
                    search_term: query
                },
                success: function(response) {
                    if (response.success) {
                        let html = '';
                        response.visitors.forEach(function(visitor) {
                            html += `
                                <div class="border rounded p-2 mb-2 cursor-pointer" 
                                     onclick="selectVisitor(${visitor.id}, '${visitor.first_name}', '${visitor.last_name}', '${visitor.phone}', '${visitor.email}')">
                                    <strong>${visitor.first_name} ${visitor.last_name}</strong><br>
                                    <small class="text-muted">${visitor.phone} ${visitor.email ? '• ' + visitor.email : ''}</small>
                                </div>
                            `;
                        });
                        $('#visitor_results').html(html);
                    }
                },
                error: function() {
                    $('#visitor_results').html('<div class="text-danger">Search error occurred</div>');
                }
            });
        }
        
        function selectVisitor(id, firstName, lastName, phone, email) {
            document.getElementById('selected_visitor_id').value = id;
            document.getElementById('first_name').value = firstName;
            document.getElementById('last_name').value = lastName;
            document.getElementById('phone').value = phone;
            document.getElementById('email').value = email || '';
            
            $('#visitor_results').empty();
            $('#visitor_search').val(firstName + ' ' + lastName);
        }
        
        function editBlacklistEntry(entry) {
            document.getElementById('edit_blacklist_id').value = entry.id;
            document.getElementById('edit_status').value = entry.status;
            document.getElementById('edit_reason').value = entry.reason;
            document.getElementById('edit_notes').value = entry.notes || '';
            
            var modal = new bootstrap.Modal(document.getElementById('editBlacklistModal'));
            modal.show();
        }
        
        function updateBlacklistStatus(id, status) {
            if (confirm('Are you sure you want to ' + (status === 'active' ? 'activate' : 'deactivate') + ' this blacklist entry?')) {
                // Create a form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="blacklist_id" value="${id}">
                    <input type="hidden" name="status" value="${status}">
                    <input type="hidden" name="reason" value="Status updated">
                    <input type="hidden" name="update_blacklist" value="1">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
    
    <?php include '../includes/chat-widget.php'; ?>
</body>
</html>