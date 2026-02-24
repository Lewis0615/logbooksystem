<?php
/**
 * Visitor Check-Out Module
 * St. Dominic Savio College - Visitor Management System
 */

require_once '../config/config.php';
require_once '../config/auth.php';

// Check authentication
$auth->requireLogin('login.php');

$error_message = '';
$success_message = '';

// Handle checkout submission
if ($_POST && isset($_POST['checkout_visitor'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $visit_id = (int)($_POST['visit_id'] ?? 0);
    
    if (!validateCSRFToken($csrf_token)) {
        $error_message = 'Security token validation failed.';
    } elseif ($visit_id <= 0) {
        $error_message = 'Invalid visit ID.';
    } else {
        try {
            // Get visit details
            $visit = $db->fetch(
                "SELECT v.*, vis.first_name, vis.last_name 
                 FROM visits v 
                 JOIN visitors vis ON v.visitor_id = vis.id 
                 WHERE v.id = ? AND v.status = 'checked_in'",
                [$visit_id]
            );
            
            if (!$visit) {
                $error_message = 'Visit not found or already checked out.';
            } else {
                // Calculate actual duration
                $checkin_time = strtotime($visit['check_in_time']);
                $checkout_time = time();
                $actual_duration = round(($checkout_time - $checkin_time) / 60);
                
                // Update visit record
                $db->execute(
                    "UPDATE visits SET status = 'checked_out', check_out_time = NOW(), 
                     actual_duration = ?, checked_out_by = ? WHERE id = ?",
                    [$actual_duration, $_SESSION['user_id'], $visit_id]
                );
                
                $auth->logActivity($_SESSION['user_id'], 'VISITOR_CHECKOUT', 
                    "Checked out visitor: {$visit['first_name']} {$visit['last_name']} - Duration: {$actual_duration} minutes");
                
                $success_message = "Visitor {$visit['first_name']} {$visit['last_name']} checked out successfully!";
            }
        } catch (Exception $e) {
            $error_message = 'Error checking out visitor: ' . $e->getMessage();
        }
    }
}

// Get currently checked-in visitors
try {
    $checked_in_visitors = $db->fetchAll("
        SELECT v.id, v.visit_pass, v.check_in_time, v.expected_checkout_time,
               vis.first_name, vis.last_name, vis.phone, vis.company_organization,
               v.person_to_visit, v.purpose,
               TIMESTAMPDIFF(MINUTE, v.check_in_time, NOW()) as duration_minutes,
               CASE WHEN NOW() > v.expected_checkout_time THEN 1 ELSE 0 END as is_overstay
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.id
        WHERE v.status = 'checked_in'
        ORDER BY v.check_in_time ASC
    ");
    
    // Get recently checked-out visitors (today)
    $checked_out_visitors = $db->fetchAll("
        SELECT v.id, v.visit_pass, v.check_in_time, v.check_out_time,
               vis.first_name, vis.last_name, vis.phone, vis.company_organization,
               v.person_to_visit, v.purpose, v.actual_duration,
               CONCAT(u_out.first_name, ' ', u_out.last_name) as checked_out_by_name
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.id
        LEFT JOIN users u_out ON v.checked_out_by = u_out.id
        WHERE v.status = 'checked_out' 
        AND DATE(v.check_out_time) = CURDATE()
        ORDER BY v.check_out_time DESC
    ");
} catch (Exception $e) {
    $error_message = 'Error loading visitor data: ' . $e->getMessage();
    $checked_in_visitors = [];
    $checked_out_visitors = [];
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Check-Out - <?php echo APP_NAME; ?></title>
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

        .checkout-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .checkout-card:hover {
            box-shadow: var(--shadow-md);
        }

        .checkout-card .card-header {
            background: white;
            color: var(--gray-900);
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }

        .checkout-card .card-header h5 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            margin: 0;
            font-size: 1rem;
            color: var(--gray-900);
        }

        .visitor-row { 
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .visitor-row:hover { 
            background: var(--gray-50);
        }

        .overstay { 
            background: #fef2f2;
            border-left: 3px solid var(--danger);
        }

        .overstay:hover {
            background: #fee2e2;
        }

        .btn-success {
            background: var(--success);
            border: none;
            border-radius: 8px;
            padding: 0.625rem 1.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .btn-success:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline-danger {
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.8125rem;
            transition: all 0.2s ease;
            border: 1px solid var(--border-color);
            color: var(--danger);
        }

        .btn-outline-danger:hover {
            background: var(--danger);
            border-color: var(--danger);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .alert {
            border-radius: 10px;
            border: 1px solid var(--border-color);
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background: #fef2f2;
            border-left: 3px solid var(--danger);
            color: var(--gray-900);
        }

        .alert-success {
            background: #ecfdf5;
            border-left: 3px solid var(--success);
            color: var(--gray-900);
        }

        .badge {
            border-radius: 6px;
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .table {
            font-size: 0.875rem;
        }

        .table thead th {
            font-weight: 600;
            color: var(--gray-700);
            border-bottom: 2px solid var(--border-color);
            padding: 0.75rem;
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .table tbody td {
            padding: 0.875rem 0.75rem;
            vertical-align: middle;
            border-bottom: 1px solid var(--border-color);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
            color: var(--gray-400);
        }

        .empty-state p {
            color: var(--gray-500);
            margin-bottom: 1.5rem;
        }

        h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .page-header h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php 
    // Include appropriate sidebar based on user role
    if ($auth->hasRole(ROLE_GUARD)) {
        include '../includes/guard-sidebar.php';
    } else {
        include '../includes/admin-sidebar.php';
    }
    ?>
    
    <div class="main-content">
        <div class="page-header">
            <div class="container-fluid">
                <h2 class="mb-0">
                    <i class="fas fa-user-minus me-2"></i>Visitor Check-Out
                </h2>
                <p class="mb-0 subtitle">Monitor and process visitor departures</p>
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
        
        <!-- Currently Inside Section -->
        <div class="card checkout-card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-user-check me-2"></i>Currently Inside Campus 
                    <span class="badge bg-success ms-2"><?php echo count($checked_in_visitors); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($checked_in_visitors)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users fa-3x"></i>
                        <p>No visitors currently checked in</p>
                        <a href="checkin.php" class="btn btn-success">
                            <i class="fas fa-user-plus me-2"></i>Check In New Visitor
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="checkedInTable">
                            <thead>
                                <tr>
                                    <th>Pass</th>
                                    <th>Visitor</th>
                                    <th>Company</th>
                                    <th>Person to Visit</th>
                                    <th>Purpose</th>
                                    <th>Check-In Time</th>
                                    <th>Duration</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($checked_in_visitors as $visitor): ?>
                                    <tr class="visitor-row <?php echo $visitor['is_overstay'] ? 'overstay' : ''; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($visitor['visit_pass']); ?></strong>
                                            <?php if ($visitor['is_overstay']): ?>
                                                <span class="badge bg-danger ms-1">Overstay</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($visitor['phone']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($visitor['company_organization'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($visitor['person_to_visit'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($visitor['purpose'] ?? '—'); ?></td>
                                        <td><?php echo formatDisplayDateTime($visitor['check_in_time']); ?></td>
                                        <td>
                                            <?php
                                            $hours = floor($visitor['duration_minutes'] / 60);
                                            $minutes = $visitor['duration_minutes'] % 60;
                                            echo "{$hours}h {$minutes}m";
                                            ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                <input type="hidden" name="visit_id" value="<?php echo $visitor['id']; ?>">
                                                <button type="submit" name="checkout_visitor" class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Check out <?php echo htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']); ?>?')">
                                                    <i class="fas fa-sign-out-alt me-1"></i>Check Out
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recently Checked Out Section -->
        <div class="card checkout-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-user-clock me-2"></i>Checked Out Today 
                    <span class="badge bg-secondary ms-2"><?php echo count($checked_out_visitors); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($checked_out_visitors)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox fa-3x"></i>
                        <p>No visitors have checked out today</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover" id="checkedOutTable">
                            <thead>
                                <tr>
                                    <th>Pass</th>
                                    <th>Visitor</th>
                                    <th>Company</th>
                                    <th>Person Visited</th>
                                    <th>Purpose</th>
                                    <th>Check-In Time</th>
                                    <th>Check-Out Time</th>
                                    <th>Duration</th>
                                    <th>Processed By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($checked_out_visitors as $visitor): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($visitor['visit_pass']); ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($visitor['phone']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($visitor['company_organization'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($visitor['person_to_visit'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($visitor['purpose'] ?? '—'); ?></td>
                                        <td><?php echo formatDisplayDateTime($visitor['check_in_time']); ?></td>
                                        <td>
                                            <span class="badge bg-success">
                                                <?php echo formatDisplayDateTime($visitor['check_out_time']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            if ($visitor['actual_duration']) {
                                                $hours = floor($visitor['actual_duration'] / 60);
                                                $minutes = $visitor['actual_duration'] % 60;
                                                echo "{$hours}h {$minutes}m";
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($visitor['checked_out_by_name'] ?? '—'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        </div>
    </div>
    
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/datatables.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable for currently checked-in visitors
            $('#checkedInTable').DataTable({
                order: [[5, 'asc']], // Sort by check-in time
                pageLength: 25
            });
            
            // Initialize DataTable for checked-out visitors
            $('#checkedOutTable').DataTable({
                order: [[6, 'desc']], // Sort by check-out time (most recent first)
                pageLength: 25
            });
        });
    </script>
</body>
</html>