<?php
/**
 * Logout AJAX Handler
 * St. Dominic Savio College - Visitor Management System
 */

header('Content-Type: application/json');
require_once '../../config/config.php';
require_once '../../config/auth.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$response = ['success' => false, 'message' => '', 'redirect' => ''];

try {
    // Check if user is logged in
    if (!$auth->isLoggedIn()) {
        $response = [
            'success' => true,
            'message' => 'Already logged out.',
            'redirect' => 'login.php'
        ];
        echo json_encode($response);
        exit();
    }
    
    // Perform logout
    $logoutResult = $auth->logout();
    
    if ($logoutResult) {
        $response = [
            'success' => true,
            'message' => 'Successfully logged out.',
            'redirect' => 'login.php?message=logged_out'
        ];
    } else {
        $response['message'] = 'Logout failed. Please try again.';
    }
    
} catch (Exception $e) {
    $response['message'] = 'Logout error. Please try again.';
    error_log("Logout AJAX error: " . $e->getMessage());
}

echo json_encode($response);
exit();
?>