<?php
/**
 * Chat Messages AJAX Handler
 * St. Dominic Savio College - Visitor Management System
 * 
 * Handles real-time chat functionality between guards and administrators
 */

// Buffer ALL output so any PHP warnings/notices don't corrupt the JSON response
ob_start();

// Suppress display_errors for this AJAX endpoint — errors go to log only
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Include config first — it handles session_start() safely
try {
    require_once '../config/config.php';
    require_once '../config/auth.php';
} catch (Exception $e) {
    ob_end_clean();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuration error: ' . $e->getMessage()]);
    exit();
}

// Now safe to set JSON header (session already started by config.php)
ob_end_clean();
header('Content-Type: application/json');

// Check if user is authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get current user info
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'];
$current_user_name = ($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '');

// Determine target users based on role
if ($current_user_role === ROLE_GUARD) {
    // Guards can chat with admins and supervisors
    $target_roles = [ROLE_ADMIN, ROLE_SUPERVISOR];
} else if ($current_user_role === ROLE_ADMIN || $current_user_role === ROLE_SUPERVISOR) {
    // Admins and supervisors can chat with guards and each other
    $target_roles = [ROLE_GUARD, ROLE_ADMIN, ROLE_SUPERVISOR];
} else {
    // Invalid role
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid role']);
    exit();
}

// Handle different actions
$action = $_POST['action'] ?? $_GET['action'] ?? 'load';

try {
    switch ($action) {
        case 'load':
        case 'refresh':
            loadMessages();
            break;
            
        case 'send':
            sendMessage();
            break;
            
        case 'unread_count':
            getUnreadCount();
            break;
            
        case 'mark_read':
            markMessagesAsRead();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Load chat messages
 */
function loadMessages() {
    global $pdo, $current_user_id, $current_user_role, $target_roles;
    
    try {
        // Check if PDO connection exists
        if (!isset($pdo) || !$pdo) {
            throw new Exception('Database connection not available');
        }
        
        // Get last message ID for incremental loading
        $last_message_id = intval($_GET['last_message_id'] ?? 0);
        
        // Build query to get messages
        $placeholders = str_repeat('?,', count($target_roles) - 1) . '?';

        // Base WHERE clause – shared by both queries
        $base_where = "(
                    (cm.sender_id = ? AND cm.receiver_role IN ($placeholders)) OR
                    (u.role IN ($placeholders) AND cm.receiver_role = ?)
                )";

        // Params without last_message_id (used for initial full load)
        $base_params = array_merge(
            [$current_user_id],
            $target_roles,
            $target_roles,
            [$current_user_role]
        );

        if ($last_message_id > 0) {
            // Incremental: only fetch new messages since last seen
            $sql = "SELECT 
                        cm.id,
                        cm.sender_id,
                        cm.message,
                        cm.created_at,
                        CONCAT(u.first_name, ' ', u.last_name) as sender_name,
                        u.role as sender_role
                    FROM chat_messages cm
                    JOIN users u ON cm.sender_id = u.id
                    WHERE $base_where
                    AND cm.id > ?
                    ORDER BY cm.created_at ASC
                    LIMIT 50";
            $params = array_merge($base_params, [$last_message_id]);
        } else {
            // Initial load: get the 50 most recent messages
            $sql = "SELECT 
                        cm.id,
                        cm.sender_id,
                        cm.message,
                        cm.created_at,
                        CONCAT(u.first_name, ' ', u.last_name) as sender_name,
                        u.role as sender_role
                    FROM chat_messages cm
                    JOIN users u ON cm.sender_id = u.id
                    WHERE $base_where
                    ORDER BY cm.created_at DESC
                    LIMIT 50";
            $params = $base_params;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // For initial load we fetched DESC – reverse so oldest is on top
        if ($last_message_id === 0) {
            $messages = array_reverse($messages);
        }
        
        echo json_encode(['success' => true, 'messages' => $messages]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to load messages: ' . $e->getMessage());
    }
}

/**
 * Send a new message
 */
function sendMessage() {
    global $pdo, $current_user_id, $current_user_role, $target_roles;
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        throw new Exception('Invalid CSRF token');
    }
    
    $message = trim($_POST['message'] ?? '');
    
    if (empty($message)) {
        throw new Exception('Message cannot be empty');
    }
    
    if (strlen($message) > 1000) {
        throw new Exception('Message too long (max 1000 characters)');
    }
    
    try {
        // Use posted receiver_role if valid, otherwise fall back to first target role
        $posted_receiver = trim($_POST['receiver_role'] ?? '');
        if ($posted_receiver && in_array($posted_receiver, $target_roles)) {
            $receiver_role = $posted_receiver;
        } else {
            $receiver_role = $target_roles[0];
        }
        
        // Insert message
        $sql = "INSERT INTO chat_messages (sender_id, receiver_role, message, created_at) 
                VALUES (?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$current_user_id, $receiver_role, $message]);
        
        $message_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message_id' => $message_id,
            'message' => 'Message sent successfully'
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to send message: ' . $e->getMessage());
    }
}

/**
 * Get unread message count
 */
function getUnreadCount() {
    global $pdo, $current_user_id, $current_user_role, $target_roles;
    
    try {
        $placeholders = str_repeat('?,', count($target_roles) - 1) . '?';
        
        // Count unread messages for current user
        $sql = "SELECT COUNT(*) as unread_count
                FROM chat_messages cm
                JOIN users u ON cm.sender_id = u.id
                WHERE u.role IN ($placeholders)
                AND cm.receiver_role = ?
                AND cm.is_read = 0";
        
        $params = array_merge($target_roles, [$current_user_role]);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'unread_count' => intval($result['unread_count'])
        ]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to get unread count: ' . $e->getMessage());
    }
}

/**
 * Mark messages as read
 */
function markMessagesAsRead() {
    global $pdo, $current_user_id, $current_user_role, $target_roles;
    
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        throw new Exception('Invalid CSRF token');
    }
    
    try {
        $placeholders = str_repeat('?,', count($target_roles) - 1) . '?';
        
        // Mark messages as read
        $sql = "UPDATE chat_messages cm
                JOIN users u ON cm.sender_id = u.id
                SET cm.is_read = 1
                WHERE u.role IN ($placeholders)
                AND cm.receiver_role = ?
                AND cm.is_read = 0";
        
        $params = array_merge($target_roles, [$current_user_role]);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true]);
        
    } catch (Exception $e) {
        throw new Exception('Failed to mark messages as read: ' . $e->getMessage());
    }
}
?>