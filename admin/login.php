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
    <title>Login Page - <?php echo APP_NAME; ?></title>
    <link rel="icon" type="image/png" href="/logbooksystem/assets/images/sdsclogo.png">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/login.css">

</head>
<body>
<div class="split-container">

    <!-- ══ LEFT: LOGIN FORM ══ -->
    <div class="left-panel">

        <!-- Brand Header -->
        <div class="brand">
            <div class="seal">
                <img src="../assets/images/sdsclogo.png" alt="St. Dominic Savio College Logo">
            </div>
            <h1>St. Dominic Savio College</h1>
            <div class="subtitle">Visitor Log Book Management System</div>
        </div>

        <div class="divider-gold"></div>

        <!-- Form -->
        <div class="form-section">
            <h2>Login Page</h2>
            <p class="form-subtitle">Enter your credentials to access the system</p>

            <?php if ($error_message): ?>
                <div id="phpMsg" data-type="error" data-text="<?php echo htmlspecialchars($error_message); ?>" style="display:none;"></div>
            <?php endif; ?>

            <?php if ($success_message): ?>
                <div id="phpMsg" data-type="success" data-text="<?php echo htmlspecialchars($success_message); ?>" style="display:none;"></div>
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
                            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM12 17c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1s3.1 1.39 3.1 3.1v2z"/>
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

        <div class="footer-note">
            Secure access &mdash; <span>St. Dominic Savio College</span> &copy; <?php echo date('Y'); ?>
        </div>
    </div>

    <!-- ══ RIGHT: ILLUSTRATION ══ -->
    <div class="right-panel">
        <div class="right-bg"></div>
        <img class="right-img"
             src="../assets/images/visitor-admin-guard.png"
             alt="Visitor Management Illustration">
        <div class="right-overlay"></div>

        <div class="right-dots">
            <div class="dot dot-1"></div>
            <div class="dot dot-2"></div>
            <div class="dot dot-3"></div>
        </div>

        <div class="right-badge">
            <div class="badge-label">Visitor Access</div>
            <div class="badge-title">Log &amp; Manage Visitors</div>
        </div>
    </div>

</div><!-- /split-container -->

<!-- LOGIN MESSAGE MODAL -->
<div id="loginMsgModal" role="alertdialog" aria-modal="true" aria-labelledby="lmTitle">
    <div class="lm-dialog">
        <div class="lm-header" id="lmHeader">
            <div class="lm-icon-wrap" id="lmIconWrap"></div>
            <h5 id="lmTitle">Notice</h5>
            <p id="lmSubtitle"></p>
        </div>
        <div class="lm-body">
            <div class="lm-message" id="lmMessage"></div>
            <button class="lm-close-btn" id="lmCloseBtn" onclick="closeLoginModal()">OK, Got it</button>
        </div>
    </div>
</div>

<script>
    /* ── LOGIN MESSAGE MODAL ── */
    const LM_CONFIG = {
        error: {
            title:    'Authentication Failed',
            subtitle: 'Please review and try again',
            icon:     '<svg viewBox="0 0 24 24" fill="#fff"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>'
        },
        success: {
            title:    'Success',
            subtitle: 'Action completed successfully',
            icon:     '<svg viewBox="0 0 24 24" fill="#fff"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.59L5.41 12 6.83 10.59 10 13.75l7.17-7.17 1.41 1.42L10 16.59z"/></svg>'
        }
    };

    function showLoginModal(message, type) {
        const cfg = LM_CONFIG[type] || LM_CONFIG.error;
        document.getElementById('lmHeader').className   = 'lm-header ' + type;
        document.getElementById('lmIconWrap').innerHTML = cfg.icon;
        document.getElementById('lmTitle').textContent   = cfg.title;
        document.getElementById('lmSubtitle').textContent= cfg.subtitle;
        document.getElementById('lmMessage').textContent = message;
        document.getElementById('lmMessage').className   = 'lm-message ' + type;
        document.getElementById('lmCloseBtn').className  = 'lm-close-btn ' + type;
        const closeIcon = type === 'error'
            ? '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg>'
            : '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>';
        document.getElementById('lmCloseBtn').innerHTML = closeIcon + ' OK, Got it';
        document.getElementById('loginMsgModal').classList.add('show');
        document.getElementById('loginMsgModal').onclick = function(e) {
            if (e.target === this) closeLoginModal();
        };
    }

    function closeLoginModal() {
        document.getElementById('loginMsgModal').classList.remove('show');
    }

    // Auto-focus on username field
    document.addEventListener('DOMContentLoaded', function () {
        document.getElementById('username').focus();

        // Auto-show modal for PHP-generated messages
        const phpMsg = document.getElementById('phpMsg');
        if (phpMsg) {
            setTimeout(function() {
                showLoginModal(phpMsg.dataset.text, phpMsg.dataset.type);
            }, 350);
        }

        // Visual feedback on focus
        const inputs = document.querySelectorAll('.form-input');
        inputs.forEach(input => {
            input.addEventListener('focus', function () {
                this.parentElement.classList.add('focused');
            });
            input.addEventListener('blur', function () {
                this.parentElement.classList.remove('focused');
            });
        });
    });

    // AJAX Login handling (unchanged from original)
    document.getElementById('login-form').addEventListener('submit', function (e) {
        e.preventDefault();

        const username   = document.getElementById('username').value.trim();
        const password   = document.getElementById('password').value;
        const csrf_token = document.getElementById('csrf_token').value;

        console.log('AJAX login attempt for username:', username);

        if (!username || !password) {
            showMessage('Please enter both username and password.', 'error');
            return false;
        }

        // Show loading state
        const submitBtn  = this.querySelector('.login-btn');
        const originalHTML = submitBtn.innerHTML;
        submitBtn.innerHTML = '<svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor" style="animation:rotate 1s linear infinite"><path d="M12 6v3l4-4-4-4v3c-4.42 0-8 3.58-8 8 0 1.57.46 3.03 1.24 4.26L6.7 14.8c-.45-.83-.7-1.79-.7-2.8 0-3.31 2.69-6 6-6zm6.76 1.74L17.3 9.2c.44.84.7 1.79.7 2.8 0 3.31-2.69 6-6 6v-3l-4 4 4 4v-3c4.42 0 8-3.58 8-8 0-1.57-.46-3.03-1.24-4.26z"/></svg> Signing in...';
        submitBtn.disabled = true;

        // Send AJAX request
        fetch('ajax/login-ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password, csrf_token })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Login response:', data);

            if (data.success) {
                showLoginModal(data.message, 'success');
                document.getElementById('lmCloseBtn').onclick = function() {
                    closeLoginModal();
                    window.location.href = data.redirect;
                };
            } else {
                showLoginModal(data.message, 'error');
                submitBtn.innerHTML = originalHTML;
                submitBtn.disabled  = false;
            }
        })
        .catch(error => {
            console.error('Login error:', error);
            showLoginModal('Network error. Please try again.', 'error');
            submitBtn.innerHTML = originalHTML;
            submitBtn.disabled  = false;
        });
    });

    // Helper: show modal messages
    function showMessage(message, type) {
        showLoginModal(message, type);
    }
</script>
</body>
</html>