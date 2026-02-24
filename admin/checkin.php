<?php
/**
 * Visitor Check-In Module
 * St. Dominic Savio College - Visitor Management System
 */

require_once '../config/config.php';
require_once '../config/auth.php';

// Check authentication
$auth->requireLogin('login.php');

$error_message = '';
$success_message = '';

// Handle form submission
if ($_POST && isset($_POST['checkin_visitor'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $error_message = 'Security token validation failed.';
    } else {
        // Process check-in data
        $first_name = sanitizeInput($_POST['first_name'] ?? '');
        $last_name = sanitizeInput($_POST['last_name'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $company = sanitizeInput($_POST['company'] ?? '');
        $person_to_visit = sanitizeInput($_POST['person_to_visit'] ?? '');
        $purpose = sanitizeInput($_POST['purpose'] ?? '');
        $expected_duration = (int)($_POST['expected_duration'] ?? DEFAULT_VISIT_DURATION);
        
        if (empty($first_name) || empty($last_name) || empty($phone)) {
            $error_message = 'Please fill in all required fields (Name and Phone).';
        } else {
            try {
                // Check if visitor exists
                $visitor = $db->fetch("SELECT * FROM visitors WHERE phone = ?", [$phone]);
                
                if (!$visitor) {
                    // Create new visitor
                    $db->execute(
                        "INSERT INTO visitors (first_name, last_name, phone, email, company_organization, created_at) 
                         VALUES (?, ?, ?, ?, ?, NOW())",
                        [$first_name, $last_name, $phone, $email, $company]
                    );
                    $visitor_id = $db->lastInsertId();
                } else {
                    $visitor_id = $visitor['id'];
                }
                
                // Check if visitor is blacklisted
                $blacklisted = $db->fetch(
                    "SELECT * FROM blacklist WHERE 
                     (visitor_id = ? OR phone = ?) AND status = 'active' 
                     AND (is_permanent = 1 OR expiry_date >= CURDATE())",
                    [$visitor_id, $phone]
                );
                
                if ($blacklisted) {
                    $error_message = 'This visitor is blacklisted and cannot be checked in.';
                    $auth->logActivity($_SESSION['user_id'], 'BLACKLIST_ATTEMPT', 
                        "Blocked blacklisted visitor: $first_name $last_name ($phone)");
                } else {
                    // Create visit record
                    $expected_checkout = date('Y-m-d H:i:s', strtotime("+$expected_duration minutes"));
                    $visitor_pass = generateVisitorPass();
                    
                    $db->execute(
                        "INSERT INTO visits (visitor_id, person_to_visit, purpose, visit_pass, 
                         check_in_time, expected_checkout_time, expected_duration, status, checked_in_by) 
                         VALUES (?, ?, ?, ?, NOW(), ?, ?, 'checked_in', ?)",
                        [$visitor_id, $person_to_visit, $purpose, $visitor_pass, 
                         $expected_checkout, $expected_duration, $_SESSION['user_id']]
                    );
                    
                    $auth->logActivity($_SESSION['user_id'], 'VISITOR_CHECKIN', 
                        "Checked in visitor: $first_name $last_name - Pass: $visitor_pass");
                    
                    $success_message = "Visitor checked in successfully! Pass: $visitor_pass";
                }
            } catch (Exception $e) {
                $error_message = 'Error checking in visitor: ' . $e->getMessage();
            }
        }
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Check-In - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/vendor/font-awesome.min.css" rel="stylesheet">
    <style>
        :root {
            /* Modern Green Palette */
            --primary: #069668;
            --primary-dark: #047857;
            --primary-light: #10b981;
            --primary-bg: #ecfdf5;
            --primary-hover: #059669;
            
            /* Neutral Colors */
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            
            /* Semantic Colors */
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            
            /* UI Colors */
            --border-color: #e5e7eb;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        * {
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--gray-50);
            color: var(--gray-900);
            font-size: 14px;
            line-height: 1.6;
        }

        .main-content {
            margin-left: 280px;
            padding: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
        }

        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            padding: 2rem;
            margin-bottom: 1.5rem;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            border-radius: 50%;
        }

        .page-header h2 {
            font-family: 'Poppins', sans-serif;
            font-size: 1.875rem;
            font-weight: 700;
            color: white;
            margin-bottom: 0.25rem;
            line-height: 1.2;
            letter-spacing: -0.025em;
        }

        .page-header .subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9375rem;
            font-weight: 400;
            margin-bottom: 0;
        }

        .checkin-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .checkin-card:hover {
            box-shadow: var(--shadow-md);
        }

        .checkin-card .card-header {
            background: white;
            color: var(--gray-900);
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }

        .checkin-card .card-header h5 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            margin: 0;
            font-size: 1rem;
            color: var(--gray-900);
        }

        .checkin-card .card-body {
            padding: 2rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 0.625rem 0.875rem;
            transition: all 0.2s ease;
            font-size: 0.875rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(6, 150, 104, 0.1);
            outline: none;
        }

        .btn-success {
            background: var(--success);
            border: none;
            border-radius: 8px;
            padding: 0.625rem 1.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .btn-success:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-secondary {
            border-radius: 8px;
            padding: 0.625rem 1.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: var(--gray-100);
            border: 1px solid var(--border-color);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
            border-color: var(--gray-300);
        }

        .alert {
            border-radius: 10px;
            border: 1px solid var(--border-color);
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background: #fef2f2;
            border-left: 3px solid var(--danger);
            color: var(--gray-900);
        }

        .alert-success {
            background: #ecfdf5;
            border-left: 3px solid var(--success);
            color: var(--gray-900);
        }

        h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .page-header {
                padding: 1.5rem;
            }

            .checkin-card .card-body {
                padding: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .page-header h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php 
    // Include appropriate sidebar based on user role
    if ($auth->hasRole(ROLE_GUARD)) {
        include '../includes/guard-sidebar.php';
    } else {
        include '../includes/admin-sidebar.php';
    }
    ?>
    
    <div class="main-content">
        <div class="page-header">
            <div class="container-fluid">
                <h2 class="mb-0">
                    <i class="fas fa-user-plus me-2"></i>Visitor Check-In
                </h2>
                <p class="mb-0 subtitle">Register new visitor arrival</p>
            </div>
        </div>
        
        <div class="container-fluid">
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="card checkin-card">
            <div class="card-header">
                <h5 class="mb-0">Visitor Information</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">First Name *</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Last Name *</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" class="form-control" name="phone" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Company/Organization</label>
                        <input type="text" class="form-control" name="company">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Person to Visit</label>
                                <input type="text" class="form-control" name="person_to_visit">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Purpose of Visit</label>
                                <select class="form-select" name="purpose">
                                    <option value="">Select Purpose</option>
                                    <?php foreach ($visit_purposes as $purpose): ?>
                                        <option value="<?php echo htmlspecialchars($purpose); ?>">
                                            <?php echo htmlspecialchars($purpose); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Expected Duration (minutes)</label>
                        <select class="form-select" name="expected_duration">
                            <option value="60">1 Hour</option>
                            <option value="120">2 Hours</option>
                            <option value="240">4 Hours</option>
                            <option value="480" selected>8 Hours (Full Day)</option>
                        </select>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="reset" class="btn btn-secondary me-md-2">Clear Form</button>
                        <button type="submit" name="checkin_visitor" class="btn btn-success">
                            <i class="fas fa-user-plus me-2"></i>Check In Visitor
                        </button>
                    </div>
                </form>
            </div>
        </div>
        </div>
    </div>
    
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>