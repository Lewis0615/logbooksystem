<?php
/**
 * Visitor Management AJAX Endpoint
 * St. Dominic Savio College - Visitor Management System
 * Handles real-time visitor count and security logging
 */

require_once '../config/config.php';
require_once '../config/auth.php';

// Set JSON header
header('Content-Type: application/json');

// Get action from POST or GET
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_current_count':
            // Get current visitor count
            $current_visitors = $db->fetch(
                "SELECT COUNT(*) as count FROM visits 
                 WHERE status = 'checked_in'", 
                []
            );
            
            // Get recent blacklist attempts (today)
            $blacklist_attempts = $db->fetch(
                "SELECT COUNT(*) as count FROM blacklist 
                 WHERE DATE(created_at) = CURDATE() 
                 AND status = 'active'", 
                []
            );
            
            echo json_encode([
                'success' => true,
                'count' => (int)($current_visitors['count'] ?? 0),
                'blacklist_attempts' => (int)($blacklist_attempts['count'] ?? 0)
            ]);
            break;
            
        case 'log_security_contact':
            // Check if user is logged in for security logging
            if (!$auth->isLoggedIn()) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Authentication required for security logging'
                ]);
                break;
            }
            
            $reason = $_POST['reason'] ?? 'unknown';
            $user_id = $_SESSION['user_id'] ?? null;
            
            if ($user_id) {
                $auth->logActivity($user_id, 'SECURITY_CONTACT', 
                    "Emergency security contact initiated: " . $reason);
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Security contact logged successfully'
            ]);
            break;
            
        case 'get_visitor_stats':
            // Get comprehensive visitor statistics
            $stats = [];
            
            // Today's check-ins
            $today_checkins = $db->fetch(
                "SELECT COUNT(*) as count FROM visits 
                 WHERE DATE(check_in_time) = CURDATE()", 
                []
            );
            $stats['today_checkins'] = (int)($today_checkins['count'] ?? 0);
            
            // Current visitors
            $current_visitors = $db->fetch(
                "SELECT COUNT(*) as count FROM visits 
                 WHERE status = 'checked_in'", 
                []
            );
            $stats['current_visitors'] = (int)($current_visitors['count'] ?? 0);
            
            // Today's checkouts
            $today_checkouts = $db->fetch(
                "SELECT COUNT(*) as count FROM visits 
                 WHERE DATE(check_out_time) = CURDATE()", 
                []
            );
            $stats['today_checkouts'] = (int)($today_checkouts['count'] ?? 0);
            
            // Overdue visitors (expected checkout time passed)
            $overdue_visitors = $db->fetch(
                "SELECT COUNT(*) as count FROM visits 
                 WHERE status = 'checked_in' 
                 AND expected_checkout_time < NOW()", 
                []
            );
            $stats['overdue_visitors'] = (int)($overdue_visitors['count'] ?? 0);
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action specified'
            ]);
            break;
    }
    
} catch (Exception $e) {
    // Log the error
    error_log("Visitor AJAX Error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred processing your request'
    ]);
}
?>