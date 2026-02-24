<?php
/**
 * Login Page
 * St. Dominic Savio College - Visitor Management System
 */

require_once '../config/config.php';
require_once '../config/auth.php';

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

// Check for messages from URL parameters
if (isset($_GET['message'])) {
    switch ($_GET['message']) {
        case 'logged_out':
            $success_message = 'You have been successfully logged out.';
            break;
        case 'session_expired':
            $error_message = 'Your session has expired. Please log in again.';
            break;
        case 'invalid_role':
            $error_message = 'Invalid user role detected. Please contact administrator.';
            break;
    }
}

// Handle login form submission
// REMOVED - Now handled via AJAX

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - <?php echo APP_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --green-dark: #1a4d2e;
            --green-mid: #256340;
            --green-light: #e8f5ee;
            --green-accent: #2d7a4f;
            --gold: #c9a84c;
            --gold-light: #e8c96a;
            --border: #c2dece;
            --text-muted: #a8c9b5;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--green-light);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(26, 77, 46, 0.1);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
            border: 1px solid var(--border);
        }

        .login-header {
            background: var(--green-dark);
            position: relative;
            overflow: hidden;
            padding: 3rem 2rem;
            text-align: center;
        }

        .login-header::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(201, 168, 76, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 1;
        }

        .login-header::after {
            content: '';
            position: absolute;
            bottom: -75px;
            left: -75px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(201, 168, 76, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 1;
        }

        .login-header-content {
            position: relative;
            z-index: 2;
        }

        .logo {
            width: 80px;
            height: 80px;
            border: 2px solid var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .logo img {
            width: 50px;
            height: 50px;
        }

        .school-name {
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: var(--gold);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .login-type {
            color: var(--text-muted);
            font-size: 0.9rem;
            font-weight: 400;
        }

        .login-body {
            padding: 2.5rem 2rem;
        }

        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--green-dark);
            margin-bottom: 0.5rem;
        }

        .form-subtitle {
            color: #666;
            font-size: 0.9rem;
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-danger {
            background: #fee2e2;
            color: #dc2626;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--green-dark);
            font-size: 0.9rem;
        }

        .form-label svg {
            width: 16px;
            height: 16px;
            fill: var(--green-accent);
        }

        .form-input {
            background: white;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 13px 16px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            font-family: inherit;
            width: 100%;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--green-accent);
            box-shadow: 0 0 0 3px rgba(45, 122, 79, 0.1);
        }

        .form-input::placeholder {
            color: #a0a0a0;
        }

        .login-btn {
            background: var(--green-dark);
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            width: 100%;
            margin-bottom: 1.5rem;
        }

        .login-btn:hover {
            background: var(--green-accent);
        }

        .login-btn:active {
            transform: scale(0.99);
        }

        .login-btn svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1.5rem 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .divider span {
            color: #666;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .visitor-register-link {
            text-align: center;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 12px;
        }

        .visitor-register-link p {
            margin-bottom: 1rem;
            color: #666;
            font-size: 0.9rem;
        }

        .visitor-btn {
            background: transparent;
            color: var(--green-dark);
            border: 1.5px solid var(--green-accent);
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .visitor-btn:hover {
            background: var(--green-accent);
            color: white;
            text-decoration: none;
        }

        .visitor-btn svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }

        /* RESPONSIVE DESIGN */
        @media (max-width: 480px) {
            body {
                padding: 1rem 0.5rem;
            }

            .login-header {
                padding: 2rem 1.5rem;
            }

            .login-body {
                padding: 2rem 1.5rem;
            }

            .school-name {
                font-size: 1.5rem;
            }

            .form-title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="login-header-content">
                <!-- Logo -->
                <div class="logo">
                    <img src="../assets/images/sdsclogo.png" alt="Logo" style="width: 50px; height: 50px;">
                </div>

                <!-- School Info -->
                <h1 class="school-name">St. Dominic Savio College</h1>
                <div class="subtitle">Visitor Management System</div>
                <div class="login-type">Admin Portal</div>
            </div>
        </div>

        <div class="login-body">
            <div class="form-header">
                <h2 class="form-title">Admin Login</h2>
                <p class="form-subtitle">Enter your credentials to access the system</p>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                        <path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/>
                    </svg>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                    </svg>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="#" id="login-form">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>" id="csrf_token">
                
                <div class="form-group">
                    <label for="username" class="form-label">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                        Username
                    </label>
                    <input type="text" class="form-input" id="username" name="username" 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           placeholder="Enter your username" required>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">
                        <svg viewBox="0 0 24 24">
                            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zM15.1 8H8.9V6c0-1.71 1.39-3.1 3.1-3.1s3.1 1.39 3.1 3.1v2z"/>
                        </svg>
                        Password
                    </label>
                    <input type="password" class="form-input" id="password" name="password" 
                           placeholder="Enter your password" required>
                </div>
                
                <button type="submit" name="login" class="login-btn">
                    <svg viewBox="0 0 24 24">
                        <path d="M11 7L9.6 8.4l2.6 2.6H2v2h10.2l-2.6 2.6L11 17l5-5-5-5zm9 12h-8v2h8c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-8v2h8v14z"/>
                    </svg>
                    Login
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Auto-focus on username field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
            
            // Add visual feedback on focus
            const inputs = document.querySelectorAll('.form-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
            });
        });
        
        // AJAX Login handling
        document.getElementById('login-form').addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission
            
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const csrf_token = document.getElementById('csrf_token').value;
            
            console.log('AJAX login attempt for username:', username);
            
            if (!username || !password) {
                showMessage('Please enter both username and password.', 'error');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('.login-btn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" style="animation: rotate 1s linear infinite;"><path d="M12 6v3l4-4-4-4v3c-4.42 0-8 3.58-8 8 0 1.57.46 3.03 1.24 4.26L6.7 14.8c-.45-.83-.7-1.79-.7-2.8 0-3.31 2.69-6 6-6zm6.76 1.74L17.3 9.2c.44.84.7 1.79.7 2.8 0 3.31-2.69 6-6 6v-3l-4 4 4 4v-3c4.42 0 8-3.58 8-8 0-1.57-.46-3.03-1.24-4.26z"/></svg> Signing in...';
            submitBtn.disabled = true;
            
            // Add CSS for rotation animation
            if (!document.getElementById('rotation-style')) {
                const style = document.createElement('style');
                style.id = 'rotation-style';
                style.textContent = '@keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }';
                document.head.appendChild(style);
            }
            
            // Send AJAX request
            fetch('ajax/login-ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    username: username,
                    password: password,
                    csrf_token: csrf_token
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Login response:', data);
                
                if (data.success) {
                    showMessage(data.message, 'success');
                    
                    // Redirect after short delay
                    setTimeout(() => {
                        console.log('Redirecting to:', data.redirect);
                        window.location.href = data.redirect;
                    }, 1000);
                } else {
                    showMessage(data.message, 'error');
                    
                    // Reset button
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Login error:', error);
                showMessage('Network error. Please try again.', 'error');
                
                // Reset button
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });
        
        // Helper function to show messages
        function showMessage(message, type) {
            // Remove existing messages
            const existingAlerts = document.querySelectorAll('.alert');
            existingAlerts.forEach(alert => alert.remove());
            
            // Create new alert
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type === 'error' ? 'danger' : 'success'}`;
            
            const icon = type === 'error' ? 
                '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>' :
                '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>';
            
            alertDiv.innerHTML = `${icon} ${message}`;
            
            // Insert before form
            const form = document.getElementById('login-form');
            form.parentNode.insertBefore(alertDiv, form);
            
            // Auto-remove success messages after 3 seconds
            if (type === 'success') {
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 3000);
            }
        }
    </script>
</body>
</html>