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
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --forest:       #1a3a2a;
            --forest-mid:   #234d37;
            --forest-light: #2e6347;
            --gold:         #c8a84b;
            --gold-light:   #e2c97e;
            --mint-bg:      #e8f5ee;
            --white:        #ffffff;
            --text-dark:    #1a2e22;
            --text-mid:     #4a6355;
            --text-light:   #7a9b8a;
            --border:       #d4e8db;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body {
            height: 100%;
            font-family: 'DM Sans', sans-serif;
            background: var(--mint-bg);
        }

        /* ── SPLIT LAYOUT ── */
        .split-container {
            display: flex;
            height: 100vh;
            min-height: 600px;
            overflow: hidden;
        }

        /* ── LEFT PANEL ── */
        .left-panel {
            flex: 0 0 480px;
            background: var(--white);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 48px 52px;
            position: relative;
            z-index: 2;
            box-shadow: 4px 0 40px rgba(26,58,42,0.10);
            overflow-y: auto;
            animation: slideInLeft 0.65s cubic-bezier(.22,.68,0,1.2) both;
        }

        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-36px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        /* Gold left-edge accent */
        .left-panel::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(180deg, var(--gold) 0%, var(--forest-light) 60%, var(--forest) 100%);
        }

        /* ── BRAND HEADER ── */
        .brand {
            text-align: center;
            margin-bottom: 36px;
            animation: fadeUp 0.55s 0.15s both;
            width: 100%;
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(14px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .seal {
            width: 76px;
            height: 76px;
            border-radius: 50%;
            border: 2px solid var(--gold);
            background: var(--forest);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
            box-shadow: 0 4px 22px rgba(200,168,75,0.28);
            overflow: hidden;
        }

        .seal img {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }

        .brand h1 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 21px;
            font-weight: 700;
            color: var(--forest);
            line-height: 1.3;
            margin-bottom: 5px;
        }

        .brand .subtitle {
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.18em;
            color: var(--gold);
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .brand .portal-tag {
            font-size: 12.5px;
            color: var(--text-light);
        }

        .divider-gold {
            width: 44px;
            height: 1.5px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            margin: 18px auto 28px;
        }

        /* ── FORM SECTION ── */
        .form-section {
            width: 100%;
            animation: fadeUp 0.55s 0.28s both;
        }

        .form-section h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: 28px;
            font-weight: 600;
            color: var(--text-dark);
            text-align: center;
            margin-bottom: 5px;
        }

        .form-section .form-subtitle {
            font-size: 13px;
            color: var(--text-light);
            text-align: center;
            margin-bottom: 26px;
        }

        /* ── ALERTS ── */
        .alert {
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 18px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 13.5px;
            line-height: 1.5;
        }

        .alert-danger {
            background: #fee2e2;
            color: #c53030;
            border-left: 4px solid #e53e3e;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert svg { flex-shrink: 0; margin-top: 2px; }

        /* ── FIELDS ── */
        .form-group { margin-bottom: 18px; }

        .form-label {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12.5px;
            font-weight: 500;
            color: var(--text-mid);
            margin-bottom: 7px;
            letter-spacing: 0.02em;
        }

        .form-label svg {
            width: 14px; height: 14px;
            fill: var(--forest-light);
        }

        .form-input {
            width: 100%;
            padding: 13px 15px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14px;
            color: var(--text-dark);
            background: #fafcfb;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
        }

        .form-input:focus {
            border-color: var(--forest-light);
            background: var(--white);
            box-shadow: 0 0 0 3px rgba(46,99,71,0.10);
        }

        .form-input::placeholder { color: #b0c8bb; }

        /* ── LOGIN BUTTON ── */
        .login-btn {
            width: 100%;
            padding: 14px;
            background: var(--forest);
            color: var(--white);
            border: none;
            border-radius: 10px;
            font-family: 'DM Sans', sans-serif;
            font-size: 14.5px;
            font-weight: 600;
            letter-spacing: 0.04em;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            transition: background 0.22s, transform 0.14s, box-shadow 0.22s;
            box-shadow: 0 4px 18px rgba(26,58,42,0.22);
            margin-top: 4px;
        }

        .login-btn svg { width: 17px; height: 17px; fill: currentColor; }

        .login-btn:hover {
            background: var(--forest-light);
            transform: translateY(-1px);
            box-shadow: 0 8px 26px rgba(26,58,42,0.28);
        }

        .login-btn:active { transform: translateY(0); }
        .login-btn:disabled { opacity: 0.75; cursor: not-allowed; transform: none; }

        /* ── FOOTER NOTE ── */
        .footer-note {
            margin-top: 26px;
            text-align: center;
            font-size: 11px;
            color: var(--text-light);
            animation: fadeUp 0.55s 0.42s both;
        }

        .footer-note span { color: var(--gold); font-weight: 500; }

        /* ── RIGHT PANEL ── */
        .right-panel {
            flex: 1;
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.75s 0.08s both;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to   { opacity: 1; }
        }

        .right-bg {
            position: absolute;
            inset: 0;
            background: #ffffff;
        }

        .right-img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
            padding: 40px;
        }

        .right-overlay {
            display: none;
        }

        .right-badge {
            position: absolute;
            bottom: 32px;
            right: 32px;
            z-index: 2;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(200,168,75,0.4);
            border-radius: 14px;
            padding: 13px 18px;
            box-shadow: 0 8px 32px rgba(26,58,42,0.12);
            animation: fadeUp 0.65s 0.55s both;
        }

        .right-badge .badge-label {
            font-size: 10px;
            font-weight: 500;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--gold);
            margin-bottom: 3px;
        }

        .right-badge .badge-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 15px;
            font-weight: 600;
            color: var(--forest);
        }

        .right-dots {
            position: absolute;
            top: 32px;
            left: 32px;
            z-index: 2;
            display: flex;
            gap: 9px;
            align-items: center;
            animation: fadeIn 0.65s 0.75s both;
        }

        .dot { border-radius: 50%; }
        .dot-1 { width: 10px; height: 10px; background: rgba(200,168,75,0.6); }
        .dot-2 { width: 14px; height: 14px; background: rgba(46,99,71,0.5); }
        .dot-3 { width: 8px;  height: 8px;  background: rgba(200,168,75,0.3); }

        /* ── RESPONSIVE ── */
        @media (max-width: 900px) {
            .split-container { flex-direction: column; height: auto; min-height: 100vh; }
            .left-panel  { flex: none; width: 100%; padding: 44px 32px; box-shadow: none; }
            .right-panel { flex: none; height: 320px; }
            .left-panel::before { width: 100%; height: 5px; top: 0; left: 0; }
        }

        @media (max-width: 480px) {
            .left-panel { padding: 36px 22px; }
            .brand h1   { font-size: 18px; }
        }

        /* Loading spinner */
        @keyframes rotate { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

        /* ── LOGIN MESSAGE MODAL ── */
        #loginMsgModal {
            display: none; position: fixed; inset: 0; z-index: 9999;
            align-items: center; justify-content: center;
            background: rgba(26,58,42,.55); backdrop-filter: blur(6px);
            padding: 20px;
        }
        #loginMsgModal.show { display: flex; }
        @keyframes lmSlide {
            from { opacity:0; transform: scale(.88) translateY(22px); }
            to   { opacity:1; transform: none; }
        }
        .lm-dialog {
            background: var(--white); border-radius: 20px;
            width: 100%; max-width: 380px;
            box-shadow: 0 28px 60px rgba(26,58,42,.22);
            overflow: hidden;
            animation: lmSlide .24s cubic-bezier(.34,1.56,.64,1);
        }
        .lm-header {
            padding: 30px 28px 22px;
            text-align: center; position: relative; overflow: hidden;
        }
        .lm-header.error  { background: linear-gradient(135deg, #b91c1c, #ef4444); }
        .lm-header.success{ background: linear-gradient(135deg, var(--forest), var(--forest-light)); }
        .lm-header::before {
            content:''; position:absolute; top:-44px; right:-44px;
            width:140px; height:140px; border-radius:50%;
            background: rgba(255,255,255,.08);
        }
        .lm-header::after {
            content:''; position:absolute; bottom:-28px; left:-28px;
            width:90px; height:90px; border-radius:50%;
            background: rgba(255,255,255,.06);
        }
        .lm-icon-wrap {
            position:relative; z-index:1;
            width:64px; height:64px; border-radius:18px;
            background: rgba(255,255,255,.18); border: 2px solid rgba(255,255,255,.28);
            display:flex; align-items:center; justify-content:center;
            margin: 0 auto 14px;
            box-shadow: 0 8px 20px rgba(0,0,0,.12);
        }
        .lm-icon-wrap svg { width:30px; height:30px; fill:#fff; }
        .lm-header h5 {
            position:relative; z-index:1;
            color:#fff; font-family:'Cormorant Garamond',serif;
            font-size:1.25rem; font-weight:700; margin:0 0 4px;
        }
        .lm-header p {
            position:relative; z-index:1;
            color:rgba(255,255,255,.78); font-size:.8rem; margin:0;
        }
        .lm-body {
            padding: 22px 28px;
            text-align: center;
        }
        .lm-message {
            font-size: .92rem; color: var(--text-dark); line-height: 1.6;
            padding: 14px 16px; border-radius: 10px;
            margin-bottom: 20px;
        }
        .lm-message.error  { background:#fee2e2; color:#b91c1c; border:1px solid #fecaca; }
        .lm-message.success{ background:#d1fae5; color:#065f46; border:1px solid #a7f3d0; }
        .lm-close-btn {
            width: 100%; padding: 12px; border: none; border-radius: 10px;
            font-family:'DM Sans',sans-serif; font-weight:600; font-size:.92rem;
            cursor: pointer; transition: all .2s;
            display:flex; align-items:center; justify-content:center; gap:8px;
        }
        .lm-close-btn.error  {
            background: linear-gradient(135deg,#b91c1c,#ef4444); color:#fff;
            box-shadow: 0 4px 14px rgba(239,68,68,.3);
        }
        .lm-close-btn.success {
            background: linear-gradient(135deg,var(--forest),var(--forest-light)); color:#fff;
            box-shadow: 0 4px 14px rgba(26,58,42,.25);
        }
        .lm-close-btn:hover { transform:translateY(-1px); filter:brightness(1.08); }
    </style>
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