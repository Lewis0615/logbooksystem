<?php
/**
 * Login AJAX Handler
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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    // Fallback to POST data
    $input = $_POST;
}

$response = ['success' => false, 'message' => '', 'redirect' => ''];

try {
    $username = sanitizeInput($input['username'] ?? '');
    $password = $input['password'] ?? '';
    $csrf_token = $input['csrf_token'] ?? '';
    
    // Validate input
    if (empty($username) || empty($password)) {
        $response['message'] = 'Please enter both username and password.';
        echo json_encode($response);
        exit();
    }
    
    // Skip CSRF validation temporarily for testing
    if (!validateCSRFToken($csrf_token)) {
        $response['message'] = 'Security token validation failed. Please try again.';
        echo json_encode($response);
        exit();
    }
    
    // Attempt login
    $result = $auth->login($username, $password);
    
    if ($result['success']) {
        $user = $result['user'];
        
        // Determine redirect URL based on role
        if ($user['role'] === ROLE_ADMIN || $user['role'] === ROLE_SUPERVISOR) {
            $redirect_url = '../admin/dashboard.php';
        } elseif ($user['role'] === ROLE_GUARD) {
            $redirect_url = '../admin/checkin.php';
        } else {
            $redirect_url = '../admin/dashboard.php';
        }
        
        $response = [
            'success' => true,
            'message' => 'Login successful! Redirecting...',
            'redirect' => $redirect_url,
            'user' => [
                'username' => $user['username'],
                'role' => $user['role'],
                'name' => $user['name'] ?? $user['username']
            ]
        ];
    } else {
        $response['message'] = $result['message'];
    }
    
} catch (Exception $e) {
    $response['message'] = 'Login error. Please try again later.';
    error_log("Login AJAX error: " . $e->getMessage());
}

echo json_encode($response);
exit();
?>