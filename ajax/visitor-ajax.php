<?php
/**
 * Visitor AJAX Handler
 * St. Dominic Savio College - Visitor Management System
 */

header('Content-Type: application/json');
require_once '../config/config.php';
require_once '../config/auth.php';

// Check authentication
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_current_count':
            // Get current visitor count
            $count = $db->fetch("SELECT COUNT(*) as count FROM visits WHERE status = 'checked_in'")['count'];
            
            // Get recent blacklist attempts (last 24 hours)
            $blacklist_attempts = $db->fetch("
                SELECT COUNT(*) as count 
                FROM activity_logs 
                WHERE action = 'BLACKLIST_ATTEMPT' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ")['count'];
            
            // Get overstayed visitors
            $overstayed = $db->fetch("
                SELECT COUNT(*) as count 
                FROM visits 
                WHERE status = 'checked_in' 
                AND NOW() > expected_checkout_time
            ")['count'];
            
            $response = [
                'success' => true,
                'count' => (int)$count,
                'blacklist_attempts' => (int)$blacklist_attempts,
                'overstayed' => (int)$overstayed,
                'message' => 'Visitor count retrieved successfully'
            ];
            break;
            
        case 'get_recent_visitors':
            // Get recent check-ins (last 10)
            $recent_visitors = $db->fetchAll("
                SELECT v.visit_pass, v.check_in_time,
                       vis.first_name, vis.last_name, vis.company_organization,
                       v.person_to_visit, v.purpose
                FROM visits v
                JOIN visitors vis ON v.visitor_id = vis.id
                WHERE v.status = 'checked_in'
                ORDER BY v.check_in_time DESC
                LIMIT 10
            ");
            
            $response = [
                'success' => true,
                'data' => $recent_visitors,
                'message' => 'Recent visitors retrieved successfully'
            ];
            break;
            
        case 'search_visitor':
            $search_term = $_POST['search'] ?? '';
            
            if (empty($search_term)) {
                $response['message'] = 'Search term is required';
                break;
            }
            
            // Search for visitors
            $visitors = $db->fetchAll("
                SELECT vis.*, 
                       v.id as visit_id, v.status, v.visit_pass, v.check_in_time
                FROM visitors vis
                LEFT JOIN visits v ON vis.id = v.visitor_id AND v.status = 'checked_in'
                WHERE vis.first_name LIKE ? 
                   OR vis.last_name LIKE ? 
                   OR vis.phone LIKE ?
                   OR v.visit_pass LIKE ?
                ORDER BY v.check_in_time DESC
                LIMIT 20
            ", ["%$search_term%", "%$search_term%", "%$search_term%", "%$search_term%"]);
            
            $response = [
                'success' => true,
                'data' => $visitors,
                'message' => 'Search completed successfully'
            ];
            break;
            
        case 'check_blacklist':
            $phone = $_POST['phone'] ?? '';
            $visitor_id = (int)($_POST['visitor_id'] ?? 0);
            
            if (empty($phone) && $visitor_id <= 0) {
                $response['message'] = 'Phone number or visitor ID is required';
                break;
            }
            
            $sql = "SELECT * FROM blacklist WHERE status = 'active' 
                    AND (is_permanent = 1 OR expiry_date >= CURDATE())";
            $params = [];
            
            if ($visitor_id > 0) {
                $sql .= " AND visitor_id = ?";
                $params[] = $visitor_id;
            } elseif (!empty($phone)) {
                $sql .= " AND phone = ?";
                $params[] = $phone;
            }
            
            $blacklist_entry = $db->fetch($sql, $params);
            
            $response = [
                'success' => true,
                'is_blacklisted' => !empty($blacklist_entry),
                'data' => $blacklist_entry,
                'message' => $blacklist_entry ? 'Visitor is blacklisted' : 'Visitor is not blacklisted'
            ];
            break;
            
        default:
            $response['message'] = 'Invalid action specified';
            break;
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'data' => null
    ];
    error_log("Visitor AJAX error: " . $e->getMessage());
}

echo json_encode($response);
exit();
?>