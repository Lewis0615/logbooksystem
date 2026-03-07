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
    <style>
        :root {
            --green-950: #071a10;
            --green-900: #0d2b1a;
            --green-800: #163d27;
            --green-700: #1e5235;
            --green-600: #256642;
            --green-500: #2d7a4f;
            --green-400: #3a9962;
            --green-300: #52c47d;
            --green-200: #86dba4;
            --green-100: #d4f4e2;
            --green-50:  #edfaf4;

            --accent-blue:    #3b82f6;
            --accent-blue-lt: #dbeafe;
            --accent-teal:    #0d9488;
            --accent-teal-lt: #ccfbf1;
            --accent-orange:  #f59e0b;
            --accent-orange-lt: #fef3c7;
            --accent-red:     #ef4444;
            --accent-red-lt:  #fee2e2;

            --gray-25:  #fcfcfd;
            --gray-50:  #f8faf9;
            --gray-100: #f0f4f2;
            --gray-200: #e2eae6;
            --gray-300: #c8d6cf;
            --gray-400: #94a8a0;
            --gray-500: #6b7f78;
            --gray-600: #4d5e58;
            --gray-700: #354039;
            --gray-800: #1f2925;
            --gray-900: #111915;

            --border:   #e2eae6;
            --border-lt:#f0f4f2;

            --shadow-xs: 0 1px 2px rgba(0,0,0,.05);
            --shadow-sm: 0 1px 4px rgba(0,0,0,.08), 0 1px 2px rgba(0,0,0,.04);
            --shadow-md: 0 4px 16px rgba(0,0,0,.10), 0 2px 6px rgba(0,0,0,.06);
            --shadow-lg: 0 12px 40px rgba(0,0,0,.13), 0 4px 12px rgba(0,0,0,.06);

            --radius:    14px;
            --radius-sm: 8px;
            --radius-xs: 5px;
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            font-family: 'DM Sans', -apple-system, sans-serif;
            background: var(--gray-50);
            color: var(--gray-800);
            font-size: 14px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }

        /* ─── LAYOUT ─── */
        .main-content {
            margin-left: 260px;
            padding: 28px 30px;
            min-height: 100vh;
            transition: margin-left .3s ease;
        }

        /* ─── PAGE HEADER ─── */
        .page-header {
            background: linear-gradient(135deg, var(--green-700) 0%, var(--green-600) 55%, var(--green-500) 100%);
            border-radius: var(--radius);
            padding: 28px 32px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -60px; right: -60px;
            width: 220px; height: 220px;
            border-radius: 50%;
            background: rgba(255,255,255,.05);
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -80px; right: 120px;
            width: 180px; height: 180px;
            border-radius: 50%;
            background: rgba(255,255,255,.03);
        }

        .page-header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .page-header h2 {
            font-family: 'Work Sans', sans-serif;
            font-size: 1.8rem;
            font-weight: 800;
            color: #fff;
            margin: 0 0 4px;
            letter-spacing: -.02em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header .subtitle {
            color: rgba(255,255,255,.7);
            font-size: .9rem;
            margin: 0;
        }

        .secure-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(255,255,255,.15);
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 20px;
            padding: 7px 16px;
            font-size: .8rem;
            font-weight: 600;
            color: #fff;
            backdrop-filter: blur(4px);
        }

        .secure-dot {
            width: 8px; height: 8px;
            background: #4ade80;
            border-radius: 50%;
            box-shadow: 0 0 0 2px rgba(74,222,128,.3);
            animation: securePulse 2s infinite;
        }

        @keyframes securePulse {
            0%, 100% { box-shadow: 0 0 0 2px rgba(74,222,128,.3); }
            50%       { box-shadow: 0 0 0 6px rgba(74,222,128,.08); }
        }

        /* ─── ALERTS ─── */
        .alert {
            border-radius: var(--radius-sm);
            border: none;
            padding: 13px 18px;
            margin-bottom: 20px;
            font-size: .875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-danger  { background: var(--accent-red-lt);    color: #991b1b; border-left: 3px solid var(--accent-red); }
        .alert-success { background: var(--green-50);          color: var(--green-700); border-left: 3px solid var(--green-400); }

        /* ─── CARDS ─── */
        .card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            animation: fadeUp .4s ease .25s both;
        }

        .card-header {
            background: var(--gray-50);
            border-bottom: 1px solid var(--border-lt);
            padding: 20px 24px;
        }

        .card-header-title {
            font-family: 'Work Sans', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }

        .card-header-icon {
            width: 40px; height: 40px;
            background: var(--green-100);
            color: var(--green-600);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 16px;
        }

        .card-body {
            padding: 24px;
        }

        /* ─── FORMS ─── */
        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            font-size: .85rem;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem;
            color: var(--gray-800);
            background: #fff;
            transition: all .2s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--green-400);
            box-shadow: 0 0 0 3px rgba(58,153,98,.12);
            background: var(--gray-25);
        }

        .form-control:not(:placeholder-shown) {
            background: var(--gray-25);
        }

        /* ─── BUTTONS ─── */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: var(--radius-sm);
            font-family: 'DM Sans', sans-serif;
            font-size: .9rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all .2s;
            border: 2px solid transparent;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--green-500), var(--green-400));
            color: #fff;
            box-shadow: 0 2px 8px rgba(45,122,79,.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 16px rgba(45,122,79,.4);
        }

        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border-color: var(--border);
        }

        .btn-secondary:hover {
            background: var(--gray-200);
            color: var(--gray-800);
        }

        /* ─── PROFILE AVATAR ─── */
        .profile-avatar-section {
            text-align: center;
            padding: 32px;
            background: linear-gradient(135deg, var(--green-50), #fff);
            border-radius: var(--radius);
            margin-bottom: 24px;
            border: 2px solid var(--green-100);
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, var(--green-500), var(--green-400));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            font-size: 48px;
            color: #fff;
            box-shadow: 0 8px 32px rgba(45,122,79,.2);
            position: relative;
        }

        .profile-avatar::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--green-400), var(--green-300));
            z-index: -1;
            opacity: 0.3;
        }

        .profile-name {
            font-family: 'Work Sans', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 4px;
        }

        .profile-role {
            color: var(--green-600);
            font-weight: 600;
            font-size: .9rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        /* ─── SECURITY SECTION ─── */
        .security-warning {
            background: var(--accent-orange-lt);
            border: 2px solid var(--accent-orange);
            border-radius: var(--radius-sm);
            padding: 16px;
            margin-bottom: 24px;
        }

        .security-warning h6 {
            color: #92400e;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .security-warning p {
            color: #92400e;
            font-size: .85rem;
            margin: 0;
            line-height: 1.6;
        }

        /* ─── DIVIDERS ─── */
        .section-divider {
            height: 2px;
            background: linear-gradient(90deg, var(--border), transparent);
            margin: 32px 0;
            border-radius: 2px;
        }

        /* ─── ANIMATIONS ─── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 1200px) {
            .main-content { margin-left: 0; padding: 16px; }
        }

        @media (max-width: 640px) {
            .page-header h2 { font-size: 1.4rem; }
            .profile-avatar { width: 80px; height: 80px; font-size: 32px; }
        }
    </style>
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