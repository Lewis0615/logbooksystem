<?php
/**
 * Guard Profile Settings
 * St. Dominic Savio College - Visitor Management System
 */

require_once '../config/config.php';
require_once '../config/auth.php';

// Check authentication - guard access only
$auth->requireLogin('login.php');
if (!$auth->hasRole(ROLE_GUARD)) {
    header('Location: checkin.php');
    exit();
}

$error_message = '';
$success_message = '';

// Get current user data
$user_id = $_SESSION['user_id'];
try {
    $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
    if (!$user) {
        header('Location: login.php');
        exit();
    }
} catch (Exception $e) {
    $error_message = 'Error loading profile data: ' . $e->getMessage();
}

// Handle profile update
if ($_POST && isset($_POST['update_profile'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $error_message = 'Security token validation failed.';
    } else {
        $first_name = sanitizeInput($_POST['first_name'] ?? '');
        $last_name = sanitizeInput($_POST['last_name'] ?? '');
        $email = sanitizeInput($_POST['email'] ?? '');
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($first_name) || empty($last_name)) {
            $error_message = 'First name and last name are required.';
        } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please enter a valid email address.';
        } elseif (!empty($new_password)) {
            // Password change validation
            if (empty($current_password)) {
                $error_message = 'Current password is required to set a new password.';
            } elseif (!password_verify($current_password, $user['password'])) {
                $error_message = 'Current password is incorrect.';
            } elseif (strlen($new_password) < 6) {
                $error_message = 'New password must be at least 6 characters long.';
            } elseif ($new_password !== $confirm_password) {
                $error_message = 'New passwords do not match.';
            }
        } else {
            // If changing other details, verify current password
            if (!empty($current_password) && !password_verify($current_password, $user['password'])) {
                $error_message = 'Current password is incorrect.';
            }
        }
        
        if (empty($error_message)) {
            try {
                if (!empty($new_password)) {
                    // Update with new password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $db->execute(
                        "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, password = ?, updated_at = NOW() WHERE id = ?",
                        [$first_name, $last_name, $email, $phone, $hashed_password, $user_id]
                    );
                } else {
                    // Update without changing password
                    $db->execute(
                        "UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?",
                        [$first_name, $last_name, $email, $phone, $user_id]
                    );
                }
                
                // Update session data
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;
                
                // Log the activity
                $auth->logActivity($user_id, 'PROFILE_UPDATED', 'Guard updated profile information');
                
                $success_message = 'Profile updated successfully!';
                
                // Refresh user data
                $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$user_id]);
                
            } catch (Exception $e) {
                $error_message = 'Error updating profile: ' . $e->getMessage();
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
    <title>Profile Settings</title>
    <link rel="icon" type="image/png" href="/logbooksystem/assets/images/sdsclogo.png">
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,400&display=swap" rel="stylesheet">
    <link href="../assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/vendor/font-awesome.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/guard-profile.css">

</head>
<body>
    <?php include '../includes/guard-sidebar.php'; ?>

    <div class="main-content">
        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="page-header-inner">
                <div>
                    <h2>
                        <i class="fas fa-user-cog"></i>
                        Profile Settings
                    </h2>
                    <p class="subtitle">Manage your personal account information</p>
                </div>
                <div class="secure-badge">
                    <div class="secure-dot"></div>
                    Secure Area
                </div>
            </div>
        </div>

        <!-- ALERTS -->
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8 mx-auto">
                <!-- PROFILE OVERVIEW -->
                <div class="profile-avatar-section">
                    <div class="profile-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="profile-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                    <div class="profile-role">
                        <i class="fas fa-shield-alt"></i>
                        Security Guard
                    </div>
                </div>

                <!-- PROFILE FORM -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-header-title">
                            <div class="card-header-icon">
                                <i class="fas fa-user-edit"></i>
                            </div>
                            Personal Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">First Name *</label>
                                        <input type="text" class="form-control" name="first_name" 
                                               value="<?php echo htmlspecialchars($user['first_name']); ?>" 
                                               placeholder="Enter your first name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" name="last_name" 
                                               value="<?php echo htmlspecialchars($user['last_name']); ?>" 
                                               placeholder="Enter your last name" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Email Address</label>
                                        <input type="email" class="form-control" name="email" 
                                               value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" 
                                               placeholder="Enter your email address">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                               placeholder="Enter your phone number">
                                    </div>
                                </div>
                            </div>

                            <div class="section-divider"></div>

                            <!-- PASSWORD SECTION -->
                            <div class="security-warning">
                                <h6><i class="fas fa-shield-alt"></i> Password Security</h6>
                                <p>Leave password fields empty if you don't want to change your password. Your current password is required for any changes to your account.</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" 
                                       placeholder="Enter your current password to save changes">
                                <small class="form-text text-muted mt-1">Required for any profile changes</small>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">New Password</label>
                                        <input type="password" class="form-control" name="new_password" 
                                               placeholder="Enter new password (optional)">
                                        <small class="form-text text-muted mt-1">Minimum 6 characters</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" name="confirm_password" 
                                               placeholder="Confirm new password">
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex gap-3 justify-content-end">
                                <button type="button" class="btn btn-secondary" onclick="window.location.href='guard-visitor-management.php'">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ACCOUNT INFO -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-header-title">
                            <div class="card-header-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            Account Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div style="padding: 16px; background: var(--gray-50); border-radius: var(--radius-sm); border: 1px solid var(--border-lt);">
                                    <div style="font-size: 0.75rem; font-weight: 600; color: var(--gray-500); text-transform: uppercase; margin-bottom: 4px;">User ID</div>
                                    <div style="font-size: 0.9rem; font-weight: 600; color: var(--gray-800);"><?php echo $user['id']; ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div style="padding: 16px; background: var(--gray-50); border-radius: var(--radius-sm); border: 1px solid var(--border-lt);">
                                    <div style="font-size: 0.75rem; font-weight: 600; color: var(--gray-500); text-transform: uppercase; margin-bottom: 4px;">Role</div>
                                    <div style="font-size: 0.9rem; font-weight: 600; color: var(--gray-800);">
                                        <i class="fas fa-shield-alt me-1 text-success"></i>
                                        <?php echo ucfirst($user['role']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <div style="padding: 16px; background: var(--gray-50); border-radius: var(--radius-sm); border: 1px solid var(--border-lt);">
                                    <div style="font-size: 0.75rem; font-weight: 600; color: var(--gray-500); text-transform: uppercase; margin-bottom: 4px;">Account Created</div>
                                    <div style="font-size: 0.9rem; font-weight: 600; color: var(--gray-800);">
                                        <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div style="padding: 16px; background: var(--gray-50); border-radius: var(--radius-sm); border: 1px solid var(--border-lt);">
                                    <div style="font-size: 0.75rem; font-weight: 600; color: var(--gray-500); text-transform: uppercase; margin-bottom: 4px;">Last Updated</div>
                                    <div style="font-size: 0.9rem; font-weight: 600; color: var(--gray-800);">
                                        <?php echo $user['updated_at'] ? date('M j, Y', strtotime($user['updated_at'])) : 'Never'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Password confirmation validation
            $('input[name="confirm_password"]').on('input', function() {
                const newPassword = $('input[name="new_password"]').val();
                const confirmPassword = $(this).val();
                
                if (newPassword && confirmPassword) {
                    if (newPassword === confirmPassword) {
                        $(this).css('border-color', 'var(--green-400)');
                    } else {
                        $(this).css('border-color', 'var(--accent-red)');
                    }
                } else {
                    $(this).css('border-color', 'var(--border)');
                }
            });
            
            // Form validation
            $('form').on('submit', function(e) {
                const newPassword = $('input[name="new_password"]').val();
                const confirmPassword = $('input[name="confirm_password"]').val();
                const currentPassword = $('input[name="current_password"]').val();
                
                if (newPassword && !confirmPassword) {
                    e.preventDefault();
                    alert('Please confirm your new password.');
                    return false;
                }
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('New passwords do not match.');
                    return false;
                }
                
                if (!currentPassword) {
                    e.preventDefault();
                    alert('Current password is required to save changes.');
                    return false;
                }
            });
        });
    </script>
    
    <?php include '../includes/chat-widget.php'; ?>
</body>
</html>