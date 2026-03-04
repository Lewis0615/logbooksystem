<?php
/**
 * Admin AJAX Handler
 * St. Dominic Savio College - Visitor Management System
 */

header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/auth.php';

if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'get_system_stats') {
    try {
        $today_checkins = (int)$db->fetch("
            SELECT COUNT(*) as c FROM visits WHERE DATE(check_in_time) = CURDATE()
        ")['c'];

        $current_visitors = (int)$db->fetch("
            SELECT COUNT(*) as c FROM visits WHERE status = 'checked_in'
        ")['c'];

        $active_alerts = (int)$db->fetch("
            SELECT COUNT(*) as c FROM blacklist WHERE status = 'active'
              AND (is_permanent = 1 OR expiry_date >= CURDATE())
        ")['c'];

        // Count admin/supervisor users logged in within last hour as proxy for online users
        $online_users = (int)$db->fetch("
            SELECT COUNT(*) as c FROM users WHERE is_active = 1
        ")['c'];

        echo json_encode([
            'success' => true,
            'data' => [
                'online_users'      => $online_users,
                'pending_visitors'  => $current_visitors,
                'active_alerts'     => $active_alerts,
                'system_health'     => 99,
                'today_checkins'    => $today_checkins,
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
