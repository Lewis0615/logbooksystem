<?php
/**
 * Reports Module
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
$report_data = [];
$report_type = $_GET['type'] ?? 'daily';
$date_from = $_GET['date_from'] ?? date('Y-m-d');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$export_format = $_GET['export'] ?? null;

// Generate report based on type
try {
    switch ($report_type) {
        case 'daily':
            $report_data = $db->fetchAll("
                SELECT 
                    v.visitor_pass,
                    CONCAT(vis.first_name, ' ', vis.last_name) as visitor_name,
                    vis.phone,
                    vis.company_organization,
                    vp.purpose_name,
                    o.office_name,
                    v.person_to_visit,
                    v.check_in_time,
                    v.check_out_time,
                    v.actual_duration,
                    v.status,
                    CONCAT(u_in.first_name, ' ', u_in.last_name) as checked_in_by,
                    CONCAT(u_out.first_name, ' ', u_out.last_name) as checked_out_by
                FROM visits v
                JOIN visitors vis ON v.visitor_id = vis.id
                LEFT JOIN visit_purposes vp ON v.purpose_id = vp.id
                LEFT JOIN offices o ON v.office_id = o.id
                LEFT JOIN users u_in ON v.checked_in_by = u_in.id
                LEFT JOIN users u_out ON v.checked_out_by = u_out.id
                WHERE DATE(v.check_in_time) BETWEEN ? AND ?
                ORDER BY v.check_in_time DESC
            ", [$date_from, $date_to]);
            
            $report_title = 'Daily Visits Report';
            break;
            
        case 'summary':
            $report_data = $db->fetchAll("
                SELECT 
                    DATE(check_in_time) as visit_date,
                    COUNT(*) as total_visits,
                    COUNT(CASE WHEN status = 'checked_out' THEN 1 END) as completed_visits,
                    COUNT(CASE WHEN status = 'checked_in' THEN 1 END) as still_inside,
                    COUNT(CASE WHEN status = 'overstayed' THEN 1 END) as overstayed,
                    AVG(CASE WHEN actual_duration IS NOT NULL THEN actual_duration END) as avg_duration
                FROM visits
                WHERE DATE(check_in_time) BETWEEN ? AND ?
                GROUP BY DATE(check_in_time)
                ORDER BY visit_date DESC
            ", [$date_from, $date_to]);
            
            $report_title = 'Summary Report';
            break;
            
        case 'visitor_frequency':
            $report_data = $db->fetchAll("
                SELECT 
                    CONCAT(vis.first_name, ' ', vis.last_name) as visitor_name,
                    vis.phone,
                    vis.company_organization,
                    COUNT(*) as visit_count,
                    MIN(v.check_in_time) as first_visit,
                    MAX(v.check_in_time) as last_visit,
                    AVG(CASE WHEN v.actual_duration IS NOT NULL THEN v.actual_duration END) as avg_duration
                FROM visits v
                JOIN visitors vis ON v.visitor_id = vis.id
                WHERE DATE(v.check_in_time) BETWEEN ? AND ?
                GROUP BY v.visitor_id, vis.first_name, vis.last_name, vis.phone, vis.company_organization
                HAVING visit_count > 1
                ORDER BY visit_count DESC
            ", [$date_from, $date_to]);
            
            $report_title = 'Frequent Visitor Report';
            break;
            
        case 'purpose_analysis':
            $report_data = $db->fetchAll("
                SELECT 
                    vp.purpose_name,
                    COUNT(*) as visit_count,
                    AVG(CASE WHEN v.actual_duration IS NOT NULL THEN v.actual_duration END) as avg_duration,
                    COUNT(CASE WHEN v.status = 'checked_out' THEN 1 END) as completed_count,
                    ROUND((COUNT(CASE WHEN v.status = 'checked_out' THEN 1 END) / COUNT(*)) * 100, 2) as completion_rate
                FROM visits v
                JOIN visit_purposes vp ON v.purpose_id = vp.id
                WHERE DATE(v.check_in_time) BETWEEN ? AND ?
                GROUP BY v.purpose_id, vp.purpose_name
                ORDER BY visit_count DESC
            ", [$date_from, $date_to]);
            
            $report_title = 'Visit Purpose Analysis';
            break;
            
        case 'office_analysis':
            $report_data = $db->fetchAll("
                SELECT 
                    o.office_name,
                    COUNT(*) as visit_count,
                    AVG(CASE WHEN v.actual_duration IS NOT NULL THEN v.actual_duration END) as avg_duration,
                    COUNT(DISTINCT v.visitor_id) as unique_visitors,
                    COUNT(CASE WHEN NOW() > v.expected_checkout_time AND v.status = 'checked_in' THEN 1 END) as overstay_count
                FROM visits v
                JOIN offices o ON v.office_id = o.id
                WHERE DATE(v.check_in_time) BETWEEN ? AND ?
                GROUP BY v.office_id, o.office_name
                ORDER BY visit_count DESC
            ", [$date_from, $date_to]);
            
            $report_title = 'Office Visit Analysis';
            break;
            
        case 'security':
            $report_data = $db->fetchAll("
                SELECT 
                    'Overstayed Visits' as alert_type,
                    COUNT(*) as count,
                    'warning' as severity
                FROM visits
                WHERE status = 'checked_in' AND NOW() > expected_checkout_time
                    AND DATE(check_in_time) BETWEEN ? AND ?
                UNION ALL
                SELECT 
                    'Blacklist Violations' as alert_type,
                    COUNT(*) as count,
                    'danger' as severity
                FROM activity_logs
                WHERE action = 'BLACKLIST_ATTEMPT'
                    AND DATE(created_at) BETWEEN ? AND ?
                UNION ALL
                SELECT 
                    'System Errors' as alert_type,
                    COUNT(*) as count,
                    'info' as severity
                FROM activity_logs
                WHERE action LIKE '%ERROR%'
                    AND DATE(created_at) BETWEEN ? AND ?
            ", [$date_from, $date_to, $date_from, $date_to, $date_from, $date_to]);
            
            $report_title = 'Security & Alerts Report';
            break;
            
        default:
            $error_message = 'Invalid report type selected.';
            break;
    }
    
    // Handle export
    if ($export_format && $report_data) {
        $filename = $report_type . '_report_' . date('Y-m-d_H-i-s');
        
        if ($export_format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            
            $output = fopen('php://output', 'w');
            
            // Write header
            if ($report_data) {
                fputcsv($output, array_keys($report_data[0]));
                foreach ($report_data as $row) {
                    fputcsv($output, $row);
                }
            }
            
            fclose($output);
            exit();
        } elseif ($export_format === 'excel') {
            // Simple Excel export (can be enhanced with proper Excel library)
            header('Content-Type: application/vnd.ms-excel');
            header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
            
            echo '<table border="1">';
            if ($report_data) {
                echo '<tr>';
                foreach (array_keys($report_data[0]) as $header) {
                    echo '<th>' . htmlspecialchars($header) . '</th>';
                }
                echo '</tr>';
                
                foreach ($report_data as $row) {
                    echo '<tr>';
                    foreach ($row as $cell) {
                        echo '<td>' . htmlspecialchars($cell) . '</td>';
                    }
                    echo '</tr>';
                }
            }
            echo '</table>';
            exit();
        }
    }
    
} catch (Exception $e) {
    $error_message = 'Error generating report: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - <?php echo APP_NAME; ?></title>
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

        .content-card, .report-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }

        .content-card:hover, .report-card:hover {
            box-shadow: var(--shadow-md);
        }

        .content-card .card-header, .report-card .card-header {
            background: white;
            color: var(--gray-900);
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            border-radius: 12px 12px 0 0;
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

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(0, 0, 0, 0.02);
        }

        .badge {
            padding: 0.375rem 0.75rem;
            font-weight: 600;
            font-size: 0.75rem;
            border-radius: 6px;
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

        .btn-report, .btn-primary {
            background: var(--primary);
            border: none;
            border-radius: 8px;
            padding: 0.625rem 1.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            color: white;
        }

        .btn-report:hover, .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
            color: white;
        }

        .btn-sm {
            padding: 0.4rem 0.75rem;
            font-size: 0.8125rem;
            border-radius: 6px;
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

        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .btn-outline-secondary {
            color: var(--gray-600);
            border-color: var(--gray-400);
        }

        .btn-outline-secondary:hover {
            background: var(--gray-500);
            border-color: var(--gray-500);
            color: white;
        }

        .btn-secondary {
            background: var(--gray-500);
            border: none;
            color: white;
            border-radius: 8px;
            padding: 0.625rem 1.5rem;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .btn-secondary:hover {
            background: var(--gray-600);
        }

        h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
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

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }

        .empty-state i {
            color: var(--gray-400);
            margin-bottom: 1rem;
        }

        .empty-state h5 {
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }

        .empty-state p {
            color: var(--gray-500);
        }

        .report-info {
            background: var(--gray-50);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem;
            }

            .page-header h2 {
                font-size: 1.5rem;
            }

            .btn-group {
                flex-direction: column;
                width: 100%;
            }

            .btn-group .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h2 class="mb-0">
                <i class="fas fa-chart-pie me-2"></i>Reports & Analytics
            </h2>
            <p class="mb-0 subtitle">Generate comprehensive visitor reports and analytics</p>
        </div>
        
        <div class="container-fluid">
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Report Filters -->
        <section class="content-section">
        <div class="card report-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Report Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" name="type">
                            <option value="daily" <?php echo $report_type === 'daily' ? 'selected' : ''; ?>>Daily Visits</option>
                            <option value="summary" <?php echo $report_type === 'summary' ? 'selected' : ''; ?>>Summary Report</option>
                            <option value="visitor_frequency" <?php echo $report_type === 'visitor_frequency' ? 'selected' : ''; ?>>Frequent Visitors</option>
                            <option value="purpose_analysis" <?php echo $report_type === 'purpose_analysis' ? 'selected' : ''; ?>>Purpose Analysis</option>
                            <option value="office_analysis" <?php echo $report_type === 'office_analysis' ? 'selected' : ''; ?>>Office Analysis</option>
                            <option value="security" <?php echo $report_type === 'security' ? 'selected' : ''; ?>>Security Report</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date From</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date To</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-report">
                                <i class="fas fa-search me-2"></i>Generate Report
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        </section>
        
        <!-- Report Results -->
        <?php if ($report_data): ?>
            <section class="content-section">
            <div class="card report-card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">
                        <i class="fas fa-file-alt me-2"></i><?php echo $report_title; ?>
                        <span class="badge bg-primary ms-2"><?php echo count($report_data); ?></span>
                    </h5>
                    <div class="btn-group" role="group">
                        <a href="?type=<?php echo $report_type; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&export=csv" 
                           class="btn btn-sm btn-outline-success">
                            <i class="fas fa-file-csv me-1"></i>CSV
                        </a>
                        <a href="?type=<?php echo $report_type; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&export=excel" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-file-excel me-1"></i>Excel
                        </a>
                        <button class="btn btn-sm btn-outline-secondary" onclick="printReport()">
                            <i class="fas fa-print me-1"></i>Print
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="report-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Report Period:</strong> <?php echo formatDisplayDate($date_from); ?> to <?php echo formatDisplayDate($date_to); ?> • 
                        <strong>Total Records:</strong> <?php echo count($report_data); ?> • 
                        <strong>Generated:</strong> <?php echo formatDisplayDateTime(getCurrentDateTime()); ?>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover" id="reportTable">
                            <thead>
                                <tr>
                                    <?php if ($report_data): ?>
                                        <?php foreach (array_keys($report_data[0]) as $header): ?>
                                            <th><?php echo ucwords(str_replace('_', ' ', $header)); ?></th>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($report_data as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $key => $cell): ?>
                                            <td>
                                                <?php
                                                // Format specific columns
                                                if (strpos($key, 'time') !== false || strpos($key, 'date') !== false) {
                                                    echo $cell ? formatDisplayDateTime($cell) : '--';
                                                } elseif (strpos($key, 'duration') !== false && is_numeric($cell)) {
                                                    $hours = floor($cell / 60);
                                                    $minutes = round($cell % 60);
                                                    echo $hours . 'h ' . $minutes . 'm';
                                                } elseif (strpos($key, 'rate') !== false && is_numeric($cell)) {
                                                    echo number_format($cell, 2) . '%';
                                                } elseif (is_numeric($cell)) {
                                                    echo number_format($cell);
                                                } else {
                                                    echo htmlspecialchars($cell ?: '--');
                                                }
                                                ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            </section>
        <?php elseif (!$error_message): ?>
            <section class="content-section">
            <div class="card report-card">
                <div class="card-body">
                    <div class="empty-state">
                        <i class="fas fa-chart-line fa-3x"></i>
                        <h5>Select Report Parameters</h5>
                        <p>Choose a report type and date range above to generate comprehensive analytics.</p>
                    </div>
                </div>
            </div>
            </section>
        <?php endif; ?>
        </div>
    </div>
    
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/datatables.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable only if table exists
            if ($('#reportTable').length) {
                $('#reportTable').DataTable({
                    pageLength: 50,
                    order: [[0, 'desc']],
                    responsive: true,
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search report..."
                    }
                });
            }
        });
        
        function printReport() {
            window.print();
        }
        
        // Print styles
        const style = document.createElement('style');
        style.innerHTML = `
            @media print {
                .no-print, .btn, .page-header::before { display: none !important; }
                .card { border: 1px solid #ddd !important; box-shadow: none !important; }
                .page-header { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%) !important; -webkit-print-color-adjust: exact; }
                .main-content { margin-left: 0 !important; }
                .table thead th { background: var(--gray-50) !important; -webkit-print-color-adjust: exact; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>