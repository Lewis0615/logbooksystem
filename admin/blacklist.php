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
                         id_number, reason, severity, is_permanent, expiry_date, reported_by, approved_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [
                            $visitor_id > 0 ? $visitor_id : null,
                            $first_name, $last_name, $phone, $email, $id_number,
                            $reason, $severity, $is_permanent, $expiry_date,
                            $_SESSION['user_id'], $_SESSION['user_id']
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
    <title>Blacklist Management - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/vendor/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/datatables.min.css" rel="stylesheet">
    <style>
        :root {
            /* Modern Green Palette */
            --primary: #069668;
            --primary-dark: #047857;
            --primary-light: #10b981;
            --primary-bg: #ecfdf5;
            --primary-hover: #059669;
            
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
        }

        * {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
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

        .content-section {
            margin-bottom: 1.5rem;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
        }

        .stats-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .stats-card .card-body {
            padding: 1.5rem;
            position: relative;
        }

        .stats-icon-bg {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .stats-number {
            font-family: 'Poppins', sans-serif;
            font-size: 2.25rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.5rem;
            color: var(--gray-900);
        }

        .stats-label {
            color: var(--gray-600);
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .content-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }

        .content-card:hover {
            box-shadow: var(--shadow-md);
        }

        .content-card .card-header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            border-radius: 12px 12px 0 0;
        }

        .blacklist-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }

        .blacklist-card:hover {
            box-shadow: var(--shadow-md);
        }

        .blacklist-card .card-header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            border-bottom: 2px solid var(--border-color);
            color: var(--gray-700);
            font-weight: 600;
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1rem 0.75rem;
            background: var(--gray-50);
        }

        .table tbody td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
            color: var(--gray-900);
            font-size: 0.875rem;
        }

        .table tbody tr {
            border-bottom: 1px solid var(--gray-100);
            transition: background-color 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: var(--gray-50);
        }

        .badge {
            padding: 0.375rem 0.75rem;
            font-weight: 600;
            font-size: 0.75rem;
            border-radius: 6px;
        }

        .severity-high { border-left: 4px solid var(--danger); }
        .severity-medium { border-left: 4px solid var(--warning); }
        .severity-low { border-left: 4px solid var(--info); }
        .status-active { background: rgba(239, 68, 68, 0.05) !important; }
        .status-inactive { background: var(--gray-50) !important; opacity: 0.7; }

        .btn-primary {
            background: var(--primary);
            border: none;
            border-radius: 8px;
            padding: 0.625rem 1.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-light {
            background: white;
            border: 1px solid var(--border-color);
            color: var(--gray-700);
            border-radius: 8px;
            padding: 0.625rem 1.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .btn-light:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
            color: var(--gray-900);
        }

        .btn-sm {
            padding: 0.4rem 0.75rem;
            font-size: 0.8125rem;
            border-radius: 6px;
        }

        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .btn-outline-success {
            color: var(--success);
            border-color: var(--success);
        }

        .btn-outline-success:hover {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }

        .btn-outline-warning {
            color: var(--warning);
            border-color: var(--warning);
        }

        .btn-outline-warning:hover {    
            background: var(--warning);
            border-color: var(--warning);
            color: white;
        }

        .btn-secondary {
            background: var(--gray-500);
            border: none;
            color: white;
            border-radius: 8px;
        }

        .btn-secondary:hover {
            background: var(--gray-600);
        }

        .btn-danger {
            background: var(--danger);
            border: none;
            color: white;
            border-radius: 8px;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-warning {
            background: var(--warning);
            border: none;
            color: white;
            border-radius: 8px;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 0.625rem 0.875rem;
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(6, 150, 104, 0.1);
            outline: none;
        }

        .form-label {
            color: var(--gray-700);
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            border-radius: 12px 12px 0 0;
        }

        .modal-header.bg-danger {
            background: var(--danger) !important;
            color: white;
        }

        .modal-header.bg-warning {
            background: var(--warning) !important;
            color: white;
        }

        .modal-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
            background: var(--gray-50);
        }

        .alert {
            border-radius: 12px;
            border: none;
            border-left: 4px solid;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background: #fef2f2;
            border-left-color: var(--danger);
            color: #991b1b;
        }

        .alert-success {
            background: #f0fdf4;
            border-left-color: var(--success);
            color: #166534;
        }

        h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }

        .empty-state i {
            color: var(--success);
            margin-bottom: 1rem;
        }

        .empty-state h5 {
            color: var(--gray-600);
            margin-bottom: 0.5rem;
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
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="mb-0">
                        <i class="fas fa-ban me-2"></i>Blacklist Management
                    </h2>
                    <p class="mb-0 subtitle">Manage visitor access restrictions and security alerts</p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#addBlacklistModal">
                        <i class="fas fa-plus me-2"></i>Add to Blacklist
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
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Blacklist Statistics -->
        <section class="content-section">
        <div class="stats-grid">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="stats-icon-bg" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
                        <i class="fas fa-ban"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($active_count); ?></div>
                    <div class="stats-label">Active Blacklist</div>
                </div>
            </div>
            <div class="card stats-card">
                <div class="card-body">
                    <div class="stats-icon-bg" style="background: rgba(245, 158, 11, 0.1); color: var(--warning);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($expired_count); ?></div>
                    <div class="stats-label">Expired Entries</div>
                </div>
            </div>
            <div class="card stats-card">
                <div class="card-body">
                    <div class="stats-icon-bg" style="background: rgba(59, 130, 246, 0.1); color: var(--info);">
                        <i class="fas fa-list"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format(count($blacklist_entries)); ?></div>
                    <div class="stats-label">Total Entries</div>
                </div>
            </div>
        </div>
        </section>
        
        <!-- Blacklist Entries -->
        <section class="content-section">
        <div class="card blacklist-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-shield-alt me-2"></i>Blacklist Entries
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
</body>
</html>