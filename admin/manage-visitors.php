<?php
/**
 * Visitor History & Management Module
 * St. Dominic Savio College - Visitor Management System
 */

require_once '../config/config.php';
require_once '../config/settings.php';
require_once '../config/auth.php';

// Require admin or supervisor role
$auth->requireLogin('login.php');
if (!$auth->hasRole(ROLE_ADMIN) && !$auth->hasRole(ROLE_SUPERVISOR)) {
    header('Location: checkin.php');
    exit();
}

$error_message = '';

// Load purpose list from settings (same source as the registration form)
$visit_purposes = getSettingList('visit_purposes_list', $visit_purposes);

// Get visitor history
try {
    $visits = $db->fetchAll("
        SELECT v.*, vis.first_name, vis.last_name, vis.phone, vis.email, vis.address,
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
    <title>Visitor History</title>
    <link rel="icon" type="image/png" href="/logbooksystem/assets/images/sdsclogo.png">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/vendor/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/datatables.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/manage-visitor.css">
    
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
                            <?php foreach ($visit_purposes as $purpose): ?>
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