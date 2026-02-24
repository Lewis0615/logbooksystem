<?php
/**
 * Visitor History & Management Module
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
$search_term = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Get visitor history
try {
    $sql = "SELECT v.*, vis.first_name, vis.last_name, vis.phone, vis.email, vis.company_organization,
                   CONCAT(u_in.first_name, ' ', u_in.last_name) as checked_in_by_name,
                   CONCAT(u_out.first_name, ' ', u_out.last_name) as checked_out_by_name
            FROM visits v
            JOIN visitors vis ON v.visitor_id = vis.id
            LEFT JOIN users u_in ON v.checked_in_by = u_in.id
            LEFT JOIN users u_out ON v.checked_out_by = u_out.id
            WHERE DATE(v.check_in_time) BETWEEN ? AND ?";
    
    $params = [$date_from, $date_to];
    
    if (!empty($search_term)) {
        $sql .= " AND (vis.first_name LIKE ? OR vis.last_name LIKE ? OR vis.phone LIKE ? OR v.visit_pass LIKE ?)";
        $search_param = "%$search_term%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }
    
    $sql .= " ORDER BY v.check_in_time DESC LIMIT 500";
    
    $visits = $db->fetchAll($sql, $params);
    
    // Get summary statistics
    $stats = $db->fetch("
        SELECT 
            COUNT(*) as total_visits,
            COUNT(CASE WHEN status = 'checked_out' THEN 1 END) as completed_visits,
            COUNT(CASE WHEN status = 'checked_in' THEN 1 END) as current_visits,
            COUNT(CASE WHEN status = 'overstayed' OR 
                         (status = 'checked_in' AND NOW() > expected_checkout_time) THEN 1 END) as overstayed_visits
        FROM visits v
        WHERE DATE(v.check_in_time) BETWEEN ? AND ?
    ", [$date_from, $date_to]);
    
} catch (Exception $e) {
    $error_message = 'Error loading visitor data: ' . $e->getMessage();
    $visits = [];
    $stats = ['total_visits' => 0, 'completed_visits' => 0, 'current_visits' => 0, 'overstayed_visits' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor History - <?php echo APP_NAME; ?></title>
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
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

        .history-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .history-card:hover {
            box-shadow: var(--shadow-md);
        }

        .history-card .card-header {
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

        .status-checked_in { color: var(--success); }
        .status-checked_out { color: var(--gray-500); }
        .status-overstayed { color: var(--danger); }

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

        .form-control {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 0.625rem 0.875rem;
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }

        .form-control:focus {
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

        h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: var(--primary);
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
                grid-template-columns: repeat(2, 1fr);
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
                        <i class="fas fa-history me-2"></i>Visitor History
                    </h2>
                    <p class="mb-0 subtitle">View and manage visitor records</p>
                </div>
                <div class="col-auto">
                    <a href="checkin.php" class="btn btn-light">
                        <i class="fas fa-plus me-2"></i>New Check-In
                    </a>
                </div>
            </div>
        </div>
        
        <div class="container-fluid">
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Summary Statistics -->
        <section class="content-section">
        <div class="stats-grid">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="stats-icon-bg" style="background: rgba(6, 150, 104, 0.1); color: var(--primary);">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($stats['total_visits']); ?></div>
                    <div class="stats-label">Total Visits</div>
                </div>
            </div>
            <div class="card stats-card">
                <div class="card-body">
                    <div class="stats-icon-bg" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($stats['completed_visits']); ?></div>
                    <div class="stats-label">Completed</div>
                </div>
            </div>
            <div class="card stats-card">
                <div class="card-body">
                    <div class="stats-icon-bg" style="background: rgba(59, 130, 246, 0.1); color: var(--info);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($stats['current_visits']); ?></div>
                    <div class="stats-label">Currently Inside</div>
                </div>
            </div>
            <div class="card stats-card">
                <div class="card-body">
                    <div class="stats-icon-bg" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($stats['overstayed_visits']); ?></div>
                    <div class="stats-label">Overstayed</div>
                </div>
            </div>
        </div>
        </section>
        
        <!-- Search and Filter -->
        <section class="content-section">
        <div class="card history-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Visits</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" 
                               value="<?php echo htmlspecialchars($search_term); ?>"
                               placeholder="Name, phone, or pass number">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date From</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date To</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Search
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        </section>
        
        <!-- Visitor Records -->
        <section class="content-section">
        <div class="card history-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Visit Records 
                    <span class="badge bg-primary ms-2"><?php echo count($visits); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($visits)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-3">No visit records found for the selected date range.</p>
                        <a href="checkin.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Check In New Visitor
                        </a>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="visitsTable">
                        <thead>
                            <tr>
                                <th>Pass</th>
                                <th>Visitor</th>
                                <th>Contact</th>
                                <th>Company</th>
                                <th>Purpose</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($visits as $visit): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($visit['visit_pass']); ?></strong></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($visit['first_name'] . ' ' . $visit['last_name']); ?></strong>
                                        <?php if ($visit['person_to_visit']): ?>
                                            <br><small class="text-muted">Visiting: <?php echo htmlspecialchars($visit['person_to_visit']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($visit['phone']); ?>
                                        <?php if ($visit['email']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($visit['email']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($visit['company_organization'] ?? '—'); ?></td>
                                    <td><?php echo htmlspecialchars($visit['purpose'] ?? '—'); ?></td>
                                    <td>
                                        <?php echo formatDisplayDateTime($visit['check_in_time']); ?>
                                        <?php if ($visit['checked_in_by_name']): ?>
                                            <br><small class="text-muted">by <?php echo htmlspecialchars($visit['checked_in_by_name']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($visit['check_out_time']): ?>
                                            <?php echo formatDisplayDateTime($visit['check_out_time']); ?>
                                            <?php if ($visit['checked_out_by_name']): ?>
                                                <br><small class="text-muted">by <?php echo htmlspecialchars($visit['checked_out_by_name']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($visit['actual_duration']): ?>
                                            <?php
                                            $hours = floor($visit['actual_duration'] / 60);
                                            $minutes = $visit['actual_duration'] % 60;
                                            echo "{$hours}h {$minutes}m";
                                            ?>
                                        <?php elseif ($visit['status'] === 'checked_in'): ?>
                                            <?php
                                            $current_duration = round((time() - strtotime($visit['check_in_time'])) / 60);
                                            $hours = floor($current_duration / 60);
                                            $minutes = $current_duration % 60;
                                            echo "{$hours}h {$minutes}m";
                                            ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $visit['status'] === 'checked_in' ? 'success' : 
                                                ($visit['status'] === 'checked_out' ? 'secondary' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $visit['status'])); ?>
                                        </span>
                                        <?php if ($visit['status'] === 'checked_in' && strtotime($visit['expected_checkout_time']) < time()): ?>
                                            <br><span class="badge bg-warning">Overstay</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        </section>
        </div>
    </div>
    
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/datatables.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable only if table exists
            if ($('#visitsTable').length) {
                $('#visitsTable').DataTable({
                    order: [[5, 'desc']], // Sort by check-in time descending
                    pageLength: 25,
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search records..."
                    }
                });
            }
        });
    </script>
</body>
</html>