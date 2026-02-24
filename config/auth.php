<?php
/**
 * Authentication and Security Functions
 * St. Dominic Savio College - Visitor Management System
 */

require_once 'config.php';
require_once 'database.php';

class Auth {
    private $db;
    
    public function __construct() {
        global $db;
        $this->db = $db;
    }
    
    /**
     * Login user with username and password
     */
    public function login($username, $password) {
        try {
            // Check for too many login attempts
            $this->checkLoginAttempts($username);
            
            $sql = "SELECT id, username, password, role, first_name, last_name, 
                           is_active, last_login FROM users WHERE username = ? AND is_active = 1";
            $user = $this->db->fetch($sql, [$username]);
            
            if ($user && password_verify($password, $user['password'])) {
                // Successful login
                $this->clearLoginAttempts($username);
                $this->updateLastLogin($user['id']);
                $this->setSession($user);
                $this->logActivity($user['id'], 'LOGIN', 'User logged in successfully');
                return ['success' => true, 'user' => $user];
            } else {
                // Failed login
                $this->recordLoginAttempt($username);
                return ['success' => false, 'message' => 'Invalid username or password'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Login error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Logout current user
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            // Only log if user still exists
            $user_exists = $this->db->fetch("SELECT id FROM users WHERE id = ?", [$_SESSION['user_id']]);
            if ($user_exists) {
                $this->logActivity($_SESSION['user_id'], 'LOGOUT', 'User logged out');
            } else {
                error_log("Logout: User ID {$_SESSION['user_id']} no longer exists, skipping activity log");
            }
        }
        $this->clearSession();
        return true;
    }
    
    /**
     * Clear session data safely
     */
    private function clearSession() {
        session_unset();
        session_destroy();
        session_start(); // Restart with clean session
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($role) {
        return $this->isLoggedIn() && $_SESSION['user_role'] === $role;
    }
    
    /**
     * Get current user information
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        $sql = "SELECT id, username, role, first_name, last_name FROM users WHERE id = ?";
        $user = $this->db->fetch($sql, [$_SESSION['user_id']]);
        
        // If session user doesn't exist in database, clear the session
        if (!$user) {
            error_log("Invalid session: User ID {$_SESSION['user_id']} not found in database");
            $this->clearSession();
            return null;
        }
        
        return $user;
    }
    
    /**
     * Set user session
     */
    private function setSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
        $_SESSION['login_time'] = time();
    }
    
    /**
     * Check for too many login attempts
     */
    private function checkLoginAttempts($username) {
        $sql = "SELECT COUNT(*) as attempts FROM login_attempts 
                WHERE username = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)";
        $result = $this->db->fetch($sql, [$username, LOCKOUT_DURATION]);
        
        if ($result['attempts'] >= MAX_LOGIN_ATTEMPTS) {
            throw new Exception('Account temporarily locked due to too many failed attempts. Please try again later.');
        }
    }
    
    /**
     * Record failed login attempt
     */
    private function recordLoginAttempt($username) {
        $sql = "INSERT INTO login_attempts (username, ip_address, attempt_time) VALUES (?, ?, NOW())";
        $this->db->execute($sql, [$username, $_SERVER['REMOTE_ADDR']]);
    }
    
    /**
     * Clear login attempts for user
     */
    private function clearLoginAttempts($username) {
        $sql = "DELETE FROM login_attempts WHERE username = ?";
        $this->db->execute($sql, [$username]);
    }
    
    /**
     * Update last login time
     */
    private function updateLastLogin($userId) {
        $sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $this->db->execute($sql, [$userId]);
    }
    
    /**
     * Log user activity
     */
    public function logActivity($userId, $action, $description, $additionalData = null) {
        try {
            // Validate that the user exists before logging
            if ($userId !== null) {
                $user_exists = $this->db->fetch("SELECT id FROM users WHERE id = ?", [$userId]);
                if (!$user_exists) {
                    // User doesn't exist, log with NULL user_id
                    error_log("Warning: Attempted to log activity for non-existent user ID: $userId");
                    $userId = null;
                }
            }
            
            $sql = "INSERT INTO activity_logs (user_id, action, description, additional_data, 
                                              ip_address, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())";
            $this->db->execute($sql, [
                $userId, 
                $action, 
                $description, 
                $additionalData ? json_encode($additionalData) : null,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            // Log activity errors should not break the application
            error_log("Activity log error: " . $e->getMessage());
        }
    }
    
    /**
     * Check session timeout
     */
    public function checkSessionTimeout() {
        if ($this->isLoggedIn() && isset($_SESSION['login_time'])) {
            // Check if the session user still exists in database
            if (isset($_SESSION['user_id'])) {
                $user_exists = $this->db->fetch("SELECT id FROM users WHERE id = ?", [$_SESSION['user_id']]);
                if (!$user_exists) {
                    error_log("Session cleanup: User ID {$_SESSION['user_id']} no longer exists");
                    $this->clearSession();
                    return false;
                }
            }
            
            // Check timeout
            if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
                $this->logout();
                return false;
            }
        }
        return true;
    }
    
    /**
     * Require login (redirect if not logged in)
     */
    public function requireLogin($redirectUrl = 'login.php') {
        if (!$this->isLoggedIn() || !$this->checkSessionTimeout()) {
            header("Location: $redirectUrl");
            exit();
        }
    }
    
    /**
     * Require specific role
     */
    public function requireRole($role, $redirectUrl = 'index.php') {
        $this->requireLogin();
        if (!$this->hasRole($role)) {
            header("Location: $redirectUrl");
            exit();
        }
    }
}

// Global auth instance
$auth = new Auth();

// CSRF Protection Functions
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>