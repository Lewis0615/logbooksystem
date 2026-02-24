<?php
/**
 * Application Configuration File
 * St. Dominic Savio College - Visitor Management System
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Application Settings
define('APP_NAME', 'St. Dominic Savio College - Visitor Management System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/logbooksystem');
define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB

// Security Settings
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_DURATION', 900); // 15 minutes

// Visitor Settings
define('DEFAULT_VISIT_DURATION', 480); // 8 hours in minutes
define('REQUIRE_PHOTO', false);
define('REQUIRE_ID_UPLOAD', false);
define('AUTO_CHECKOUT_HOURS', 24);

// Date and Time Settings
date_default_timezone_set('Asia/Manila');
define('DATE_FORMAT', 'Y-m-d');
define('TIME_FORMAT', 'H:i:s');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'F j, Y');
define('DISPLAY_TIME_FORMAT', 'g:i A');
define('DISPLAY_DATETIME_FORMAT', 'F j, Y g:i A');

// User Roles
define('ROLE_ADMIN', 'admin');
define('ROLE_GUARD', 'guard');
define('ROLE_SUPERVISOR', 'supervisor');

// Visit Purposes (can be modified via admin settings)
$visit_purposes = [
    'Meeting',
    'Interview',
    'Student Inquiry',
    'Parent Conference',
    'Delivery',
    'Maintenance',
    'Official Business',
    'Guest Speaker',
    'Event Attendance',
    'Other'
];

// Offices/Departments
$offices = [
    'Principal\'s Office',
    'Registrar\'s Office',
    'Accounting Office',
    'Student Affairs Office',
    'Library',
    'Computer Laboratory',
    'Science Laboratory',
    'Faculty Room',
    'Guidance Office',
    'Maintenance Office',
    'Canteen',
    'Other'
];

// Helper Functions
function formatDisplayDate($date) {
    return date(DISPLAY_DATE_FORMAT, strtotime($date));
}

function formatDisplayTime($time) {
    return date(DISPLAY_TIME_FORMAT, strtotime($time));
}

function formatDisplayDateTime($datetime) {
    return date(DISPLAY_DATETIME_FORMAT, strtotime($datetime));
}

function getCurrentDateTime() {
    return date(DATETIME_FORMAT);
}

function getCurrentDate() {
    return date(DATE_FORMAT);
}

function getCurrentTime() {
    return date(TIME_FORMAT);
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateVisitorPass() {
    return 'VP' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidPhone($phone) {
    return preg_match('/^[+]?[0-9]{10,15}$/', $phone);
}

// Include database configuration
require_once 'database.php';
?>