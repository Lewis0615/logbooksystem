<?php
/**
 * Main Entry Point
 * St. Dominic Savio College - Visitor Management System
 */

require_once 'config/config.php';
require_once 'config/auth.php';

// Check if user is logged in
if ($auth->isLoggedIn()) {
    // Check session timeout
    if (!$auth->checkSessionTimeout()) {
        header('Location: login.php?message=session_expired');
        exit();
    }
    
    // Redirect based on user role
    $user_role = $_SESSION['user_role'] ?? '';
    
    switch ($user_role) {
        case ROLE_ADMIN:
        case ROLE_SUPERVISOR:
            header('Location: modules/admin/dashboard.php');
            break;
        case ROLE_GUARD:
            header('Location: modules/visitor/checkin.php');
            break;
        default:
            // Unknown role, logout and redirect to login
            $auth->logout();
            header('Location: login.php?message=invalid_role');
            break;
    }
} else {
    // User not logged in, show homepage with options
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
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
            --custom-color: #5853f0;
            --custom-color-accent: #050089; /* Example custom color variable */
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
            flex-direction: row;
        }

        /* LEFT PANEL */
        .left-panel {
            width: 30%;
            background: var(--green-dark);
            position: relative;
            overflow: hidden;
            padding: 3rem 2.5rem;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            min-height: 100vh;
        }

        .left-panel::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(201, 168, 76, 0.15) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 1;
        }

        .left-panel::after {
            content: '';
            position: absolute;
            bottom: -100px;
            left: -100px;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(201, 168, 76, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            z-index: 1;
        }

        .left-panel-content {
            position: relative;
            z-index: 2;
        }

        .logo {
            width: 72px;
            height: 72px;
            border: 2px solid var(--gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
        }

        .logo svg {
            width: 32px;
            height: 32px;
            fill: var(--gold);
        }

        .school-name {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            font-weight: 700;
            color: white;
            line-height: 1.2;
            margin-bottom: 0.75rem;
        }

        .subtitle {
            color: var(--gold);
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .divider {
            width: 44px;
            height: 2px;
            background: linear-gradient(90deg, var(--gold) 0%, var(--gold-light) 100%);
            margin-bottom: 2rem;
        }

        .welcome-heading {
            font-family: 'Playfair Display', serif;
            font-size: 1.75rem;
            font-weight: 600;
            color: white;
            margin-bottom: 1rem;
        }

        .welcome-text {
            color: var(--text-muted);
            font-size: 0.95rem;
            line-height: 1.5;
            margin-bottom: 2.5rem;
        }

        .feature-cards {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .feature-card {
            background: rgba(45, 122, 79, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 14px;
            padding: 1.25rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.2s ease;
        }

        .feature-card:hover {
            background: rgba(45, 122, 79, 0.4);
        }

        .feature-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .feature-icon.gold {
            background: linear-gradient(135deg, var(--gold) 0%, var(--gold-light) 100%);
        }

        .feature-icon.teal {
            background: linear-gradient(135deg, #2dd4bf 0%, #14b8a6 100%);
        }

        .feature-info h4 {
            color: white;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .feature-info p {
            color: var(--text-muted);
            font-size: 0.8rem;
            line-height: 1.3;
        }

        /* RIGHT PANEL */
        .right-panel {
            flex: 1;
            background: var(--green-light);
            padding: 3rem 2.5rem;
            overflow-y: auto;
        }

        .form-header {
            margin-bottom: 2rem;
        }

        .step-label {
            color: var(--green-accent);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .form-title {
            font-family: 'Playfair Display', serif;
            font-size: 2.25rem;
            font-weight: 700;
            color: var(--green-dark);
            margin-bottom: 0.5rem;
        }

        .form-subtitle {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }

        .progress-bar {
            display: flex;
            gap: 6px;
            margin-bottom: 2rem;
        }

        .progress-segment {
            height: 4px;
            flex: 1;
            border-radius: 99px;
        }

        .progress-segment.active-1 { background: var(--green-dark); }
        .progress-segment.active-2 { background: var(--green-accent); }
        .progress-segment.inactive { background: #e5e7eb; }

        /* FORM STYLES */
        .registration-form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
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

        .form-input,
        .form-select,
        .form-textarea {
            background: white;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 13px 16px;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            font-family: inherit;
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--green-accent);
            box-shadow: 0 0 0 3px rgba(45, 122, 79, 0.1);
        }

        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 12px center;
            background-repeat: no-repeat;
            background-size: 16px;
            padding-right: 40px;
        }

        /* PHOTO UPLOAD */
        .photo-upload-zone {
            border: 2px dashed var(--border);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.7);
            padding: 2rem;
            text-align: center;
            position: relative;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .photo-upload-zone:hover {
            border-color: var(--green-accent);
            background: rgba(232, 245, 238, 0.8);
        }

        .photo-upload-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }

        .upload-icon {
            width: 48px;
            height: 48px;
            border: 2px dashed var(--border);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .upload-icon svg {
            width: 20px;
            height: 20px;
            fill: var(--green-accent);
        }

        .upload-text h4 {
            color: var(--green-dark);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .upload-text p {
            color: #666;
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .camera-controls {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1rem;
        }

        .camera-btn {
            background: var(--green-dark);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .camera-btn:hover {
            background: var(--green-accent);
        }

        .camera-btn.secondary {
            background: #6b7280;
        }

        .camera-btn.secondary:hover {
            background: #4b5563;
        }

        .camera-preview {
            display: none;
            margin-top: 1rem;
        }

        .camera-preview video {
            width: 100%;
            max-width: 300px;
            border-radius: 8px;
            background: #000;
        }

        .camera-preview canvas {
            display: none;
        }

        .photo-preview {
            display: none;
            margin-top: 1rem;
            text-align: center;
        }

        .photo-preview img {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }

        .photo-preview .filename {
            font-size: 0.8rem;
            color: #666;
        }

        .retake-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            margin-top: 0.5rem;
        }

        .retake-btn:hover {
            background: #dc2626;
        }

        /* VISITOR PASS CONFIRMATION */
        .pass-confirmation {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .pass-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            max-width: 400px;
            width: 100%;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .pass-header {
            margin-bottom: 1.5rem;
        }

        .pass-header h3 {
            color: var(--green-dark);
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .pass-header p {
            color: #666;
            font-size: 0.9rem;
        }

        .pass-code {
            background: var(--green-light);
            border: 2px solid var(--green-accent);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .pass-code-label {
            font-size: 0.8rem;
            color: var(--green-accent);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .pass-code-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--green-dark);
            font-family: 'Monaco', 'Consolas', monospace;
            letter-spacing: 0.1em;
        }

        .pass-details {
            text-align: left;
            margin: 1.5rem 0;
            border-top: 1px solid var(--border);
            padding-top: 1rem;
        }

        .pass-detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            font-size: 0.85rem;
        }

        .pass-detail-label {
            color: #666;
            font-weight: 500;
        }

        .pass-detail-value {
            color: var(--green-dark);
            font-weight: 600;
        }

        .pass-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .pass-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .pass-btn.primary {
            background: var(--green-dark);
            color: white;
        }

        .pass-btn.primary:hover {
            background: var(--green-accent);
        }

        .pass-btn.secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .pass-btn.secondary:hover {
            background: #e5e7eb;
        }

        /* FILE UPLOAD BUTTON */
        .file-upload-btn {
            background: var(--green-dark);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
            font-size: 0.9rem;
        }

        .file-upload-btn:hover {
            background: var(--green-accent);
        }

        .file-upload-btn svg {
            width: 16px;
            height: 16px;
            fill: currentColor;
        }

        .file-upload-btn input[type="file"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .file-name {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #666;
        }

        /* SUBMIT BUTTON */
        .submit-btn {
            background: var(--custom-color);
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
        }

        .submit-btn:hover {
            background: var(--custom-color-accent);
        }

        .submit-btn:active {
            transform: scale(0.99);
        }

        .submit-btn svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }

        /* DRESS CODE SECTION - LEFT PANEL */
        .dress-code-section {
            background: rgba(45, 122, 79, 0.2);
            border: 1px solid rgba(201, 168, 76, 0.3);
            border-radius: 14px;
            padding: 1.5rem;
            margin-top: 2rem;
        }

        .dress-code-header {
            margin-bottom: 1.5rem;
        }

        .dress-code-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gold-light);
            margin-bottom: 0.5rem;
        }

        .dress-code-subtitle {
            color: var(--text-muted);
            font-size: 0.8rem;
            line-height: 1.4;
        }

        .dress-code-grid {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .dress-code-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.875rem;
            transition: all 0.2s ease;
            position: relative;
        }

        .dress-code-card.allowed {
            border-left: 3px solid #10b981;
        }

        .dress-code-card.not-allowed {
            border-left: 3px solid #ef4444;
        }

        .dress-code-card:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateX(4px);
        }

        .dress-code-image-wrapper {
            width: 60px;
            height: 60px;
            flex-shrink: 0;
            border-radius: 8px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dress-code-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .dress-code-card:hover .dress-code-image {
            transform: scale(1.1);
        }

        .dress-code-content {
            flex: 1;
            min-width: 0;
        }

        .dress-code-status {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 12px;
            font-size: 0.65rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .dress-code-status.allowed-badge {
            background: rgba(16, 185, 129, 0.2);
            color: #6ee7b7;
        }

        .dress-code-status.notallowed-badge {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
        }

        .dress-code-item-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: white;
            margin-bottom: 0.25rem;
            line-height: 1.3;
        }

        .dress-code-description {
            font-size: 0.72rem;
            color: var(--text-muted);
            line-height: 1.4;
            display: none;
        }

        .dress-code-footer {
            background: rgba(201, 168, 76, 0.15);
            border: 1px solid rgba(201, 168, 76, 0.3);
            border-radius: 8px;
            padding: 0.75rem;
            text-align: center;
            margin-top: 1rem;
        }

        .dress-code-footer p {
            color: var(--gold-light);
            font-size: 0.7rem;
            font-weight: 500;
            margin: 0;
            line-height: 1.4;
        }

        .dress-code-expand {
            color: var(--text-muted);
            font-size: 0.7rem;
            text-align: center;
            margin-top: 0.75rem;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .dress-code-expand:hover {
            color: var(--gold);
        }

        /* RESPONSIVE DESIGN */
        @media (max-width: 900px) {
            body {
                flex-direction: column;
            }

            .left-panel {
                width: 100%;
                min-height: auto;
                padding: 2rem 1.5rem;
            }

            .right-panel {
                padding: 2rem 1.5rem;
            }

            .registration-form {
                grid-template-columns: 1fr;
            }

            .form-group.full-width {
                grid-column: 1;
            }
            
            .dress-code-section {
                margin-top: 1.5rem;
                padding: 1.25rem;
            }
            
            .dress-code-image-wrapper {
                width: 55px;
                height: 55px;
            }
        }

        @media (max-width: 480px) {
            .right-panel {
                padding: 1.5rem 1rem;
            }

            .form-title {
                font-size: 1.8rem;
            }

            .left-panel {
                padding: 1.5rem 1rem;
            }

            .school-name {
                font-size: 1.6rem;
            }
            
            .dress-code-section {
                padding: 1rem;
                margin-top: 1.25rem;
            }
            
            .dress-code-image-wrapper {
                width: 50px;
                height: 50px;
            }
            
            .dress-code-title {
                font-size: 1.1rem;
            }
            
            .dress-code-card {
                padding: 0.75rem;
                gap: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <!-- LEFT PANEL -->
    <div class="left-panel">
        <div class="left-panel-content">
            <!-- Logo -->
            <div class="logo">
                <img src="/logbooksystem/assets/images/sdsclogo.png" alt="Logo" class="logo-icon" style="width: 50px; height: 50px;">
            </div>

            <!-- School Info -->
            <h1 class="school-name">St. Dominic Savio College</h1>
            <div class="subtitle">Digital Visitor Logbook System</div>
            <div class="divider"></div>

            <!-- Welcome Section -->
            <h2 class="welcome-heading">Welcome, Visitor!</h2>
            <p class="welcome-text">
                Register quickly and securely to access our campus. 
                Your information is kept confidential and helps us maintain a safe environment.
            </p>
            
            <!-- Dress Code Section -->
            <div class="dress-code-section">
                <div class="dress-code-header">
                    <h2 class="dress-code-title">Dress Code Policy</h2>
                    <p class="dress-code-subtitle">Please observe our dress code guidelines when visiting campus</p>
                </div>
                
                <div class="dress-code-grid">
                    <!-- Proper Attire - Allowed -->
                    <div class="dress-code-card allowed">
                        <div class="dress-code-image-wrapper">
                            <img src="assets/images/dress-code/proper-attire.svg" alt="Proper Attire" class="dress-code-image">
                        </div>
                        <div class="dress-code-content">
                            <div class="dress-code-status allowed-badge">✓ Allowed</div>
                            <h3 class="dress-code-item-title">Proper Attire</h3>
                        </div>
                    </div>
                    
                    <!-- Closed Footwear - Allowed -->
                    <div class="dress-code-card allowed">
                        <div class="dress-code-image-wrapper">
                            <img src="assets/images/dress-code/closed-footwear.svg" alt="Closed Footwear" class="dress-code-image">
                        </div>
                        <div class="dress-code-content">
                            <div class="dress-code-status allowed-badge">✓ Allowed</div>
                            <h3 class="dress-code-item-title">Closed Footwear</h3>
                        </div>
                    </div>
                    
                    <!-- Sleeveless / Tank Tops - Not Allowed -->
                    <div class="dress-code-card not-allowed">
                        <div class="dress-code-image-wrapper">
                            <img src="assets/images/dress-code/sleeveless.svg" alt="No Sleeveless" class="dress-code-image">
                        </div>
                        <div class="dress-code-content">
                            <div class="dress-code-status notallowed-badge">✗ Not Allowed</div>
                            <h3 class="dress-code-item-title">Sleeveless / Tank Tops</h3>
                        </div>
                    </div>
                    
                    <!-- Short Skirts/Shorts - Not Allowed -->
                    <div class="dress-code-card not-allowed">
                        <div class="dress-code-image-wrapper">
                            <img src="assets/images/dress-code/short-skirts.svg" alt="No Short Skirts/Shorts" class="dress-code-image">
                        </div>
                        <div class="dress-code-content">
                            <div class="dress-code-status notallowed-badge">✗ Not Allowed</div>
                            <h3 class="dress-code-item-title">Short Skirts/Shorts</h3>
                        </div>
                    </div>
                    
                    <!-- Slippers / Flip-flops - Not Allowed -->
                    <div class="dress-code-card not-allowed">
                        <div class="dress-code-image-wrapper">
                            <img src="assets/images/dress-code/slippers.svg" alt="No Slippers" class="dress-code-image">
                        </div>
                        <div class="dress-code-content">
                            <div class="dress-code-status notallowed-badge">✗ Not Allowed</div>
                            <h3 class="dress-code-item-title">Slippers / Flip-flops</h3>
                        </div>
                    </div>
                    
                    <!-- Offensive Clothing - Not Allowed -->
                    <div class="dress-code-card not-allowed">
                        <div class="dress-code-image-wrapper">
                            <img src="assets/images/dress-code/offensive-clothing.svg" alt="No Offensive Clothing" class="dress-code-image">
                        </div>
                        <div class="dress-code-content">
                            <div class="dress-code-status notallowed-badge">✗ Not Allowed</div>
                            <h3 class="dress-code-item-title">Offensive Clothing</h3>
                        </div>
                    </div>
                </div>
                
                <div class="dress-code-footer">
                    <p>⚠️ Visitors not following the dress code may be denied entry</p>
                </div>
            </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="right-panel">
        <div class="form-header">
            <h2 class="form-title">Visitor Registration</h2>
            <p class="form-subtitle">Fields marked * are required.</p>
        

        <!-- Registration Form -->
        <form class="registration-form" id="visitor-form">
            <!-- Full Name -->
            <div class="form-group">
                <label class="form-label" for="fullName">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                    Full Name *
                </label>
                <input type="text" id="fullName" class="form-input" placeholder="Enter your full name" required>
            </div>

            <!-- Contact Number -->
            <div class="form-group">
                <label class="form-label" for="contactNumber">
                    <svg viewBox="0 0 24 24">
                        <path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                    </svg>
                    Contact Number *
                </label>
                <input type="tel" id="contactNumber" class="form-input" placeholder="09X XXX XXXXX" pattern="09[0-9]{9}" maxlength="12" required title="Please enter a valid Philippine mobile number (e.g., 09123456789)">
                <small style="color: #666; font-size: 0.8rem; margin-top: 0.25rem;"></small>    
            </div>

            <!-- Address -->
            <div class="form-group full-width">
                <label class="form-label" for="address">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                    Address
                </label>
                <input type="text" id="address" class="form-input" placeholder="Street Address, Barangay, City/Municipality, Province, ZIP Code">
            </div>

            <!-- Purpose of Visit -->
            <div class="form-group">
                <label class="form-label" for="purpose">
                    <svg viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/>
                    </svg>
                    Purpose of Visit *
                </label>
                <select id="purpose" class="form-select" required>
                    <option value="">Select purpose</option>
                    <option value="academic">Academic Inquiry</option>
                    <option value="business">Business/Official</option>
                    <option value="personal">Personal Visit</option>
                    <option value="delivery">Delivery</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <!-- Purpose Other Input -->
            <div class="form-group" id="purposeOtherContainer" style="display: none;">
                <label class="form-label" for="purposeOther">
                    <svg viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/>
                    </svg>
                    Specify Purpose *
                </label>
                <input type="text" id="purposeOther" class="form-input" placeholder="Please specify your purpose">
            </div>

            <!-- Office/Department -->
            <div class="form-group">
                <label class="form-label" for="department">
                    <svg viewBox="0 0 24 24">
                        <path d="M21 16V4a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2zm-11-4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zM9 18v-2a2 2 0 012-2h2a2 2 0 012 2v2H9z"/>
                    </svg>
                    Office / Department
                </label>
                <select id="department" class="form-select">
                    <option value="">Select department</option>
                    <option value="registrar">Registrar's Office</option>
                    <option value="admissions">Admissions</option>
                    <option value="finance">Finance Office</option>
                    <option value="principal">Principal's Office</option>
                    <option value="library">Library</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <!-- Department Other Input -->
            <div class="form-group" id="departmentOtherContainer" style="display: none;">
                <label class="form-label" for="departmentOther">
                    <svg viewBox="0 0 24 24">
                        <path d="M21 16V4a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2zm-11-4c0-1.11.89-2 2-2s2 .89 2 2-.89 2-2 2-2-.89-2-2zM9 18v-2a2 2 0 012-2h2a2 2 0 012 2v2H9z"/>
                    </svg>
                    Specify Department
                </label>
                <input type="text" id="departmentOther" class="form-input" placeholder="Please specify the department">
            </div>

            <!-- Person to Visit -->
            <div class="form-group full-width">
                <label class="form-label" for="personToVisit">
                    <svg viewBox="0 0 24 24">
                        <path d="M16 4c4.42 0 8 3.58 8 8s-3.58 8-8 8c-1.1 0-2.14-.22-3.1-.62L9 21l1.9-3.9c-1.78-1.46-2.9-3.64-2.9-6.1 0-4.42 3.58-8 8-8z"/>
                    </svg>
                    Person to Visit *
                </label>
                <input type="text" id="personToVisit" class="form-input" placeholder="Name of person you're visiting" required>
            </div>

            <!-- Group Visit -->
            <div class="form-group">
                <label class="form-label">
                    <svg viewBox="0 0 24 24">
                        <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                    </svg>
                    Are you visiting as a group?
                </label>
                <div style="display: flex; gap: 1.5rem; margin-top: 0.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="radio" name="groupVisit" id="groupVisitYes" value="yes" style="width: 18px; height: 18px; cursor: pointer;">
                        <span style="color: var(--green-dark); font-weight: 500;">Yes</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <input type="radio" name="groupVisit" id="groupVisitNo" value="no" checked style="width: 18px; height: 18px; cursor: pointer;">
                        <span style="color: var(--green-dark); font-weight: 500;">No</span>
                    </label>
                </div>
            </div>

            <!-- Number of People in Group -->
            <div class="form-group" id="groupSizeContainer" style="display: none;">
                <label class="form-label" for="groupSize">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                    </svg>
                    Total Number of People *
                </label>
                <input type="number" id="groupSize" class="form-input" placeholder="Enter total number of people" min="2" max="100">
            </div>

            <!-- Group Members Names -->
            <div class="form-group full-width" id="groupMembersContainer" style="display: none;">
                <label class="form-label">
                    <svg viewBox="0 0 24 24">
                        <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
                    </svg>
                    Group Members Full Names *
                </label>
                <div id="groupMembersFields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 0.5rem;">
                    <!-- Dynamic name fields will be inserted here -->
                </div>
            </div>

            <!-- Additional Notes -->
            <div class="form-group full-width">
                <label class="form-label" for="notes">
                    <svg viewBox="0 0 24 24">
                        <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/>
                    </svg>
                    Additional Notes
                </label>
                <textarea id="notes" class="form-textarea" placeholder="Any additional information or special requests"></textarea>
            </div>

            <!-- Valid ID Upload -->
            <div class="form-group full-width">
                <label class="form-label" for="idType">
                    <svg viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/>
                    </svg>
                    Valid ID Type (Optional)
                </label>
                <select id="idType" class="form-select">
                    <option value="">Select ID type</option>
                    <option value="drivers_license">Driver's License</option>
                    <option value="passport">Passport</option>
                    <option value="national_id">National ID / PhilSys ID</option>
                    <option value="voters_id">Voter's ID</option>
                    <option value="prc_id">PRC ID</option>
                    <option value="postal_id">Postal ID</option>
                    <option value="sss_id">SSS ID</option>
                    <option value="gsis_id">GSIS ID</option>
                    <option value="tin_id">TIN ID</option>
                    <option value="school_id">School ID</option>
                    <option value="company_id">Company ID</option>
                    <option value="barangay_id">Barangay ID</option>
                    <option value="senior_citizen_id">Senior Citizen ID</option>
                    <option value="pwd_id">PWD ID</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <!-- ID Type Other Input -->
            <div class="form-group" id="idTypeOtherContainer" style="display: none;">
                <label class="form-label" for="idTypeOther">
                    <svg viewBox="0 0 24 24">
                        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm4 18H6V4h7v5h5v11z"/>
                    </svg>
                    Specify ID Type
                </label>
                <input type="text" id="idTypeOther" class="form-input" placeholder="Please specify your ID type">
            </div>

            <!-- Valid ID Photo/Upload -->
            <div class="form-group full-width">
                <label class="form-label">
                    <svg viewBox="0 0 24 24">
                        <path d="M23 19V5c0-1.1-.9-2-2-2H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                        <circle cx="9.5" cy="8.5" r="1.5"/>
                    </svg>
                    Upload/Capture Valid ID (Optional)
                </label>
                <div class="photo-upload-zone" id="photo-zone">
                    <div class="upload-icon">
                        <svg viewBox="0 0 24 24">
                            <path d="M23 19V5c0-1.1-.9-2-2-2H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                            <circle cx="9.5" cy="8.5" r="1.5"/>
                        </svg>
                    </div>
                    <div class="upload-text">
                        <h4>Capture ID Photo or Upload File</h4>
                        <p>Use camera to capture or choose a file</p>
                    </div>
                    <div class="camera-controls">
                        <button type="button" class="camera-btn" id="start-camera-btn">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M17 10.5V7c0-.55-.45-1-1-1H4c-.55 0-1 .45-1 1v10c0 .55.45 1 1 1h12c.55 0 1-.45 1-1v-3.5l4 4v-11l-4 4z"/>
                            </svg>
                            Open Camera
                        </button>
                        <label for="validId" class="camera-btn secondary">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 9V7.5L17.5 12H13z"/>
                            </svg>
                            Upload File
                            <input type="file" id="validId" accept="image/*,.pdf" style="position: absolute; opacity: 0; pointer-events: none;">
                        </label>
                    </div>
                </div>
                
                <div class="camera-preview" id="camera-preview">
                    <video id="camera-video" autoplay playsinline></video>
                    <canvas id="photo-canvas"></canvas>
                    <div class="camera-controls">
                        <button type="button" class="camera-btn" id="capture-btn">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <circle cx="12" cy="12" r="3.2"/>
                                <path d="M9 2L7.17 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2h-3.17L15 2H9zm3 15c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/>
                            </svg>
                            Capture ID Photo
                        </button>
                        <button type="button" class="camera-btn secondary" id="close-camera-btn">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                            Cancel
                        </button>
                    </div>
                </div>
                
                <div class="photo-preview" id="photo-preview">
                    <img id="photo-img" src="" alt="Captured ID Photo">
                    <div class="filename" id="photo-filename">Captured ID Photo</div>
                    <button type="button" class="retake-btn" id="retake-btn">Retake Photo</button>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="form-group full-width">
                <button type="submit" class="submit-btn">
                    <svg viewBox="0 0 24 24">
                        <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                    </svg>
                    Complete Registration
                </button>
            </div>
        </form>
    </div>

    <!-- Visitor Pass Confirmation Modal -->
    <div class="pass-confirmation" id="pass-confirmation">
        <div class="pass-card">
            <div class="pass-header">
                <h3>Registration Successful!</h3>
                <p>Your visitor pass has been generated</p>
            </div>
            
            <div class="pass-code">
                <div class="pass-code-label">Visitor Pass Code</div>
                <div class="pass-code-number" id="visitor-pass-code">VP-XXXX</div>
            </div>
            
            <div class="pass-details">
                <div class="pass-detail-row">
                    <span class="pass-detail-label">Visitor Name:</span>
                    <span class="pass-detail-value" id="pass-visitor-name">--</span>
                </div>
                <div class="pass-detail-row">
                    <span class="pass-detail-label">Registration Date:</span>
                    <span class="pass-detail-value" id="pass-date">--</span>
                </div>
                <div class="pass-detail-row">
                    <span class="pass-detail-label">Registration Time:</span>
                    <span class="pass-detail-value" id="pass-time">--</span>
                </div>
                <div class="pass-detail-row">
                    <span class="pass-detail-label">Purpose:</span>
                    <span class="pass-detail-value" id="pass-purpose">--</span>
                </div>
                <div class="pass-detail-row">
                    <span class="pass-detail-label">Person to Visit:</span>
                    <span class="pass-detail-value" id="pass-person-to-visit">--</span>
                </div>
            </div>
            
            <div class="pass-actions">
                <button type="button" class="pass-btn secondary" id="print-pass-btn">Print Pass</button>
                <button type="button" class="pass-btn primary" id="close-pass-btn">Close</button>
            </div>
        </div>
    </div>
    
    <script>
        let cameraStream = null;
        let capturedPhotoBlob = null;
        let registrationData = {};

        // Generate visitor pass code
        function generateVisitorPassCode() {
            const prefix = 'VP';
            const timestamp = Date.now().toString().slice(-6); // Last 6 digits of timestamp
            const random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
            return `${prefix}-${timestamp}-${random}`;
        }

        // Format current date and time
        function getCurrentDateTime() {
            const now = new Date();
            const dateOptions = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            const timeOptions = { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit',
                hour12: true 
            };
            
            return {
                date: now.toLocaleDateString('en-US', dateOptions),
                time: now.toLocaleTimeString('en-US', timeOptions),
                timestamp: now.toISOString()
            };
        }

        // Show visitor pass confirmation
        function showVisitorPass(formData) {
            const passCode = generateVisitorPassCode();
            const dateTime = getCurrentDateTime();
            
            // Store registration data
            registrationData = {
                passCode: passCode,
                visitorName: formData.fullName,
                contactNumber: formData.contactNumber,
                address: formData.address || 'Not specified',
                purpose: formData.purpose,
                purposeOther: formData.purposeOther || null,
                department: formData.department || 'Not specified',
                departmentOther: formData.departmentOther || null,
                idType: formData.idType || 'Not specified',
                idTypeOther: formData.idTypeOther || null,
                personToVisit: formData.personToVisit,
                isGroupVisit: formData.isGroupVisit,
                groupSize: formData.groupSize || 1,
                groupMembers: formData.groupMembers || [],
                notes: formData.notes || 'None',
                registrationDate: dateTime.date,
                registrationTime: dateTime.time,
                timestamp: dateTime.timestamp,
                hasPhoto: !!capturedPhotoBlob
            };
            
            // Update modal content
            document.getElementById('visitor-pass-code').textContent = passCode;
            document.getElementById('pass-visitor-name').textContent = formData.fullName;
            document.getElementById('pass-date').textContent = dateTime.date;
            document.getElementById('pass-time').textContent = dateTime.time;
            document.getElementById('pass-purpose').textContent = getPurposeText(formData.purpose);
            document.getElementById('pass-person-to-visit').textContent = formData.personToVisit;
            
            // Show modal
            document.getElementById('pass-confirmation').style.display = 'flex';
            
            console.log('Visitor Registration Data:', registrationData);
        }

        // Get purpose text from select value
        function getPurposeText(value, otherValue = null) {
            const purposes = {
                'academic': 'Academic Inquiry',
                'business': 'Business/Official',
                'personal': 'Personal Visit',
                'delivery': 'Delivery',
                'other': otherValue || 'Other'
            };
            return purposes[value] || value;
        }

        // Print visitor pass
        function printVisitorPass() {
            const printContent = `
                <div style="font-family: Arial, sans-serif; max-width: 400px; margin: 0 auto; padding: 20px; border: 2px solid #1a4d2e;">
                    <div style="text-align: center; margin-bottom: 20px;">
                        <h2 style="color: #1a4d2e; margin: 0;">St. Dominic Savio College</h2>
                        <p style="margin: 5px 0; color: #c9a84c;">VISITOR PASS</p>
                    </div>
                    
                    <div style="background: #e8f5ee; padding: 15px; margin: 15px 0; text-align: center;">
                        <div style="font-size: 12px; color: #2d7a4f; font-weight: bold;">PASS CODE</div>
                        <div style="font-size: 24px; font-weight: bold; color: #1a4d2e; font-family: monospace;">${registrationData.passCode}</div>
                    </div>
                    
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="padding: 8px 0; border-bottom: 1px solid #ddd;"><strong>Visitor:</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #ddd;">${registrationData.visitorName}</td></tr>
                        <tr><td style="padding: 8px 0; border-bottom: 1px solid #ddd;"><strong>Purpose:</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #ddd;">${getPurposeText(document.getElementById('purpose').value)}</td></tr>
                        <tr><td style="padding: 8px 0; border-bottom: 1px solid #ddd;"><strong>Visiting:</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #ddd;">${registrationData.personToVisit}</td></tr>
                        <tr><td style="padding: 8px 0; border-bottom: 1px solid #ddd;"><strong>Date:</strong></td><td style="padding: 8px 0; border-bottom: 1px solid #ddd;">${registrationData.registrationDate}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Time:</strong></td><td style="padding: 8px 0;">${registrationData.registrationTime}</td></tr>
                    </table>
                    
                    <div style="text-align: center; margin-top: 20px; font-size: 12px; color: #666;">
                        <p>Please present this pass to security upon entry</p>
                    </div>
                </div>
            `;
            
            const printWindow = window.open('', '', 'width=600,height=800');
            printWindow.document.write(printContent);
            printWindow.document.close();
            printWindow.print();
        }

        // Camera functionality
        async function startCamera() {
            try {
                const constraints = {
                    video: {
                        width: { ideal: 640 },
                        height: { ideal: 480 },
                        facingMode: 'user' // Front-facing camera
                    }
                };
                
                cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
                const video = document.getElementById('camera-video');
                video.srcObject = cameraStream;
                
                // Show camera preview
                document.getElementById('photo-zone').style.display = 'none';
                document.getElementById('camera-preview').style.display = 'block';
                document.getElementById('photo-preview').style.display = 'none';
                
            } catch (error) {
                console.error('Error accessing camera:', error);
                alert('Unable to access camera. Please check permissions and try again.');
            }
        }

        function stopCamera() {
            if (cameraStream) {
                cameraStream.getTracks().forEach(track => track.stop());
                cameraStream = null;
            }
            
            // Hide camera preview
            document.getElementById('camera-preview').style.display = 'none';
            document.getElementById('photo-zone').style.display = 'block';
        }

        function capturePhoto() {
            const video = document.getElementById('camera-video');
            const canvas = document.getElementById('photo-canvas');
            const context = canvas.getContext('2d');
            
            // Set canvas dimensions to match video
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            
            // Draw current video frame to canvas
            context.drawImage(video, 0, 0);
            
            // Convert canvas to blob
            canvas.toBlob(function(blob) {
                capturedPhotoBlob = blob;
                
                // Create preview
                const img = document.getElementById('photo-img');
                img.src = canvas.toDataURL('image/jpeg', 0.8);
                
                // Show preview and hide camera
                stopCamera();
                document.getElementById('photo-preview').style.display = 'block';
            }, 'image/jpeg', 0.8);
        }

        function retakePhoto() {
            capturedPhotoBlob = null;
            // Clear file input
            document.getElementById('validId').value = '';
            document.getElementById('photo-preview').style.display = 'none';
            document.getElementById('photo-zone').style.display = 'block';
        }

        // Initialize event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Group visit toggle
            const groupVisitYes = document.getElementById('groupVisitYes');
            const groupVisitNo = document.getElementById('groupVisitNo');
            const groupSizeContainer = document.getElementById('groupSizeContainer');
            const groupSizeInput = document.getElementById('groupSize');
            
            groupVisitYes.addEventListener('change', function() {
                if (this.checked) {
                    groupSizeContainer.style.display = 'flex';
                    groupSizeInput.required = true;
                }
            });
            
            groupVisitNo.addEventListener('change', function() {
                if (this.checked) {
                    groupSizeContainer.style.display = 'none';
                    groupSizeInput.required = false;
                    groupSizeInput.value = '';
                    // Hide and clear group members fields
                    document.getElementById('groupMembersContainer').style.display = 'none';
                    document.getElementById('groupMembersFields').innerHTML = '';
                }
            });
            
            // Group size change - generate name fields
            groupSizeInput.addEventListener('input', function() {
                const numberOfPeople = parseInt(this.value);
                const groupMembersContainer = document.getElementById('groupMembersContainer');
                const groupMembersFields = document.getElementById('groupMembersFields');
                
                if (numberOfPeople >= 2 && numberOfPeople <= 100) {
                    groupMembersContainer.style.display = 'flex';
                    
                    // Clear existing fields
                    groupMembersFields.innerHTML = '';
                    
                    // Generate name fields
                    for (let i = 1; i <= numberOfPeople; i++) {
                        const fieldDiv = document.createElement('div');
                        fieldDiv.style.cssText = 'display: flex; flex-direction: column;';
                        
                        const label = document.createElement('label');
                        label.style.cssText = 'font-size: 0.85rem; color: var(--green-dark); margin-bottom: 0.5rem; font-weight: 500;';
                        label.textContent = `Person ${i} Full Name *`;
                        
                        const input = document.createElement('input');
                        input.type = 'text';
                        input.className = 'form-input';
                        input.placeholder = `Enter full name of person ${i}`;
                        input.required = true;
                        input.id = `groupMember${i}`;
                        input.name = `groupMember${i}`;
                        
                        fieldDiv.appendChild(label);
                        fieldDiv.appendChild(input);
                        groupMembersFields.appendChild(fieldDiv);
                    }
                } else {
                    groupMembersContainer.style.display = 'none';
                    groupMembersFields.innerHTML = '';
                }
            });
            
            // Dropdown "Other" handling
            // Purpose of Visit
            const purposeSelect = document.getElementById('purpose');
            const purposeOtherContainer = document.getElementById('purposeOtherContainer');
            const purposeOtherInput = document.getElementById('purposeOther');
            
            purposeSelect.addEventListener('change', function() {
                if (this.value === 'other') {
                    purposeOtherContainer.style.display = 'flex';
                    purposeOtherInput.required = true;
                } else {
                    purposeOtherContainer.style.display = 'none';
                    purposeOtherInput.required = false;
                    purposeOtherInput.value = '';
                }
            });
            
            // Department
            const departmentSelect = document.getElementById('department');
            const departmentOtherContainer = document.getElementById('departmentOtherContainer');
            const departmentOtherInput = document.getElementById('departmentOther');
            
            departmentSelect.addEventListener('change', function() {
                if (this.value === 'other') {
                    departmentOtherContainer.style.display = 'flex';
                    departmentOtherInput.required = false; // Not required as department itself is optional
                } else {
                    departmentOtherContainer.style.display = 'none';
                    departmentOtherInput.value = '';
                }
            });
            
            // ID Type
            const idTypeSelect = document.getElementById('idType');
            const idTypeOtherContainer = document.getElementById('idTypeOtherContainer');
            const idTypeOtherInput = document.getElementById('idTypeOther');
            
            idTypeSelect.addEventListener('change', function() {
                if (this.value === 'other') {
                    idTypeOtherContainer.style.display = 'flex';
                    idTypeOtherInput.required = false; // Not required as ID type itself is optional
                } else {
                    idTypeOtherContainer.style.display = 'none';
                    idTypeOtherInput.value = '';
                }
            });
            
            // Camera controls
            document.getElementById('start-camera-btn').addEventListener('click', startCamera);
            document.getElementById('capture-btn').addEventListener('click', capturePhoto);
            document.getElementById('close-camera-btn').addEventListener('click', stopCamera);
            document.getElementById('retake-btn').addEventListener('click', retakePhoto);

            // Contact number validation - only allow numbers
            const contactNumberInput = document.getElementById('contactNumber');
            contactNumberInput.addEventListener('input', function(e) {
                // Remove any non-numeric characters
                this.value = this.value.replace(/[^0-9]/g, '');
                
                // Limit to 11 digits
                if (this.value.length > 11) {
                    this.value = this.value.slice(0, 11);
                }
            });
            
            contactNumberInput.addEventListener('keypress', function(e) {
                // Only allow numeric keys
                if (e.key && !/[0-9]/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Delete' && e.key !== 'Tab' && e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') {
                    e.preventDefault();
                }
            });

            // ID upload
            const idInput = document.getElementById('validId');
            idInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    // Create preview for uploaded file
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.getElementById('photo-img');
                        img.src = e.target.result;
                        document.getElementById('photo-filename').textContent = file.name;
                        
                        // Show preview and hide upload zone
                        document.getElementById('photo-zone').style.display = 'none';
                        document.getElementById('photo-preview').style.display = 'block';
                    };
                    if (file.type.startsWith('image/')) {
                        reader.readAsDataURL(file);
                    }
                }
            });

            // Form submission
            const form = document.getElementById('visitor-form');
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Collect form data
                const isGroupVisit = document.getElementById('groupVisitYes').checked;
                const groupSize = isGroupVisit ? parseInt(document.getElementById('groupSize').value) : null;
                
                // Collect group member names if it's a group visit
                let groupMembers = [];
                if (isGroupVisit && groupSize) {
                    for (let i = 1; i <= groupSize; i++) {
                        const memberInput = document.getElementById(`groupMember${i}`);
                        if (memberInput) {
                            groupMembers.push(memberInput.value);
                        }
                    }
                }
                
                const formData = {
                    fullName: document.getElementById('fullName').value,
                    contactNumber: document.getElementById('contactNumber').value,
                    address: document.getElementById('address').value,
                    purpose: document.getElementById('purpose').value,
                    purposeOther: document.getElementById('purpose').value === 'other' ? document.getElementById('purposeOther').value : null,
                    department: document.getElementById('department').value,
                    departmentOther: document.getElementById('department').value === 'other' ? document.getElementById('departmentOther').value : null,
                    idType: document.getElementById('idType').value,
                    idTypeOther: document.getElementById('idType').value === 'other' ? document.getElementById('idTypeOther').value : null,
                    personToVisit: document.getElementById('personToVisit').value,
                    isGroupVisit: isGroupVisit,
                    groupSize: groupSize,
                    groupMembers: groupMembers,
                    notes: document.getElementById('notes').value
                };
                
                // Validate required fields
                if (!formData.fullName || !formData.contactNumber || !formData.purpose || !formData.personToVisit) {
                    alert('Please fill in all required fields marked with *');
                    return;
                }
                
                // Validate "other" purpose specification
                if (formData.purpose === 'other' && !formData.purposeOther) {
                    alert('Please specify your purpose of visit');
                    return;
                }
                
                // Validate Philippine mobile number format
                const phonePattern = /^09[0-9]{9}$/;
                if (!phonePattern.test(formData.contactNumber)) {
                    alert('Please enter a valid Philippine mobile number (e.g., 09123456789)');
                    return;
                }
                
                // Validate group size if group visit is selected
                if (formData.isGroupVisit && (!formData.groupSize || formData.groupSize < 2)) {
                    alert('Please enter the total number of people in your group (minimum 2)');
                    return;
                }
                
                // Validate group member names
                if (formData.isGroupVisit && formData.groupMembers.length !== formData.groupSize) {
                    alert('Please enter the full names of all group members');
                    return;
                }
                
                // Show visitor pass confirmation
                showVisitorPass(formData);
            });

            // Modal event listeners
            document.getElementById('close-pass-btn').addEventListener('click', function() {
                document.getElementById('pass-confirmation').style.display = 'none';
                // Reset form after successful registration
                document.getElementById('visitor-form').reset();
                // Reset photo preview
                document.getElementById('photo-preview').style.display = 'none';
                document.getElementById('photo-zone').style.display = 'block';
                capturedPhotoBlob = null;
            });

            document.getElementById('print-pass-btn').addEventListener('click', printVisitorPass);

            // Add some visual feedback on focus
            const inputs = document.querySelectorAll('.form-input, .form-select, .form-textarea');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('focused');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('focused');
                });
            });

            // Close modal when clicking outside
            document.getElementById('pass-confirmation').addEventListener('click', function(e) {
                if (e.target === this) {
                    document.getElementById('close-pass-btn').click();
                }
            });
        });
    </script>
</body>
</html>
<?php
}
exit();
?>