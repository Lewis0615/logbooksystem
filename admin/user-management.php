<?php
/**
 * User Management Module
 * St. Dominic Savio College - Visitor Management System
 */

require_once '../config/config.php';
require_once '../config/auth.php';

// Require admin or supervisor role
$auth->requireLogin('login.php');
if (!$auth->hasRole(ROLE_ADMIN) && !$auth->hasRole(ROLE_SUPERVISOR)) {
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
    <title>User Management</title>
    <link rel="icon" type="image/png" href="/logbooksystem/assets/images/sdsclogo.png">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/vendor/font-awesome.min.css" rel="stylesheet">
    <link href="../assets/css/datatables.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/user-management.css">

</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <div class="page-header-inner">
                <div>
                    <h2>
                        <i class="fas fa-users-cog"></i>
                        User Management
                    </h2>
                    <p class="subtitle">Manage system user accounts and permissions</p>
                </div>
                <div>
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
                <i class="fas fa-check-circle"></i><?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <!-- Users List -->
        <section class="content-section">
        <div class="card">
            <div class="card-header">
                <h5>
                    <i class="fas fa-list"></i>System Users
                    <span class="badge bg-primary ms-2"><?php echo count($users); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <i class="fas fa-users-slash fa-3x"></i>
                        <h5>No Users Found</h5>
                        <p class="text-muted">No system users have been created yet.</p>
                        <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#createUserModal">
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
    
    <?php include '../includes/chat-widget.php'; ?>
</body>
</html>