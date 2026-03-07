<?php
/**
 * Guard Blacklist Management Interface
 * St. Dominic Savio College - Visitor Management System
 */

require_once '../config/config.php';
require_once '../config/auth.php';

// Check authentication - allow guards
$auth->requireLogin('login.php');

$error_message = '';
$success_message = '';

// Handle AJAX request to get blacklist entries
if (isset($_GET['action']) && $_GET['action'] === 'get_blacklist') {
    header('Content-Type: application/json');
    
    try {
        $blacklist = $db->fetchAll("
            SELECT b.*, u.first_name as created_by_name, u.last_name as created_by_lastname
            FROM blacklist b 
            LEFT JOIN users u ON b.created_by = u.id 
            WHERE b.status = 'active'
            ORDER BY b.created_at DESC
        ");
        
        echo json_encode([
            'success' => true,
            'blacklist' => $blacklist
        ]);
        exit;
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error loading blacklist: ' . $e->getMessage()
        ]);
        exit;
    }
}

// Load blocklist stats for display
try {
    $total_blocked = $db->fetch(
        "SELECT COUNT(*) as count FROM blacklist WHERE status = 'active'"
    )['count'] ?? 0;
    $permanent_count = $db->fetch(
        "SELECT COUNT(*) as count FROM blacklist WHERE status = 'active' AND is_permanent = 1"
    )['count'] ?? 0;
    $expiring_soon = $db->fetch(
        "SELECT COUNT(*) as count FROM blacklist WHERE status = 'active' AND is_permanent = 0
         AND expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
    )['count'] ?? 0;
} catch (Exception $e) {
    $total_blocked = 0;
    $permanent_count = 0;
    $expiring_soon = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blocklist</title>
    <link rel="icon" type="image/png" href="/logbooksystem/assets/images/sdsclogo.png">
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

        /* ─── LAYOUT ─── */
        .main-content {
            margin-left: 260px;
            padding: 28px 30px;
            min-height: 100vh;
            transition: margin-left .3s ease;
        }

        /* ─── PAGE HEADER ─── */
        .page-header {
            background: linear-gradient(135deg, var(--accent-red) 0%, #dc2626 55%, #b91c1c 100%);
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

        .protected-badge {
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

        .protected-dot {
            width: 8px; height: 8px;
            background: #fbbf24;
            border-radius: 50%;
            box-shadow: 0 0 0 2px rgba(251,191,36,.3);
            animation: protectedPulse 2s infinite;
        }

        @keyframes protectedPulse {
            0%, 100% { box-shadow: 0 0 0 2px rgba(251,191,36,.3); }
            50%       { box-shadow: 0 0 0 6px rgba(251,191,36,.08); }
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
            font-family: 'Work Sans', sans-serif;
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
            background: var(--accent-red-lt);
            color: var(--accent-red);
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
            border-color: var(--accent-red);
            box-shadow: 0 0 0 3px rgba(239,68,68,.12);
        }

        .search-input::placeholder { color: var(--gray-400); }

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

        .btn-blacklist {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            background: linear-gradient(135deg, var(--accent-red), #dc2626);
            border: none;
            border-radius: var(--radius-sm);
            font-family: 'DM Sans', sans-serif;
            font-size: .8rem;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(239,68,68,.28);
            transition: all .2s;
        }

        .btn-blacklist:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(239,68,68,.38);
        }

        .btn-remove {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            padding: 6px 12px;
            background: var(--accent-orange);
            border: none;
            border-radius: var(--radius-sm);
            font-family: 'DM Sans', sans-serif;
            font-size: .75rem;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            transition: all .2s;
        }

        .btn-remove:hover {
            background: #d97706;
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(249,115,22,.35);
        }

        .btn-add-blacklist {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: linear-gradient(135deg, var(--accent-red), #dc2626);
            border: none;
            border-radius: var(--radius-sm);
            font-family: 'DM Sans', sans-serif;
            font-size: .85rem;
            font-weight: 600;
            color: #fff;
            cursor: pointer;
            box-shadow: var(--shadow-sm);
            transition: all .2s;
        }

        .btn-add-blacklist:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* ─── BLACKLIST ITEMS ─── */
        .blacklist-item {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 16px 18px;
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            transition: transform .2s, box-shadow .2s;
            border-left: 4px solid var(--accent-red);
        }

        .blacklist-item:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .blacklist-info {
            flex-grow: 1;
        }

        .blacklist-name {
            font-weight: 700;
            font-size: .95rem;
            color: var(--gray-900);
            margin-bottom: 6px;
        }

        .blacklist-meta {
            font-size: .8rem;
            color: var(--gray-500);
            line-height: 1.6;
            margin-bottom: 8px;
        }

        .blacklist-meta i { width: 16px; opacity: .7; }

        .blacklist-reason {
            background: var(--accent-red-lt);
            color: var(--accent-red);
            padding: 8px 12px;
            border-radius: var(--radius-sm);
            font-size: .8rem;
            font-weight: 500;
            margin-bottom: 8px;
        }

        .blacklist-badges {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .severity-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: .7rem;
            font-weight: 600;
        }

        .severity-low    { background: var(--accent-orange-lt); color: var(--accent-orange); }
        .severity-medium { background: var(--accent-red-lt); color: var(--accent-red); }
        .severity-high   { background: #fecaca; color: #991b1b; }

        .permanent-badge {
            background: var(--gray-200);
            color: var(--gray-700);
            padding: 3px 8px;
            border-radius: 12px;
            font-size: .7rem;
            font-weight: 600;
        }

        /* ─── VISITOR SELECTION ─── */
        .visitor-select-item {
            background: var(--gray-50);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            padding: 12px 14px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            transition: all .2s;
            cursor: pointer;
        }

        .visitor-select-item:hover {
            background: #fff;
            box-shadow: var(--shadow-sm);
        }

        .visitor-select-item.selected {
            background: var(--accent-blue-lt);
            border-color: var(--accent-blue);
        }

        .visitor-select-item.blacklisted {
            background: var(--accent-red-lt);
            border-color: var(--accent-red);
            opacity: 0.7;
            cursor: not-allowed;
        }

        .visitor-info h6 {
            font-weight: 700;
            font-size: .9rem;
            color: var(--gray-900);
            margin: 0 0 3px;
        }

        .visitor-info small {
            font-size: .75rem;
            color: var(--gray-500);
        }

        /* ─── EMPTY STATES ─── */
        .empty-state {
            text-align: center;
            padding: 44px 20px;
        }

        .empty-icon {
            width: 60px; height: 60px;
            background: var(--gray-100);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 14px;
            font-size: 22px;
            color: var(--gray-400);
        }

        .empty-title {
            font-family: 'Work Sans', sans-serif;
            font-weight: 700;
            font-size: .95rem;
            color: var(--gray-700);
            margin-bottom: 6px;
        }

        .empty-sub {
            font-size: .8rem;
            color: var(--gray-400);
            max-width: 260px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* ─── FORMS ─── */
        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            font-size: .8rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 6px;
            display: block;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: 'DM Sans', sans-serif;
            font-size: .85rem;
            color: var(--gray-800);
            background: #fff;
            transition: border-color .2s, box-shadow .2s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-red);
            box-shadow: 0 0 0 3px rgba(239,68,68,.12);
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .form-check-input {
            margin: 0;
        }

        .form-check-label {
            font-size: .85rem;
            color: var(--gray-700);
            margin: 0;
        }

        /* ─── ANIMATIONS ─── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 1200px) {
            .main-content { margin-left: 0; padding: 16px; }
        }

        @media (max-width: 640px) {
            .page-header h2 { font-size: 1.4rem; }
        }
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
                        <i class="fas fa-ban"></i>
                        Blocklist
                    </p>
                    <p class="subtitle"><i class="fas fa-eye me-1"></i> View only &mdash; entries are managed by administrators</p>
                </div>
                <div class="protected-badge">
                    <div class="protected-dot"></div>
                    Protected Area
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

        <!-- STATS ROW -->
        <div class="row g-3 mb-3">
            <div class="col-sm-4">
                <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px 20px;display:flex;align-items:center;gap:14px;">
                    <div style="width:42px;height:42px;background:var(--accent-red-lt);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-ban" style="color:var(--accent-red);"></i>
                    </div>
                    <div>
                        <div style="font-size:1.5rem;font-weight:800;font-family:'Work Sans',sans-serif;color:var(--gray-900);line-height:1;"><?php echo $total_blocked; ?></div>
                        <div style="font-size:.75rem;color:var(--gray-500);margin-top:2px;">Total Blocked</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px 20px;display:flex;align-items:center;gap:14px;">
                    <div style="width:42px;height:42px;background:#fef2f2;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-infinity" style="color:#991b1b;"></i>
                    </div>
                    <div>
                        <div style="font-size:1.5rem;font-weight:800;font-family:'Work Sans',sans-serif;color:var(--gray-900);line-height:1;"><?php echo $permanent_count; ?></div>
                        <div style="font-size:.75rem;color:var(--gray-500);margin-top:2px;">Permanent Bans</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-4">
                <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm);padding:16px 20px;display:flex;align-items:center;gap:14px;">
                    <div style="width:42px;height:42px;background:var(--accent-orange-lt);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-clock" style="color:var(--accent-orange);"></i>
                    </div>
                    <div>
                        <div style="font-size:1.5rem;font-weight:800;font-family:'Work Sans',sans-serif;color:var(--gray-900);line-height:1;"><?php echo $expiring_soon; ?></div>
                        <div style="font-size:.75rem;color:var(--gray-500);margin-top:2px;">Expiring in 7 Days</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BLOCKLIST VIEW -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-header-title">
                    <div class="card-header-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    Blocked Visitors
                    <span style="background:var(--accent-red-lt);color:var(--accent-red);font-size:.7rem;font-weight:700;padding:2px 8px;border-radius:10px;margin-left:4px;"><?php echo $total_blocked; ?></span>
                </h5>
                <div class="card-header-actions">
                    <span style="font-size:.75rem;color:var(--gray-400);display:flex;align-items:center;gap:5px;">
                        <i class="fas fa-eye"></i> View only &mdash; managed by Admin
                    </span>
                    <button class="btn-refresh" onclick="refreshBlacklist()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
            </div>
            <div class="card-body-scroll">
                <div class="search-wrap">
                    <i class="fas fa-search"></i>
                    <input type="text" class="search-input" id="blacklistSearch" placeholder="Search by name, phone, or reason...">
                </div>
                <div id="blacklistList">
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-spinner fa-spin"></i></div>
                        <div class="empty-title">Loading blocklist...</div>
                        <div class="empty-sub">Please wait</div>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /main-content -->

    <!-- VISITOR DETAIL MODAL (read-only) -->
    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background:var(--accent-red-lt);border-bottom:1px solid var(--border);">
                    <h5 class="modal-title">
                        <i class="fas fa-ban me-2" style="color:var(--accent-red);"></i>
                        Blocked Visitor Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" style="padding:24px;" id="detailModalBody">
                    <div class="text-center py-3">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                    </div>
                </div>
                <div class="modal-footer" style="border-top:1px solid var(--border);background:var(--gray-50);">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            refreshBlacklist();

            // Live search
            $('#blacklistSearch').on('keyup', function() {
                const term = $(this).val().toLowerCase();
                $('.blacklist-item').each(function() {
                    $(this).toggle($(this).text().toLowerCase().includes(term));
                });
            });
        });

        function refreshBlacklist() {
            $('#blacklistList').html(loadingHTML('blocklist entries'));

            $.ajax({
                url: 'guard-blacklist.php?action=get_blacklist',
                method: 'GET',
                dataType: 'json',
                success: function(data) {
                    data.success ? displayBlacklist(data.blacklist) : $('#blacklistList').html(errorHTML(data.message));
                },
                error: function() {
                    $('#blacklistList').html(errorHTML('Failed to load blocklist. Please refresh.'));
                }
            });
        }

        function displayBlacklist(blacklist) {
            if (!blacklist.length) {
                $('#blacklistList').html(`
                    <div class="empty-state">
                        <div class="empty-icon"><i class="fas fa-check-circle" style="color:var(--green-400);"></i></div>
                        <div class="empty-title">No blocked visitors</div>
                        <div class="empty-sub">No visitors are currently on the blocklist</div>
                    </div>`);
                return;
            }

            let html = '';
            blacklist.forEach(function(b) {
                const createdBy = b.created_by_name ? `${esc(b.created_by_name)} ${esc(b.created_by_lastname || '')}` : 'Admin';
                const createdDate = formatDateTime(b.created_at);
                const expiryInfo = b.is_permanent == 1
                    ? '<span class="permanent-badge"><i class="fas fa-infinity me-1"></i>Permanent</span>'
                    : (b.expiry_date
                        ? `<span class="severity-badge severity-low"><i class="fas fa-calendar me-1"></i>Expires ${formatDate(b.expiry_date)}</span>`
                        : '<span style="font-size:.72rem;color:var(--gray-400);">No expiry set</span>');

                html += `
                <div class="blacklist-item" style="cursor:pointer;" onclick="showDetail(${JSON.stringify(b).replace(/"/g, '&quot;')})">
                    <div class="blacklist-info">
                        <div class="blacklist-name">${esc(b.first_name)} ${esc(b.last_name)}</div>
                        <div class="blacklist-meta">
                            <div><i class="fas fa-phone"></i> ${esc(b.phone)}${b.email ? ' &nbsp;•&nbsp; <i class="fas fa-envelope"></i> ' + esc(b.email) : ''}</div>
                            <div><i class="fas fa-user-shield"></i> Added by <strong>${createdBy}</strong> &mdash; ${createdDate}</div>
                        </div>
                        <div class="blacklist-reason"><strong>Reason:</strong> ${esc(b.reason)}</div>
                        <div class="blacklist-badges" style="margin-top:6px;">
                            <span class="severity-badge severity-${esc(b.severity)}">
                                <i class="fas fa-exclamation-triangle"></i> ${esc(b.severity).toUpperCase()}
                            </span>
                            ${expiryInfo}
                        </div>
                    </div>
                    <div style="flex-shrink:0;">
                        <span style="background:var(--accent-red-lt);color:var(--accent-red);border:1px solid rgba(239,68,68,.2);border-radius:6px;padding:5px 10px;font-size:.72rem;font-weight:700;white-space:nowrap;">
                            <i class="fas fa-ban me-1"></i>BLOCKED
                        </span>
                    </div>
                </div>`;
            });

            $('#blacklistList').html(html);
        }

        function showDetail(b) {
            const createdBy = b.created_by_name ? `${b.created_by_name} ${b.created_by_lastname || ''}` : 'Admin';
            const expiryInfo = b.is_permanent == 1 ? 'Permanent ban' : (b.expiry_date ? `Expires ${formatDate(b.expiry_date)}` : 'No expiry set');

            $('#detailModalBody').html(`
                <div class="d-flex align-items-center gap-3 mb-4 p-3" style="background:var(--accent-red-lt);border-radius:var(--radius-sm);">
                    <div style="width:48px;height:48px;background:var(--accent-red);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <i class="fas fa-user-slash" style="color:#fff;font-size:1.1rem;"></i>
                    </div>
                    <div>
                        <div style="font-weight:800;font-size:1.05rem;color:var(--gray-900);">${esc(b.first_name)} ${esc(b.last_name)}</div>
                        <div style="font-size:.8rem;color:var(--accent-red);font-weight:600;"><i class="fas fa-ban me-1"></i>BLOCKED VISITOR</div>
                    </div>
                </div>
                <table style="width:100%;font-size:.85rem;border-collapse:collapse;">
                    <tr><td style="padding:8px 0;color:var(--gray-500);width:38%;font-weight:600;"><i class="fas fa-phone me-2"></i>Phone</td><td style="padding:8px 0;color:var(--gray-900);">${esc(b.phone)}</td></tr>
                    ${b.email ? `<tr><td style="padding:8px 0;color:var(--gray-500);font-weight:600;"><i class="fas fa-envelope me-2"></i>Email</td><td style="padding:8px 0;color:var(--gray-900);">${esc(b.email)}</td></tr>` : ''}
                    <tr><td style="padding:8px 0;color:var(--gray-500);font-weight:600;"><i class="fas fa-exclamation-triangle me-2"></i>Severity</td><td style="padding:8px 0;"><span class="severity-badge severity-${esc(b.severity)}">${esc(b.severity).toUpperCase()}</span></td></tr>
                    <tr><td style="padding:8px 0;color:var(--gray-500);font-weight:600;"><i class="fas fa-calendar me-2"></i>Duration</td><td style="padding:8px 0;color:var(--gray-900);">${esc(expiryInfo)}</td></tr>
                    <tr><td style="padding:8px 0;color:var(--gray-500);font-weight:600;"><i class="fas fa-user-shield me-2"></i>Added by</td><td style="padding:8px 0;color:var(--gray-900);">${esc(createdBy)}</td></tr>
                    <tr><td style="padding:8px 0;color:var(--gray-500);font-weight:600;"><i class="fas fa-clock me-2"></i>Date Added</td><td style="padding:8px 0;color:var(--gray-900);">${formatDateTime(b.created_at)}</td></tr>
                </table>
                <div class="blacklist-reason mt-3"><strong>Reason:</strong> ${esc(b.reason)}</div>
                ${b.notes ? `<div style="margin-top:8px;padding:10px 12px;background:var(--gray-50);border-radius:var(--radius-sm);font-size:.82rem;color:var(--gray-600);"><strong>Notes:</strong> ${esc(b.notes)}</div>` : ''}
            `);
            new bootstrap.Modal(document.getElementById('detailModal')).show();
        }
        
        // Helper functions
        function formatDateTime(dt) {
            return new Date(dt).toLocaleString('en-US', {
                month: 'short', day: 'numeric', year: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            });
        }
        
        function formatDate(dt) {
            return new Date(dt).toLocaleDateString('en-US', {
                month: 'short', day: 'numeric', year: 'numeric'
            });
        }
        
        function showAlert(type, message) {
            const icon = type === 'success' ? 'check-circle' : 'exclamation-triangle';
            const html = `
                <div class="alert alert-${type} alert-dismissible fade show">
                    <i class="fas fa-${icon}"></i> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>`;
            $('.main-content').prepend(html);
            setTimeout(() => $('.alert.fade').remove(), 5000);
        }
        
        function loadingHTML(label) {
            return `
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-spinner fa-spin"></i></div>
                    <div class="empty-title">Loading ${label}...</div>
                    <div class="empty-sub">Please wait</div>
                </div>`;
        }
        
        function errorHTML(msg) {
            return `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i> ${msg}
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
    </script>
    
    <?php include '../includes/chat-widget.php'; ?>
</body>
</html>