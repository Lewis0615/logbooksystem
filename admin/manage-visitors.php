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

// Get visitor history
try {
    $visits = $db->fetchAll("
        SELECT v.*, vis.first_name, vis.last_name, vis.phone, vis.email, vis.company_organization, vis.address,
               CONCAT(u_in.first_name, ' ', u_in.last_name) as checked_in_by_name,
               CONCAT(u_out.first_name, ' ', u_out.last_name) as checked_out_by_name
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.id
        LEFT JOIN users u_in ON v.checked_in_by = u_in.id
        LEFT JOIN users u_out ON v.checked_out_by = u_out.id
        ORDER BY v.check_in_time DESC
        LIMIT 500
    ");
    
} catch (Exception $e) {
    $error_message = 'Error loading visitor data: ' . $e->getMessage();
    $visits = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor History - <?php echo APP_NAME; ?></title>
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
            font-family: 'Syne', sans-serif;
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

        .table {
            margin-bottom: 0;
            width: 100% !important;
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

        /* Rows hidden by filter */
        .table tbody tr.filter-hidden {
            display: none;
        }

        .badge {
            padding: 5px 11px;
            font-weight: 600;
            font-size: .75rem;
            border-radius: var(--radius-sm);
        }

        .status-checked_in { color: var(--success); }
        .status-checked_out { color: var(--gray-500); }
        .status-overstayed { color: var(--danger); }

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

        .form-control {
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            padding: 10px 12px;
            transition: all .2s ease;
            font-size: .875rem;
            font-family: 'DM Sans', sans-serif;
            color: var(--gray-800);
            background: #fff;
            height: 42px;
        }

        .form-control:hover {
            border-color: var(--gray-300);
            box-shadow: var(--shadow-xs);
        }

        .form-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59,130,246,.12);
            outline: none;
            background: #fff;
        }

        .form-label {
            color: var(--gray-700);
            font-weight: 600;
            font-size: .8rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-label i {
            font-size: .75rem;
        }

        h5, h6 {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
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

        /* ─────────────────────────────────────────────────────
           Filter Panel
        ───────────────────────────────────────────────────── */
        .filter-panel {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            margin-bottom: 20px;
            overflow: hidden;
            transition: box-shadow .3s ease;
        }

        .filter-panel:hover {
            box-shadow: var(--shadow-md);
        }

        .filter-header {
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            user-select: none;
            background: var(--gray-50);
            border-bottom: 1px solid transparent;
            transition: border-color .2s;
        }

        .filter-header.is-open {
            border-bottom-color: var(--border-lt);
        }

        .filter-header-left {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-header h5 {
            font-family: 'Syne', sans-serif;
            font-size: .92rem;
            font-weight: 700;
            color: var(--gray-800);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-header h5 i {
            color: var(--accent-blue);
        }

        .filter-toggle-icon {
            width: 28px;
            height: 28px;
            border-radius: var(--radius-xs);
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-500);
            font-size: .75rem;
            transition: all .2s ease;
            flex-shrink: 0;
        }

        .filter-header:hover .filter-toggle-icon {
            background: var(--accent-blue-lt);
            color: var(--accent-blue);
        }

        .filter-toggle-icon i {
            transition: transform .3s ease;
        }

        .filter-toggle-icon.rotated i {
            transform: rotate(180deg);
        }

        .filter-body {
            padding: 20px 22px;
            display: none;
        }

        .filter-body.is-open {
            display: block;
            animation: filterSlideDown .2s ease;
        }

        @keyframes filterSlideDown {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 16px;
        }

        .filter-grid-dates {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .filter-form-label {
            color: var(--gray-600);
            font-weight: 600;
            font-size: .75rem;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 5px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .filter-form-label i {
            font-size: .7rem;
            color: var(--accent-blue);
        }

        .filter-control {
            border-radius: var(--radius-sm);
            border: 1px solid var(--border);
            padding: 9px 12px;
            transition: all .2s ease;
            font-size: .875rem;
            font-family: 'DM Sans', sans-serif;
            color: var(--gray-800);
            background: #fff;
            height: 40px;
            width: 100%;
        }

        .filter-control:hover { border-color: var(--gray-300); }

        .filter-control:focus {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(59,130,246,.12);
            outline: none;
        }

        .filter-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 14px;
            border-top: 1px solid var(--border-lt);
            gap: 12px;
            flex-wrap: wrap;
        }

        .filter-result-count {
            font-size: .82rem;
            color: var(--gray-500);
            font-weight: 500;
        }

        .filter-result-count strong {
            color: var(--accent-blue);
        }

        .filter-action-btns {
            display: flex;
            gap: 8px;
        }

        .btn-filter-clear {
            background: var(--gray-100);
            border: none;
            border-radius: var(--radius-sm);
            padding: 7px 14px;
            font-weight: 600;
            font-size: .8rem;
            color: var(--gray-700);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all .2s ease;
            font-family: 'DM Sans', sans-serif;
        }

        .btn-filter-clear:hover {
            background: var(--gray-200);
            color: var(--gray-800);
        }

        .btn-filter-apply {
            background: linear-gradient(135deg, var(--accent-blue), #60a5fa);
            border: none;
            border-radius: var(--radius-sm);
            padding: 7px 14px;
            font-weight: 600;
            font-size: .8rem;
            color: #fff;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all .2s ease;
            box-shadow: 0 2px 8px rgba(59,130,246,.25);
            font-family: 'DM Sans', sans-serif;
        }

        .btn-filter-apply:hover {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59,130,246,.35);
        }

        /* Active filter pills shown in header */
        .active-filter-pills {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .filter-pill {
            background: var(--accent-blue-lt);
            color: var(--accent-blue);
            border-radius: 20px;
            padding: 3px 10px;
            font-size: .72rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            animation: pillIn .15s ease;
        }

        @keyframes pillIn {
            from { opacity: 0; transform: scale(.85); }
            to   { opacity: 1; transform: scale(1); }
        }

        .filter-pill-remove {
            cursor: pointer;
            opacity: .55;
            font-size: .65rem;
            transition: opacity .15s;
            line-height: 1;
        }

        .filter-pill-remove:hover { opacity: 1; }

        /* Empty state when filters yield 0 results */
        .filter-empty-state {
            display: none;
            text-align: center;
            padding: 48px 20px;
        }

        .filter-empty-state.is-visible {
            display: block;
        }

        .filter-empty-icon {
            width: 60px;
            height: 60px;
            background: var(--gray-100);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            font-size: 1.5rem;
            color: var(--gray-400);
        }

        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 20px 24px;
            }

            .page-header h2 {
                font-size: 1.4rem;
            }

            .filter-grid,
            .filter-grid-dates {
                grid-template-columns: 1fr;
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
                        <i class="fas fa-history"></i>
                        Visitor History
                    </h2>
                    <p class="subtitle">View and manage visitor records</p>
                </div>
            </div>
        </div>
        
        <div class="container-fluid">
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($visits)): ?>
        <!-- Filter Panel (only shown when records exist) -->
        <div class="filter-panel">
            <div class="filter-header" id="filterHeader">
                <div class="filter-header-left">
                    <h5><i class="fas fa-filter"></i> Filters</h5>
                    <div class="active-filter-pills" id="activePills"></div>
                </div>
                <div class="filter-toggle-icon" id="filterToggleIcon">
                    <i class="fas fa-chevron-down"></i>
                </div>
            </div>

            <div class="filter-body" id="filterBody">
                <!-- Row 1: Search, Status, Company, Purpose -->
                <div class="filter-grid">
                    <div>
                        <label class="filter-form-label"><i class="fas fa-search"></i> Search</label>
                        <input type="text" class="filter-control" id="f-search" placeholder="Name, pass, phone…">
                    </div>
                    <div>
                        <label class="filter-form-label"><i class="fas fa-circle"></i> Status</label>
                        <select class="filter-control" id="f-status">
                            <option value="">All Statuses</option>
                            <option value="checked_in">Checked In</option>
                            <option value="checked_out">Checked Out</option>
                        </select>
                    </div>
                    <div>
                        <label class="filter-form-label"><i class="fas fa-tag"></i> Purpose</label>
                        <select class="filter-control" id="f-purpose">
                            <option value="">All Purposes</option>
                            <?php
                            // Build purpose options from the already-fetched $visits array — no extra query
                            $purposes = array_unique(array_filter(array_column($visits, 'purpose')));
                            sort($purposes);
                            foreach ($purposes as $purpose): ?>
                                <option value="<?php echo strtolower(htmlspecialchars($purpose)); ?>">
                                    <?php echo htmlspecialchars($purpose); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Row 2: Date range -->
                <div class="filter-grid-dates">
                    <div>
                        <label class="filter-form-label"><i class="fas fa-calendar-alt"></i> Check-In From</label>
                        <input type="date" class="filter-control" id="f-date-from">
                    </div>
                    <div>
                        <label class="filter-form-label"><i class="fas fa-calendar-check"></i> Check-In To</label>
                        <input type="date" class="filter-control" id="f-date-to">
                    </div>
                </div>

                <!-- Actions bar -->
                <div class="filter-actions">
                    <div class="filter-result-count">
                        Showing <strong id="visibleCount"><?php echo count($visits); ?></strong>
                        of <strong><?php echo count($visits); ?></strong> records
                    </div>
                    <div class="filter-action-btns">
                        <button class="btn-filter-clear" id="clearFilters">
                            <i class="fas fa-times"></i> Clear All
                        </button>
                        <button class="btn-filter-apply" id="applyFilters">
                            <i class="fas fa-check"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Visitor Records -->
        <section class="content-section">
        <div class="card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-list"></i>Visit Records
                    <span class="badge bg-primary ms-2" id="tableCount"><?php echo count($visits); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($visits)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-3">No visit records found.</p>
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
                                <th>Address</th>
                                <th>Purpose</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php foreach ($visits as $visit): ?>
                                <tr
                                    data-status="<?php echo htmlspecialchars($visit['status']); ?>"
                                    data-purpose="<?php echo strtolower(htmlspecialchars($visit['purpose'] ?? '')); ?>"
                                    data-checkin-date="<?php echo date('Y-m-d', strtotime($visit['check_in_time'])); ?>"
                                    data-search="<?php echo strtolower(htmlspecialchars(
                                        $visit['first_name']            . ' ' .
                                        $visit['last_name']             . ' ' .
                                        $visit['visit_pass']            . ' ' .
                                        $visit['phone']                 . ' ' .
                                        ($visit['email']    ?? '') . ' ' .
                                        ($visit['address']  ?? '') . ' ' .
                                        ($visit['purpose']  ?? '') . ' ' .
                                        ($visit['person_to_visit']      ?? '')
                                    )); ?>"
                                >
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
                                    <td><?php echo htmlspecialchars($visit['address'] ?? '—'); ?></td>
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

                    <!-- Shown when all rows are filtered out -->
                    <div class="filter-empty-state" id="filterEmptyState">
                        <div class="filter-empty-icon">
                            <i class="fas fa-search-minus"></i>
                        </div>
                        <h6>No matching records</h6>
                        <p class="text-muted" style="font-size:.85rem;margin-bottom:16px;">
                            Try adjusting your filters or clearing them to see all records.
                        </p>
                        <button class="btn-filter-clear" id="clearFilters2">
                            <i class="fas fa-times"></i> Clear Filters
                        </button>
                    </div>
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
                    autoWidth: false,
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search records..."
                    }
                });
            }
        });
    </script>

    <?php if (!empty($visits)): ?>
    <script>
    (function () {

        /* ── Panel collapse toggle ──────────────────────────── */
        var header  = document.getElementById('filterHeader');
        var body    = document.getElementById('filterBody');
        var icon    = document.getElementById('filterToggleIcon');
        var isOpen  = false;

        header.addEventListener('click', function () {
            isOpen = !isOpen;
            body.classList.toggle('is-open', isOpen);
            header.classList.toggle('is-open', isOpen);
            icon.classList.toggle('rotated', isOpen);
        });

        /* ── Cache all data rows ────────────────────────────── */
        var rows  = Array.from(document.querySelectorAll('#tableBody tr'));
        var total = rows.length;

        /* ── Read current values from all filter controls ───── */
        function getFilters() {
            return {
                search:   document.getElementById('f-search').value.trim().toLowerCase(),
                status:   document.getElementById('f-status').value,
                purpose:  document.getElementById('f-purpose').value,
                dateFrom: document.getElementById('f-date-from').value,
                dateTo:   document.getElementById('f-date-to').value,
            };
        }

        /* ── Main filter function ───────────────────────────── */
        function applyFilters() {
            var f       = getFilters();
            var visible = 0;

            rows.forEach(function (row) {
                var show =
                    (!f.search   || row.dataset.search.includes(f.search)) &&
                    (!f.status   || row.dataset.status  === f.status)       &&
                    (!f.purpose  || row.dataset.purpose === f.purpose)       &&
                    (!f.dateFrom || row.dataset.checkinDate >= f.dateFrom)   &&
                    (!f.dateTo   || row.dataset.checkinDate <= f.dateTo);

                row.classList.toggle('filter-hidden', !show);
                if (show) visible++;
            });

            // Sync counters
            document.getElementById('visibleCount').textContent = visible;
            document.getElementById('tableCount').textContent   = visible;

            // Toggle "no results" state
            document.getElementById('filterEmptyState')
                .classList.toggle('is-visible', visible === 0);

            renderPills(f);
        }

        /* ── Render active-filter pills in the header ───────── */
        var pillLabel = {
            search:   function (v) { return '"' + v + '"'; },
            status:   function (v) { return v.replace('_', ' '); },
            purpose:  function (v) { return v; },
            dateFrom: function (v) { return 'From ' + v; },
            dateTo:   function (v) { return 'To '   + v; },
        };
        var fieldId = {
            search:   'f-search',
            status:   'f-status',
            purpose:  'f-purpose',
            dateFrom: 'f-date-from',
            dateTo:   'f-date-to',
        };

        function renderPills(f) {
            var container = document.getElementById('activePills');
            container.innerHTML = '';

            Object.keys(f).forEach(function (key) {
                var val = f[key];
                if (!val) return;

                var pill         = document.createElement('div');
                pill.className   = 'filter-pill';
                pill.innerHTML   =
                    pillLabel[key](val) +
                    ' <span class="filter-pill-remove" data-key="' + key + '">&#x2715;</span>';
                container.appendChild(pill);
            });

            // Wire individual pill × buttons
            container.querySelectorAll('.filter-pill-remove').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();              // don't toggle the panel
                    var el = document.getElementById(fieldId[btn.dataset.key]);
                    if (el) el.value = '';
                    applyFilters();
                });
            });
        }

        /* ── Reset all filters ──────────────────────────────── */
        function clearAll() {
            Object.values(fieldId).forEach(function (id) {
                document.getElementById(id).value = '';
            });
            applyFilters();
        }

        /* ── Wire up controls ───────────────────────────────── */
        document.getElementById('applyFilters').addEventListener('click', applyFilters);
        document.getElementById('clearFilters').addEventListener('click', clearAll);
        document.getElementById('clearFilters2').addEventListener('click', clearAll);

        // Live search as user types
        document.getElementById('f-search').addEventListener('input', applyFilters);

        // Live update on dropdown / date changes
        ['f-status', 'f-company', 'f-purpose', 'f-date-from', 'f-date-to'].forEach(function (id) {
            document.getElementById(id).addEventListener('change', applyFilters);
        });

        // Sync counts on first load
        applyFilters();

    })();
    </script>
    <?php endif; ?>
    
    <?php include '../includes/chat-widget.php'; ?>
</body>
</html>