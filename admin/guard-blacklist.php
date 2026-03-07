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
    <link rel="stylesheet" href="../assets/css/guard-blocklist.css">
    
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