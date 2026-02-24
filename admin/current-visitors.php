<?php
/**
 * Current Visitors Module
 * St. Dominic Savio College - Visitor Management System
 */

require_once '../config/config.php';
require_once '../config/auth.php';

// Check authentication
$auth->requireLogin('login.php');

// Get currently checked-in visitors
try {
    $current_visitors = $db->fetchAll("
        SELECT v.id, v.visit_pass, v.check_in_time, v.expected_checkout_time,
               vis.first_name, vis.last_name, vis.phone, vis.company_organization,
               v.person_to_visit, v.purpose,
               TIMESTAMPDIFF(MINUTE, v.check_in_time, NOW()) as duration_minutes,
               CASE WHEN NOW() > v.expected_checkout_time THEN 1 ELSE 0 END as is_overstay
        FROM visits v
        JOIN visitors vis ON v.visitor_id = vis.id
        WHERE v.status = 'checked_in'
        ORDER BY 
            CASE WHEN NOW() > v.expected_checkout_time THEN 0 ELSE 1 END,
            v.check_in_time ASC
    ");
} catch (Exception $e) {
    $error_message = 'Error loading current visitors: ' . $e->getMessage();
    $current_visitors = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Current Visitors - <?php echo APP_NAME; ?></title>
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

        .visitors-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }

        .visitor-card {
            background: white;
            border: 1px solid var(--border-color);
            border-left: 3px solid var(--primary);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .visitor-card.overstay {
            border-left-color: var(--danger);
            background: #fef2f2;
        }

        .visitor-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary);
        }

        .btn-light {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 0.625rem 1.25rem;
            font-weight: 600;
            font-size: 0.875rem;
            color: white;
            transition: all 0.2s ease;
        }

        .btn-light:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-1px);
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

        .badge {
            border-radius: 6px;
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 600;
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

            .stats-grid {
                gap: 1rem;
            }
        }

        @media (max-width: 768px) {
            .page-header h2 {
                font-size: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
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
                <div class="row align-items-center">
                    <div class="col">
                        <h2 class="mb-0">
                            <i class="fas fa-users me-2"></i>Current Visitors
                        </h2>
                        <p class="mb-0 subtitle"><?php echo count($current_visitors); ?> visitors currently inside</p>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-light" onclick="window.location.reload()">
                            <i class="fas fa-sync-alt me-2"></i>Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="container-fluid">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($current_visitors)): ?>
            <div class="card visitors-card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-users fa-4x text-muted mb-3"></i>
                    <h4>No Visitors Currently Inside</h4>
                    <p class="text-muted">All visitors have been checked out.</p>
                    <a href="checkin.php" class="btn btn-success">
                        <i class="fas fa-user-plus me-2"></i>Check In New Visitor
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- Live Counter -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <div class="h2 text-success"><?php echo count($current_visitors); ?></div>
                            <h6>Total Inside</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <div class="h2 text-danger">
                                <?php echo count(array_filter($current_visitors, function($v) { return $v['is_overstay']; })); ?>
                            </div>
                            <h6>Overstayed</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <div class="h2 text-info">
                                <?php 
                                $avg_duration = 0;
                                if (count($current_visitors) > 0) {
                                    $total_duration = array_sum(array_column($current_visitors, 'duration_minutes'));
                                    $avg_duration = round($total_duration / count($current_visitors) / 60, 1);
                                }
                                echo $avg_duration . 'h';
                                ?>
                            </div>
                            <h6>Avg Duration</h6>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <div class="h2 text-primary" id="currentTime"></div>
                            <h6>Current Time</h6>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Visitor Cards -->
            <div class="row">
                <?php foreach ($current_visitors as $visitor): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card visitor-card <?php echo $visitor['is_overstay'] ? 'overstay' : ''; ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="card-title mb-0">
                                        <?php echo htmlspecialchars($visitor['first_name'] . ' ' . $visitor['last_name']); ?>
                                    </h6>
                                    <?php if ($visitor['is_overstay']): ?>
                                        <span class="badge bg-danger">Overstay</span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted">Pass:</small>
                                    <strong><?php echo htmlspecialchars($visitor['visit_pass']); ?></strong>
                                </div>
                                
                                <div class="mb-2">
                                    <small class="text-muted">Phone:</small>
                                    <?php echo htmlspecialchars($visitor['phone']); ?>
                                </div>
                                
                                <?php if ($visitor['company_organization']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Company:</small>
                                        <?php echo htmlspecialchars($visitor['company_organization']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($visitor['person_to_visit']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Visiting:</small>
                                        <?php echo htmlspecialchars($visitor['person_to_visit']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($visitor['purpose']): ?>
                                    <div class="mb-2">
                                        <small class="text-muted">Purpose:</small>
                                        <?php echo htmlspecialchars($visitor['purpose']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <hr>
                                
                                <div class="row text-center">
                                    <div class="col-6">
                                        <small class="text-muted d-block">Check-In Time</small>
                                        <strong><?php echo date('H:i', strtotime($visitor['check_in_time'])); ?></strong>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted d-block">Duration</small>
                                        <strong>
                                            <?php
                                            $hours = floor($visitor['duration_minutes'] / 60);
                                            $minutes = $visitor['duration_minutes'] % 60;
                                            echo "{$hours}h {$minutes}m";
                                            ?>
                                        </strong>
                                    </div>
                                </div>
                                
                                <div class="mt-3 d-grid">
                                    <a href="checkout.php" class="btn btn-sm btn-outline-danger">
                                        <i class="fas fa-sign-out-alt me-1"></i>Go to Check-Out
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>        </div>        </div>
    </div>
    
    <script src="../assets/js/jquery.min.js"></script>
    <script src="../assets/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Update current time
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        
        // Update time every second
        setInterval(updateTime, 1000);
        updateTime(); // Initial call
        
        // Auto-refresh page every 30 seconds
        setTimeout(function() {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>