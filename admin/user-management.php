<?php
/**
 * User Management Module
 * St. Dominic Savio College - Visitor Management System
 */

require_once '../config/config.php';
require_once '../config/auth.php';

// Require admin role only
$auth->requireLogin('../login.php');
if (!$auth->hasRole(ROLE_ADMIN)) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

// Handle form submissions
if ($_POST) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $error_message = 'Security token validation failed.';
    } elseif (isset($_POST['create_user'])) {
        // Create new user
        $username = sanitizeInput($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $first_name = sanitizeInput($_POST['first_name'] ?? '');
        $last_name = sanitizeInput($_POST['last_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $role = sanitizeInput($_POST['role'] ?? '');
        
        if (empty($username) || empty($password) || empty($first_name) || empty($last_name) || empty($role)) {
            $error_message = 'Please fill in all required fields.';
        } elseif (strlen($password) < 6) {
            $error_message = 'Password must be at least 6 characters long.';
        } elseif (!in_array($role, [ROLE_ADMIN, ROLE_SUPERVISOR, ROLE_GUARD])) {
            $error_message = 'Invalid user role selected.';
        } else {
            try {
                // Check if username already exists
                $existing = $db->fetch("SELECT id FROM users WHERE username = ?", [$username]);
                if ($existing) {
                    $error_message = 'Username already exists. Please choose a different username.';
                } else {
                    // Hash password and create user
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    $result = $db->execute(
                        "INSERT INTO users (username, password, first_name, last_name, email, role, is_active, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?, 1, NOW())",
                        [$username, $hashed_password, $first_name, $last_name, $email, $role]
                    );
                    
                    if ($result > 0) {
                        $userId = $db->lastInsertId();
                        $auth->logActivity($_SESSION['user_id'], 'USER_CREATE', "Created user: $username ($role)");
                        $success_message = "User '$username' has been created successfully.";
                    } else {
                        $error_message = 'Failed to create user. Please try again.';
                    }
                }
            } catch (Exception $e) {
                $error_message = 'Error creating user: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['update_user'])) {
        // Update existing user
        $user_id = (int)($_POST['user_id'] ?? 0);
        $username = sanitizeInput($_POST['edit_username'] ?? '');
        $first_name = sanitizeInput($_POST['edit_first_name'] ?? '');
        $last_name = sanitizeInput($_POST['edit_last_name'] ?? '');
        $email = sanitizeInput($_POST['edit_email'] ?? '');
        $role = sanitizeInput($_POST['edit_role'] ?? '');
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $new_password = $_POST['new_password'] ?? '';
        
        if ($user_id <= 0 || empty($username) || empty($first_name) || empty($last_name)) {
            $error_message = 'Please fill in all required fields.';
        } else {
            try {
                // Check if username is taken by another user
                $existing = $db->fetch("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $user_id]);
                if ($existing) {
                    $error_message = 'Username already exists. Please choose a different username.';
                } else {
                    if (!empty($new_password)) {
                        // Update with new password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $result = $db->execute(
                            "UPDATE users SET username = ?, password = ?, first_name = ?, last_name = ?, 
                             email = ?, role = ?, is_active = ?, updated_at = NOW() WHERE id = ?",
                            [$username, $hashed_password, $first_name, $last_name, $email, $role, $is_active, $user_id]
                        );
                    } else {
                        // Update without changing password
                        $result = $db->execute(
                            "UPDATE users SET username = ?, first_name = ?, last_name = ?, 
                             email = ?, role = ?, is_active = ?, updated_at = NOW() WHERE id = ?",
                            [$username, $first_name, $last_name, $email, $role, $is_active, $user_id]
                        );
                    }
                    
                    if ($result >= 0) {
                        $auth->logActivity($_SESSION['user_id'], 'USER_UPDATE', "Updated user: $username");
                        $success_message = "User '$username' has been updated successfully.";
                    } else {
                        $error_message = 'Failed to update user. Please try again.';
                    }
                }
            } catch (Exception $e) {
                $error_message = 'Error updating user: ' . $e->getMessage();
            }
        }
    } elseif (isset($_POST['delete_user'])) {
        // Delete user (soft delete - set inactive)
        $user_id = (int)($_POST['delete_user_id'] ?? 0);
        
        if ($user_id <= 0) {
            $error_message = 'Invalid user ID.';
        } elseif ($user_id == $_SESSION['user_id']) {
            $error_message = 'You cannot delete your own account.';
        } else {
            try {
                $user = $db->fetch("SELECT username FROM users WHERE id = ?", [$user_id]);
                $result = $db->execute("UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?", [$user_id]);
                
                if ($result > 0) {
                    $auth->logActivity($_SESSION['user_id'], 'USER_DELETE', "Deactivated user: " . $user['username']);
                    $success_message = 'User has been deactivated successfully.';
                } else {
                    $error_message = 'Failed to deactivate user.';
                }
            } catch (Exception $e) {
                $error_message = 'Error deactivating user: ' . $e->getMessage();
            }
        }
    }
}

// Get all users
try {
    $users = $db->fetchAll("
        SELECT id, username, first_name, last_name, email, role, is_active, 
               created_at, updated_at, last_login
        FROM users 
        ORDER BY created_at DESC
    ");
    
    // Calculate statistics
    $stats = [
        'total_users' => count($users),
        'active_users' => count(array_filter($users, fn($u) => $u['is_active'])),
        'admin_count' => count(array_filter($users, fn($u) => $u['role'] === ROLE_ADMIN)),
        'supervisor_count' => count(array_filter($users, fn($u) => $u['role'] === ROLE_SUPERVISOR)),
        'guard_count' => count(array_filter($users, fn($u) => $u['role'] === ROLE_GUARD)),
    ];
} catch (Exception $e) {
    $error_message = 'Error loading users: ' . $e->getMessage();
    $users = [];
    $stats = ['total_users' => 0, 'active_users' => 0, 'admin_count' => 0, 'supervisor_count' => 0, 'guard_count' => 0];
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/vendor/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/datatables.min.css" rel="stylesheet">
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
            background: var(--gray-50);
        }

        .content-section {
            margin-bottom: 1.5rem;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
        }

        .stats-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stats-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .stats-card .card-body {
            padding: 1.5rem;
            position: relative;
        }

        .stats-icon-bg {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 1rem;
        }

        .stats-number {
            font-family: 'Poppins', sans-serif;
            font-size: 2.25rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.5rem;
            color: var(--gray-900);
        }

        .stats-label {
            color: var(--gray-600);
            font-size: 0.875rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .content-card, .user-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }

        .content-card:hover, .user-card:hover {
            box-shadow: var(--shadow-md);
        }

        .content-card .card-header, .user-card .card-header {
            background: white;
            color: var(--gray-900);
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            border-radius: 12px 12px 0 0;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            border-bottom: 2px solid var(--border-color);
            color: var(--gray-700);
            font-weight: 600;
            font-size: 0.8125rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 1rem 0.75rem;
        }

        .table tbody td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
            color: var(--gray-900);
            font-size: 0.875rem;
        }

        .table tbody tr {
            border-bottom: 1px solid var(--gray-100);
            transition: background-color 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: var(--gray-50);
        }

        .badge {
            padding: 0.375rem 0.75rem;
            font-weight: 600;
            font-size: 0.75rem;
            border-radius: 6px;
        }

        .role-badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
            border-radius: 6px;
        }

        .role-admin { background: var(--danger); }
        .role-supervisor { background: var(--warning); }
        .role-guard { background: var(--primary); }
        .status-active { color: var(--success); }
        .status-inactive { color: var(--danger); }

        .btn-primary {
            background: var(--primary);
            border: none;
            border-radius: 8px;
            padding: 0.625rem 1.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-light {
            background: white;
            border: 1px solid var(--border-color);
            color: var(--gray-700);
            border-radius: 8px;
            padding: 0.625rem 1.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }

        .btn-light:hover {
            background: var(--gray-50);
            border-color: var(--gray-300);
            color: var(--gray-900);
        }

        .btn-sm {
            padding: 0.4rem 0.75rem;
            font-size: 0.8125rem;
            border-radius: 6px;
        }

        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
        }

        .btn-outline-danger {
            color: var(--danger);
            border-color: var(--danger);
        }

        .btn-outline-danger:hover {
            background: var(--danger);
            border-color: var(--danger);
            color: white;
        }

        .btn-secondary {
            background: var(--gray-500);
            border: none;
            color: white;
            border-radius: 8px;
        }

        .btn-secondary:hover {
            background: var(--gray-600);
        }

        .btn-danger {
            background: var(--danger);
            border: none;
            color: white;
            border-radius: 8px;
        }

        .btn-danger:hover {
            background: #dc2626;
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

        .form-label {
            color: var(--gray-700);
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            background: var(--gray-50);
        }

        .modal-title {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            color: var(--gray-900);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid var(--border-color);
            padding: 1rem 1.5rem;
            background: var(--gray-50);
        }

        .alert {
            border-radius: 12px;
            border: none;
            border-left: 4px solid;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background: #fef2f2;
            border-left-color: var(--danger);
            color: #991b1b;
        }

        .alert-success {
            background: #f0fdf4;
            border-left-color: var(--success);
            color: #166534;
        }

        h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: var(--primary);
        }

        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .stats-grid {
                gap: 1rem;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem;
            }

            .page-header h2 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }

            .stats-number {
                font-size: 1.875rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col">
                    <h2 class="mb-0">
                        <i class="fas fa-users-cog me-2"></i>User Management
                    </h2>
                    <p class="mb-0 subtitle">Manage system user accounts and permissions</p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-light" data-bs-toggle="modal" data-bs-target="#createUserModal">
                        <i class="fas fa-plus me-2"></i>Create New User
                    </button>
                </div>
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
        
        <!-- User Statistics -->
        <section class="content-section">
        <div class="stats-grid">
            <div class="card stats-card">
                <div class="card-body">
                    <div class="stats-icon-bg" style="background: rgba(6, 150, 104, 0.1); color: var(--primary);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="stats-label">Total Users</div>
                </div>
            </div>
            <div class="card stats-card">
                <div class="card-body">
                    <div class="stats-icon-bg" style="background: rgba(16, 185, 129, 0.1); color: var(--success);">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($stats['active_users']); ?></div>
                    <div class="stats-label">Active Users</div>
                </div>
            </div>
            <div class="card stats-card">
                <div class="card-body">
                    <div class="stats-icon-bg" style="background: rgba(239, 68, 68, 0.1); color: var(--danger);">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($stats['admin_count']); ?></div>
                    <div class="stats-label">Administrators</div>
                </div>
            </div>
            <div class="card stats-card">
                <div class="card-body">
                    <div class="stats-icon-bg" style="background: rgba(59, 130, 246, 0.1); color: var(--info);">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($stats['supervisor_count'] + $stats['guard_count']); ?></div>
                    <div class="stats-label">Staff Members</div>
                </div>
            </div>
        </div>
        </section>
        
        <!-- Users List -->
        <section class="content-section">
        <div class="card user-card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>System Users
                    <span class="badge bg-primary ms-2"><?php echo count($users); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                        <p class="text-muted mb-3">No users found in the system.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                            <i class="fas fa-plus me-2"></i>Create First User
                        </button>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="usersTable">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                            <span class="badge bg-info ms-1">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email'] ?? '—'); ?></td>
                                    <td>
                                        <span class="badge role-<?php echo $user['role']; ?> role-badge">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                            <i class="fas fa-circle me-1"></i>
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $user['last_login'] ? formatDisplayDateTime($user['last_login']) : 'Never'; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary me-1" 
                                                onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        </section>
        </div>
    </div>
    
    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username *</label>
                                    <input type="text" class="form-control" name="username" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Password *</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                            </div>
                        </div>
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
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" required>
                                <option value="">Select Role</option>
                                <option value="<?php echo ROLE_ADMIN; ?>">Administrator</option>
                                <option value="<?php echo ROLE_SUPERVISOR; ?>">Supervisor</option>
                                <option value="<?php echo ROLE_GUARD; ?>">Guard</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editUserForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Username *</label>
                                    <input type="text" class="form-control" name="edit_username" id="edit_username" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-control" name="new_password" id="new_password">
                                    <small class="text-muted">Leave blank to keep current password</small>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">First Name *</label>
                                    <input type="text" class="form-control" name="edit_first_name" id="edit_first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Name *</label>
                                    <input type="text" class="form-control" name="edit_last_name" id="edit_last_name" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="edit_email" id="edit_email">
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Role *</label>
                                    <select class="form-select" name="edit_role" id="edit_role" required>
                                        <option value="<?php echo ROLE_ADMIN; ?>">Administrator</option>
                                        <option value="<?php echo ROLE_SUPERVISOR; ?>">Supervisor</option>
                                        <option value="<?php echo ROLE_GUARD; ?>">Guard</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1">
                                        <label class="form-check-label" for="is_active">
                                            Account Active
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_user" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deactivation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to deactivate user <strong id="deleteUsername"></strong>?</p>
                    <p class="text-muted">The user will no longer be able to log in, but their data will be preserved.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="delete_user_id" id="deleteUserId">
                        <button type="submit" name="delete_user" class="btn btn-danger">Deactivate User</button>
                    </form>
                </div>
            </div>
        </div>
        </div>
        </div>
    </div>
    
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/datatables.min.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            // Initialize DataTable only if table exists
            if ($('#usersTable').length) {
                $('#usersTable').DataTable({
                    order: [[0, 'asc']],
                    pageLength: 25,
                    language: {
                        search: "_INPUT_",
                        searchPlaceholder: "Search users..."
                    }
                });
            }
        });
        
        // Edit user function
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_first_name').value = user.first_name;
            document.getElementById('edit_last_name').value = user.last_name;
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('is_active').checked = user.is_active == 1;
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }
        
        // Confirm delete function
        function confirmDelete(userId, username) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUsername').textContent = username;
            
            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        }
    </script>
</body>
</html>