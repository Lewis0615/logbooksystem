<?php
/**
 * Visitor Data AJAX Handler
 * St. Dominic Savio College - Visitor Management System
 */

header('Content-Type: application/json');
require_once '../../config/config.php';
require_once '../../config/auth.php';

// Check authentication using the proper auth system
if (!$auth->isLoggedIn()) {
    echo json_encode(['error' => 'Access denied', 'message' => 'User not authenticated']);
    exit;
}

try {
    // Get statistics
    $stats = [
        'todayCheckIns' => 0,
        'currentVisitors' => 0,
        'todayCheckOuts' => 0,
        'overdueVisitors' => 0
    ];

    // Today's check-ins
    $result = $db->fetch("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE DATE(check_in_time) = CURDATE()
    ");
    $stats['todayCheckIns'] = (int)$result['count'];

    // Current visitors (checked in)
    $result = $db->fetch("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE status = 'checked_in'
    ");
    $stats['currentVisitors'] = (int)$result['count'];

    // Today's check-outs
    $result = $db->fetch("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE DATE(check_out_time) = CURDATE() AND status = 'checked_out'
    ");
    $stats['todayCheckOuts'] = (int)$result['count'];

    // Overdue visitors
    $result = $db->fetch("
        SELECT COUNT(*) as count 
        FROM visits 
        WHERE status = 'checked_in' AND NOW() > expected_checkout_time
    ");
    $stats['overdueVisitors'] = (int)$result['count'];

    // Get currently checked-in visitors
    $visitors = $db->fetchAll("
        SELECT 
            v.id,
            v.visit_pass,
            v.check_in_time,
            v.expected_checkout_time,
            v.department,
            v.is_group_visit,
            v.group_size,
            v.group_members,
            v.additional_notes,
            vis.first_name,
            vis.last_name,
            vis.phone,
            vis.email,
            vis.address,
            vis.company_organization,
            vis.id_type,
            v.person_to_visit,
            v.purpose,
            TIMESTAMPDIFF(MINUTE, v.check_in_time, NOW()) as duration_minutes,
            CASE WHEN NOW() > v.expected_checkout_time THEN 1 ELSE 0 END as is_overdue
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.id
        WHERE v.status = 'checked_in'
        ORDER BY v.check_in_time ASC
    ");

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'visitors' => $visitors,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load visitor data: ' . $e->getMessage(),
        'line' => $e->getLine(),
        'file' => basename($e->getFile())
    ]);
}
?>