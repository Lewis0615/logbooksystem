<?php
/**
 * System Settings Module
 * St. Dominic Savio College - Visitor Management System
 */

require_once '../config/config.php';
require_once '../config/auth.php';

// Require admin role only
$auth->requireLogin('login.php');
if (!$auth->hasRole(ROLE_ADMIN)) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

// Handle settings update
if ($_POST && isset($_POST['update_settings'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!validateCSRFToken($csrf_token)) {
        $error_message = 'Security token validation failed.';
    } else {
        // Update system settings (this is a simplified version)
        $success_message = 'Settings updated successfully! (Note: This is a demo interface)';
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - <?php echo APP_NAME; ?></title>
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
            background: var(--gray-50);
        }

        .content-section {
            margin-bottom: 1.5rem;
        }

        /* Statistics Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .stat-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stat-card .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
        }

        .stat-card .stat-icon.icon-primary {
            background: var(--primary-bg);
            color: var(--primary);
        }

        .stat-card .stat-icon.icon-success {
            background: #ecfdf5;
            color: var(--success);
        }

        .stat-card .stat-icon.icon-info {
            background: #eff6ff;
            color: var(--info);
        }

        .stat-card .stat-value {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
            line-height: 1;
        }

        .stat-card .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
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

        .content-card, .settings-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }

        .content-card:hover, .settings-card:hover {
            box-shadow: var(--shadow-md);
        }

        .card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1.25rem 1.5rem;
            border-radius: 12px 12px 0 0;
        }

        .card-header h5 {
            margin-bottom: 0;
            font-size: 1.125rem;
            color: var(--gray-900);
        }

        .card-body {
            padding: 1.5rem;
        }

        .form-label {
            color: var(--gray-700);
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid var(--border-color);
            padding: 0.625rem 0.875rem;
            transition: all 0.2s ease;
            font-size: 0.875rem;
            color: var(--gray-900);
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(6, 150, 104, 0.1);
            outline: none;
        }

        .form-control:disabled, .form-control[readonly] {
            background-color: var(--gray-50);
            opacity: 1;
        }

        .form-check-input {
            border-radius: 4px;
            border: 1px solid var(--border-color);
        }

        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .form-check-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(6, 150, 104, 0.1);
        }

        .form-check-label {
            color: var(--gray-700);
            font-weight: 500;
            font-size: 0.875rem;
        }

        .btn {
            border-radius: 8px;
            padding: 0.625rem 1.5rem;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline-warning {
            background: transparent;
            border: 1px solid var(--warning);
            color: var(--warning);
        }

        .btn-outline-warning:hover {
            background: var(--warning);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-outline-info {
            background: transparent;
            border: 1px solid var(--info);
            color: var(--info);
        }

        .btn-outline-info:hover {
            background: var(--info);
            color: white;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .badge {
            padding: 0.375rem 0.75rem;
            font-weight: 600;
            font-size: 0.8125rem;
            border-radius: 6px;
        }

        .alert {
            border-radius: 8px;
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
            background: #ecfdf5;
            border-left-color: var(--success);
            color: #065f46;
        }

        .alert i {
            font-size: 1rem;
        }

        h5, h6 {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

        .badge-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .section-header {
            margin-bottom: 1rem;
        }

        .section-header h6 {
            color: var(--gray-900);
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .section-header p {
            color: var(--gray-600);
            font-size: 0.875rem;
            margin-bottom: 0;
        }

        hr {
            border: 0;
            border-top: 1px solid var(--border-color);
            margin: 1.5rem 0;
        }

        @media (max-width: 1200px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .page-header h2 {
                font-size: 1.5rem;
            }

            .stat-card .stat-value {
                font-size: 1.75rem;
            }

            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/admin-sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <h2 class="mb-0">
                <i class="fas fa-cogs me-2"></i>System Settings
            </h2>
            <p class="mb-0 subtitle">Configure system parameters and preferences</p>
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
        
        <!-- System Information -->
        <section class="content-section">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon icon-primary">
                        <i class="fas fa-code-branch"></i>
                    </div>
                    <div class="stat-value"><?php echo APP_VERSION; ?></div>
                    <div class="stat-label">System Version</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon icon-success">
                        <i class="fas fa-database"></i>
                    </div>
                    <div class="stat-value"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-label">Database Status</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon icon-info">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value">
                        <?php
                        try {
                            echo $db->fetch("SELECT COUNT(*) as count FROM users WHERE is_active = 1")['count'];
                        } catch (Exception $e) {
                            echo '—';
                        }
                        ?>
                    </div>
                    <div class="stat-label">Active Users</div>
                </div>
            </div>
        </section>
        
        <!-- Settings Form -->
        <section class="content-section">
            <div class="card settings-card">
                <div class="card-header">
                    <h5><i class="fas fa-sliders-h me-2"></i>General Settings</h5>
                </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Application Name</label>
                                <input type="text" class="form-control" value="<?php echo APP_NAME; ?>" readonly>
                                <small class="text-muted">Defined in config.php</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Default Visit Duration (minutes)</label>
                                <select class="form-select" name="default_duration">
                                    <option value="60">1 Hour</option>
                                    <option value="120">2 Hours</option>
                                    <option value="240">4 Hours</option>
                                    <option value="480" <?php echo DEFAULT_VISIT_DURATION == 480 ? 'selected' : ''; ?>>8 Hours</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Session Timeout (seconds)</label>
                                <input type="number" class="form-control" value="<?php echo SESSION_TIMEOUT; ?>" readonly>
                                <small class="text-muted">Defined in config.php</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Max Login Attempts</label>
                                <input type="number" class="form-control" value="<?php echo MAX_LOGIN_ATTEMPTS; ?>" readonly>
                                <small class="text-muted">Defined in config.php</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">
                            <label class="form-label">Visitor Requirements</label>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="require_photo" id="require_photo" <?php echo REQUIRE_PHOTO ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="require_photo">Require Visitor Photo</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="require_id_upload" id="require_id_upload" <?php echo REQUIRE_ID_UPLOAD ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="require_id_upload">Require ID Upload</label>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="my-4">
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" name="update_settings" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
        </section>
        
        <!-- Visit Purposes Management -->
        <section class="content-section">
            <div class="card settings-card">
                <div class="card-header">
                    <h5><i class="fas fa-tags me-2"></i>Visit Purposes</h5>
                </div>
                <div class="card-body">
                    <div class="badge-list">
                        <?php foreach ($visit_purposes as $purpose): ?>
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($purpose); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <hr>
                    <p class="text-muted mb-0"><i class="fas fa-info-circle me-2"></i>Visit purposes are defined in config.php. Contact your system administrator to modify them.</p>
                </div>
            </div>
        </section>
        
        <!-- System Maintenance -->
        <section class="content-section">
            <div class="card settings-card">
                <div class="card-header">
                    <h5><i class="fas fa-tools me-2"></i>System Maintenance</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="section-header">
                                <h6><i class="fas fa-database me-2"></i>Database Cleanup</h6>
                                <p>Remove old visitor logs and cleanup expired sessions.</p>
                            </div>
                            <button class="btn btn-outline-warning" onclick="alert('Database cleanup feature coming soon!')">
                                <i class="fas fa-broom me-2"></i>Cleanup Database
                            </button>
                        </div>
                        <div class="col-md-6">
                            <div class="section-header">
                                <h6><i class="fas fa-download me-2"></i>Export Data</h6>
                                <p>Export system data for backup or analysis.</p>
                            </div>
                            <button class="btn btn-outline-info" onclick="alert('Data export feature coming soon!')">
                                <i class="fas fa-file-export me-2"></i>Export Data
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        </div>
        </div>
    </div>
    
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>