<?php
/**
 * Logout Page
 * St. Dominic Savio College - Visitor Management System
 */

require_once '../config/config.php';
require_once '../config/auth.php';

// Check if this is an AJAX request
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    // Handle via AJAX
    header('Content-Type: application/json');
    
    try {
        $result = $auth->logout();
        echo json_encode([
            'success' => true,
            'message' => 'Successfully logged out.',
            'redirect' => 'login.php?message=logged_out'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Logout failed. Please try again.'
        ]);
    }
    exit();
}

// Regular logout (non-AJAX)
$auth->logout();

// Redirect to login page with success message
header('Location: login.php?message=logged_out');
exit();
?>